# FEATURE_SPEC — Aprobación Digital de Presupuestos (Quote Approval)

> Estado: Borrador | Autor: opencode | Fecha: 2026-06-19

---

## 1. Objetivo

Permitir que el cliente apruebe o rechace un presupuesto de Work Order a través de un link compartido por WhatsApp/email, sin necesidad de login. Cierra el ciclo comercial entre `work_order_reception` (cotización) y `facturacion` (emisión de factura).

---

## 2. Actores y Roles

| Actor | Rol | Interacción |
|-------|-----|------------|
| Asesor / Recepcionista | editor | Acciona "Enviar Presupuesto" en Filament → copia link firmado → lo envía al cliente |
| Cliente | — (sin auth) | Abre link en su celular → ve presupuesto → aprueba o rechaza |
| Admin taller | owner | Visualiza estado de aprobación en la WO |
| Mecánico | editor | NO puede modificar items mientras WO está en `waiting_approval` |

---

## 3. Flujo Principal (Happy Path)

### Paso 1 — Envío del Presupuesto (Filament)
1. La WO está en estado `quoted` (tiene diagnóstico + items cotizados)
2. Asesor abre la WO en Filament → acciona botón **"Enviar Presupuesto"**
3. `RequestQuoteApprovalAction::execute(WorkOrder)`:
   - Cambia estado WO → `waiting_approval`
   - Genera `URL::signedRoute('quote.approval', $workOrder, expiresAt: now()->addDays(7))`
   - Retorna la URL firmada
4. Filament muestra la URL al asesor → la copia al portapapeles
5. Asesor pega el link en WhatsApp/email y lo envía al cliente

### Paso 2 — Visualización (Página Pública)
1. Cliente abre el link → ruta `/presupuesto/{workOrder}/{signature}`
2. Sistema valida firma (`URL::hasValidSignature($request)`)
3. Si firma inválida o expirada → muestra error "Link inválido o expirado"
4. Si firma válida → renderiza página pública mobile-first:
   - Datos del taller (nombre, dirección, teléfono)
   - Datos del vehículo: placa, marca, modelo, año
   - Diagnóstico: `service_description` + `diagnosis_summary`
   - Tabla de items cotizados (descripción, cantidad, precio unitario, total)
   - Total general (suma de todos los items)
   - Estado actual: "Esperando tu aprobación"
   - Dos botones grandes: ✅ **Aprobar y Autorizar** | ❌ **Rechazar**

### Paso 3 — Acción del Cliente
#### Si aprueba:
1. Cliente hace clic en "Aprobar y Autorizar"
2. `POST /presupuesto/{workOrder}/{signature}/approve` con firma válida
3. Sistema:
   - Verifica que WO siga en `waiting_approval` (no fue modificada entre tanto)
   - Setea `status = 'approved'` (nuevo estado intermedio)
   - Setea `approval_at = now()`
   - Setea `approval_channel = 'web'`
   - Invalida la firma (no se puede reusar)
   - Registra `WorkOrderActivity` tipo `status_change` con metadata del approval
4. Muestra pantalla de confirmación: "Presupuesto aprobado. Te contactaremos pronto."
5. En el taller: la WO aparece con estado `approved` → asesor puede avanzar a `in_progress` o generar factura

#### Si rechaza:
1. Cliente hace clic en "Rechazar"
2. Modal/prompt pide motivo (textarea, required, min 10 chars)
3. `POST /presupuesto/{workOrder}/{signature}/reject` con firma + motivo
4. Sistema:
   - Cambia WO a `rejected`
   - Setea `rejected_at` en field de WO (o agrega rejection_reason a metadata)
   - Setea `approval_channel = 'web'`
   - Invalida la firma
   - Registra `WorkOrderActivity` con el motivo
5. Muestra pantalla: "Presupuesto rechazado. Si querés reconsiderar, contactanos."
6. En el taller: la WO queda en `rejected` → asesor puede re-cotizar y volver a `quoted`

---

## 4. Reglas de Negocio (The "Gotchas")

### Bloqueo de edición en waiting_approval
- Mientras la WO está en `waiting_approval`:
  - El sistema **NO bloquea** la edición de la WO en Filament (el asesor podría necesitar agregar notas internas).
  - Pero **cualquier modificación a WorkOrderItems** (crear, editar, eliminar) **revierte el estado automáticamente a `quoted`** e invalida la URL firmada actual.
  - Implementación: observer `WorkOrderItemObserver` o lógica en `WorkOrderObserver::updating()` que detecte cambios en items.
  - Esto evita que el cliente apruebe un presupuesto desactualizado.

### Firma única, un solo uso
- `URL::signedRoute()` con `expires: 7 days`. Sin token en DB.
- Una vez que el cliente aprueba o rechaza: la firma sigue siendo técnicamente válida (no expiró), pero el sistema rechaza el segundo intento porque el estado de la WO ya no es `waiting_approval`.
- Esto se loga con un guarda al inicio de los handlers: `if ($workOrder->status !== 'waiting_approval') { abort(409, 'El presupuesto ya fue respondido.'); }`

### Nuevos estados intermedios
- `approved`: estado nuevo entre `waiting_approval` e `in_progress`. Indica que el cliente autorizó.
- `rejected`: estado nuevo entre `waiting_approval` y el reinicio del ciclo.
- **Actualizar `WorkOrderStatusEnum`** con estos dos casos.

### Transiciones de estado permitidas
```
quoted ──> waiting_approval  (asesor envía presupuesto)
waiting_approval ──> approved  (cliente aprueba)
waiting_approval ──> rejected  (cliente rechaza)
waiting_approval ──> quoted    (asesor modifica items → revierte)
approved ──> in_progress       (taller arranca trabajo)
approved ──> quoted            (se necesita re-cotizar)
rejected ──> quoted            (asesor re-cotiza y re-envía)
```

### Página pública: sin assets de Filament
- Vista Blade standalone, sin Livewire, sin Filament.
- CSS: Tailwind v4 purgado (solo las clases que usa esta vista).
- Sin JavaScript obligatorio (el modal de rechazo puede ser un `<dialog>` nativo o un formulario en otra ruta).
- Mobile-first: los botones de aprobar/rechazar deben ser touch-friendly (min 44px height).
- NO requiere autenticación ni session. Es una página pública con protección por firma.

### Seguridad
| Riesgo | Mitigación |
|--------|-----------|
| Adivinar ID de otra WO | Firma HMAC con APP_KEY. Sin firma válida → 403. |
| Replay de la misma firma | Guarda de estado: solo `waiting_approval` permite acción. Después → 409. |
| Modificación de items post-envío | Observer que revierte a `quoted` si items cambian. |
| Cliente ve items de otra WO | Firma está ligada al ID de la WO. Sin firma no se renderiza nada. |
| CSRF en POST | Laravel `signedRoute` garantiza integridad de la URL. No se necesita CSRF token adicional si la ruta es signed. |

---

## 5. Modelos y Relaciones Clave

### Cambios en modelos existentes

**WorkOrder** (sin nuevos campos):
- Los campos `approval_at`, `approval_channel` ya existen en el modelo (de la migración Sprint 1).
- `rejected_at` y `rejection_reason` se pueden almacenar en `metadata` (JSONB) existente, o agregar columnas. Para MVP: los guardamos en `metadata`.

**WorkOrderStatusEnum** (agregar):
| Case | Value | Label | Color |
|------|-------|-------|-------|
| Approved | `approved` | Aprobado | success |
| Rejected | `rejected` | Rechazado | danger |

### Sin nuevas tablas
- Todo se resuelve con estados + metadata existente + URL signed de Laravel.

---

## 6. UI / Filament Resources

### Acción en Filament (WorkOrder Edit page)
- **Botón:** "Enviar Presupuesto" en header actions de EditWorkOrder.
- **Condición:** visible solo cuando `$record->status === WorkOrderStatusEnum::Quoted`.
- **Comportamiento:**
  1. Ejecuta `RequestQuoteApprovalAction`
  2. Muestra modal con el link generado + botón "Copiar al portapapeles"
  3. Al cerrar, refresca la página (WO ahora en `waiting_approval`)

### Página Pública (Blade)
- **Rutas (web.php, sin middleware `auth`):**
  | Método | Ruta | Nombre | Propósito |
  |--------|------|--------|-----------|
  | GET | `/presupuesto/{workOrder}/{signature}` | `quote.approval` | Ver presupuesto |
  | POST | `/presupuesto/{workOrder}/{signature}/approve` | `quote.approval.approve` | Aprobar |
  | POST | `/presupuesto/{workOrder}/{signature}/reject` | `quote.approval.reject` | Rechazar |

- **Vistas:**
  - `resources/views/quote-approval/show.blade.php` — página principal
  - `resources/views/quote-approval/approved.blade.php` — confirmación de aprobación
  - `resources/views/quote-approval/rejected.blade.php` — confirmación de rechazo
  - `resources/views/quote-approval/expired.blade.php` — link expirado/inválido

---

## 7. Tests Requeridos (TDD)

### Feature Tests (el orden en que se escriben)

| # | Test | Cobertura |
|---|------|-----------|
| 1 | `test_sends_quote_and_generates_signed_url` | Action cambia estado a `waiting_approval`, retorna URL con firma válida |
| 2 | `test_public_page_shows_quote_details` | GET con firma → ve datos de WO + items |
| 3 | `test_public_page_rejects_invalid_signature` | GET sin firma o firma alterada → 403 |
| 4 | `test_public_page_rejects_expired_signature` | GET con firma vencida → error expirado |
| 5 | `test_client_can_approve_quote` | POST approve → status = `approved`, approval_at set, approval_channel = 'web' |
| 6 | `test_client_can_reject_quote_with_reason` | POST reject con motivo → status = `rejected`, motivo guardado |
| 7 | `test_cannot_approve_twice` | POST approve dos veces → segundo 409 |
| 8 | `test_modifying_items_reverts_to_quoted` | Editar item mientras `waiting_approval` → revierte a `quoted`, invalida |

---

## 8. Dependencias

- Laravel `URL::signedRoute()` (core, sin paquetes externos)
- `WorkOrderStatusEnum` (extender con `approved` + `rejected`)
- `WorkOrderObserver` (agregar lógica de reversión si items cambian)
- Ningún paquete nuevo

---

## 9. Checklist de Aprobación

- [ ] Nombre del feature cumple Zero Redundancy
- [ ] No duplica lógica existente (`approval_at`/`approval_channel` ya existen en WO)
- [ ] RLS no requiere cambios (no hay tablas nuevas)
- [ ] No requiere migración de base de datos (todo en metadata + signed URL)
- [ ] Estados aprobados/rechazados documentados en el enum
- [ ] El flujo de edición de items durante `waiting_approval` está resuelto
- [ ] La página pública no requiere autenticación
- [ ] Este spec fue revisado por John
