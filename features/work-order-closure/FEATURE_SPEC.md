# Feature: Cierre de orden de trabajo en taller

**Estado:** Borrador | **Fecha:** 2026-06-17 | **Autor:** John + Kimi + Claude | **Rama:** `feature/work-order-closure`

---

## 1. Goal

Garantizar que toda orden completada tenga evidencia documentada y consentimiento del cliente antes de liberar responsabilidad del taller.

## Non-Goals

- No evalúa calidad del trabajo (es responsabilidad del mecánico)
- No gestiona pagos pendientes (spec separado)
- No programa recogida (spec separado)

---

## 2. State Diagram

Se extiende `WorkOrderStatusEnum` agregando 4 nuevos estados. Los estados existentes (`draft`, `received`, `diagnosing`, `quoted`, `waiting_approval`, `waiting_parts`, `paused`, `qc`, `cancelled`) permanecen igual.

### Nuevos estados

```
en_proceso ──┬──→ trabajo_terminado ──→ esperando_cliente ──┬──→ completada
             │                                               │
             └──→ evidencia_pendiente ──→ trabajo_terminado  ├──→ no_recoge ──→ completada
                                                             └──→ incumplimiento (72h)
```

### Transiciones

| De | A | Requisito |
|---|---|---|
| `en_proceso` | `trabajo_terminado` | Evidencia completa, mecánico marca listo |
| `en_proceso` | `evidencia_pendiente` | Mecánico intenta marcar listo pero falta evidencia |
| `evidencia_pendiente` | `trabajo_terminado` | Mecánico completa fotos y descripción |
| `trabajo_terminado` | `esperando_cliente` | Sistema notifica al cliente para autorización |
| `esperando_cliente` | `completada` | Cliente firma digital o autoriza vía SMS |
| `esperando_cliente` | `no_recoge` | Cliente autoriza retener vehículo, firma compromiso 72h |
| `no_recoge` | `completada` | Cliente firma digital en recogida |
| `esperando_cliente` | `incumplimiento` | Sin respuesta del cliente en 72h (automático) |
| `no_recoge` | `incumplimiento` | Cliente no recoge en 72h (automático) |

---

## 3. Rules

### R1 — `en_proceso` → `trabajo_terminado` o `evidencia_pendiente`
Requiere al menos 1 foto de evidencia y descripción técnica del trabajo realizado. Si cumple, transita a `trabajo_terminado`. Si falta evidencia, transita a `evidencia_pendiente`.

### R2 — `trabajo_terminado` → `completada`
Requiere firma digital del cliente o código SMS de autorización.

### R3 — `trabajo_terminado` → `no_recoge`
Requiere que cliente explícitamente autorice retener vehículo y firme compromiso de recogida en 72h máximo. La firma es digital vía SMS (mismo mecanismo que R2). Papel escaneado es fallback documentado con foto adjunta obligatoria.

### R4 — `no_recoge` → `completada`
Requiere firma digital en recogida. Alerta automática a las 48h si no ha recogido.

### R5 — SMS: envío y validación
- Cada solicitud de autorización genera un código nuevo (no reutilizar)
- Máximo 3 reenvíos (`send_count`) por orden
- Máximo 5 intentos de validación (`attempts`) por código
- Código expira a los 15 minutos

---

## 4. Enum Mapping (legacy → nuevo)

### `WorkOrderStatusEnum` — casos nuevos a agregar

| Caso | Value | Label | Color |
|---|---|---|---|
| `WorkDone` | `work_done` | Trabajo terminado | `success` |
| `EvidencePending` | `evidence_pending` | Evidencia pendiente | `warning` |
| `WaitingClient` | `waiting_client` | Esperando cliente | `info` |
| `NoPickup` | `no_pickup` | No recoge | `danger` |
| `Breach` | `breach` | Incumplimiento | `danger` |

### Reconciliación de `completed` legacy

Las órdenes existentes con estado `completed` se migran a `work_done` con flag `is_legacy = true`. Esto indica que son datos previos al nuevo flujo y no aplican las reglas de transición (no requieren evidencia ni firma).

**Enforcement:** El código de transición del nuevo flujo debe verificar `settings->is_legacy_closure` y saltar la validación si es `true`. Cualquier intento de transición nueva (`completada`, `no_recoge`, `breach`) en una orden legacy debe lanzar excepción.

```sql
UPDATE work_orders
SET status = 'work_done', settings = jsonb_set(settings, '{is_legacy_closure}', 'true')
WHERE status = 'completed';
```

---

## 5. Schema Changes

### `work_orders` — columnas nuevas

| Columna | Tipo | Default | Descripción |
|---|---|---|---|
| `signature_hash` | `text` | `null` | Hash SHA-256 de la firma digital o código SMS |
| `signed_at` | `timestamp` | `null` | Momento de la firma |
| `closure_notes` | `text` | `null` | Notas adicionales del cierre |

### `contacts` — columna nueva

| Columna | Tipo | Default | Descripción |
|---|---|---|---|
| `blocked_until` | `timestamp` | `null` | Si está seteado, el cliente no puede crear nuevas órdenes hasta esta fecha |

### Tabla nueva: `sms_codes`

```sql
CREATE TABLE sms_codes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    work_order_id UUID NOT NULL REFERENCES work_orders(id) ON DELETE CASCADE,
    code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    send_count INT NOT NULL DEFAULT 0,    -- reenvíos de SMS (máx 3)
    attempts INT NOT NULL DEFAULT 0,      -- intentos de validación del código (máx 5)
    used_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT fk_sms_codes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_sms_codes_work_order FOREIGN KEY (work_order_id) REFERENCES work_orders(id)
);

CREATE INDEX idx_sms_codes_work_order ON sms_codes(work_order_id);
CREATE INDEX idx_sms_codes_tenant ON sms_codes(tenant_id);
```

### RLS en `sms_codes`

```sql
ALTER TABLE sms_codes ENABLE ROW LEVEL SECURITY;
ALTER TABLE sms_codes FORCE ROW LEVEL SECURITY;

CREATE POLICY sms_codes_tenant_isolation_select ON sms_codes FOR SELECT
    USING (tenant_id = public.current_tenant_id());

CREATE POLICY sms_codes_tenant_isolation_insert ON sms_codes FOR INSERT
    WITH CHECK (tenant_id = public.current_tenant_id());

CREATE POLICY sms_codes_tenant_isolation_update ON sms_codes FOR UPDATE
    USING (tenant_id = public.current_tenant_id())
    WITH CHECK (tenant_id = public.current_tenant_id());

CREATE POLICY sms_codes_tenant_isolation_delete ON sms_codes FOR DELETE
    USING (tenant_id = public.current_tenant_id());
```

---

## 6. Error Handling

| Escenario | Comportamiento |
|---|---|
| Sin foto en R1 | Transición automática a `evidencia_pendiente`. Botón "completar cierre" desactivado. Mecánico puede agregar fotos/descripción y reintentar. Puede guardar borrador en `en_proceso` sin intentar cierre. |
| Código SMS expirado | Mostrar "Código expirado". Ofrecer reenviar (máx 3 reenvíos). |
| Código SMS inválido tras 5 intentos | Mostrar "Código bloqueado". Estado permanece `esperando_cliente`. Ofrecer reenviar nuevo código. |
| Cliente no presente en R2 | Ofrecer opción `no_recoge` con SMS de autorización. Si rechaza, estado permanece `esperando_cliente`. |
| Cliente no recoge en 72h | Estado automático a `incumplimiento`. Notificación a dueño de taller. Bloqueo de nuevas órdenes para ese cliente hasta que `blocked_until` expire o se regularice manualmente. |
| Máximo 3 reenvíos alcanzado | Botón reenviar desactivado. Estado permanece `esperando_cliente`. Opción manual: operador contacta al cliente por otro medio. |

---

## 7. Audit Trail

Toda transición de estado guarda en `work_order_activities`:

| Campo | Descripción |
|---|---|
| `work_order_id` | FK a la orden |
| `type` | `status_change` |
| `description` | "Estado cambiado de X a Y" |
| `metadata` | JSON con: `from_status`, `to_status`, `ip`, `signature_hash` (si aplica), `photos_version` (array de UUIDs de fotos adjuntas al momento de la transición) |
| `user_id` | Quién realizó la transición |
| `created_at` | Timestamp |

---

## 8. Tests Requeridos

| # | Test | Categoría |
|---|---|---|
| 1 | `en_proceso` → `trabajo_terminado` requiere evidencia | Happy path |
| 2 | Sin foto en R1 bloquea transición | Edge case |
| 3 | `trabajo_terminado` → `completada` con código SMS válido | Happy path |
| 4 | Código SMS expirado rechaza autorización | Edge case |
| 5 | Máximo 3 reenvíos se bloquea | Edge case |
| 6 | `esperando_cliente` → `no_recoge` con compromiso 72h | Happy path |
| 7 | `no_recoge` → `incumplimiento` automático a las 72h | Edge case |
| 8 | Cliente con `blocked_until` no puede crear nueva orden | Edge case |
| 9 | Migración `completed` legacy → `work_done` + `is_legacy` + assert que `is_legacy=true` no puede hacer transiciones del nuevo flujo | Migración |
| 10 | RLS en `sms_codes` aísla por tenant | Seguridad |

**Total: 10 tests**
