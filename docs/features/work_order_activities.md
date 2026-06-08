# FEATURE_SPEC — Sprint 3a: work_order_activities

> Estado: Completado | Fecha: 2026-06-04

---

## 1. Entrada (Input)

No requiere formulario de usuario. Las actividades las crea el sistema automáticamente al ejecutar acciones sobre la OT (cambio de estado, nota, asignación, QC).

### Datos de Contexto

- `tenant_id`: resuelto por `TenantManager` automáticamente
- `work_order_id`: OT sobre la que se registra la actividad
- `user_id`: del request autenticado (nullable — sistema puede registrar sin usuario)

### Validaciones Clave

- `type` debe ser uno de: `status_change`, `note`, `assignment`, `qc`
- `metadata` siempre `{}` por defecto, extensible sin migraciones

---

## 2. Proceso (Processing)

No requiere servicios ni acciones nuevas. Las actividades se crean directamente vía `WorkOrderActivity::create()` desde el sistema (no desde UI).

---

## 3. Estado (State)

### Tablas

| Tabla | Operación | RLS |
|-------|-----------|-----|
| `work_order_activities` | CREATE | ✅ 4 políticas (SELECT/INSERT/UPDATE/DELETE) + FORCE |

### Campos

| Campo | Tipo | Requerido | Default | Descripción |
|-------|------|-----------|---------|-------------|
| `id` | `uuid PK` | sí | `gen_random_uuid()` | |
| `tenant_id` | `uuid FK → tenants` | sí | — | inyectado por RLS |
| `work_order_id` | `uuid FK → work_orders ON DELETE CASCADE` | sí | — | OT asociada |
| `user_id` | `uuid FK → users ON DELETE SET NULL` | no | `null` | Quién ejecutó la acción |
| `type` | `varchar(50)` | sí | — | `status_change` / `note` / `assignment` / `qc` |
| `description` | `text` | sí | — | Texto legible del evento |
| `from_status` | `varchar(50)` | no | `null` | Solo para `type=status_change` |
| `to_status` | `varchar(50)` | no | `null` | Solo para `type=status_change` |
| `metadata` | `jsonb` | no | `{}` | Datos extra del evento |
| `created_at` | `timestamptz` | sí | `now()` | |
| `updated_at` | `timestamptz` | sí | `now()` | |

Sin `deleted_at` — actividades son inmutables.

### Índices

- `(tenant_id)` — `idx_woa_tenant`
- `(tenant_id, work_order_id)` — `idx_woa_work_order`
- `(tenant_id, user_id)` partial `WHERE user_id IS NOT NULL` — `idx_woa_user`

---

## 4. Renderizado (Rendering)

ActivitiesRelationManager en WorkOrderResource — solo lectura (sin Create/Edit/Delete).

| Columna | Componente | Descripción |
|---------|-----------|-------------|
| `type` | `TextColumn::make('type')->badge()` | Label del enum |
| `description` | `TextColumn::make('description')->limit(50)` | |
| `user.name` | `TextColumn::make('user.name')` | Placeholder "Sistema" si null |
| `created_at` | `TextColumn::make('created_at')->since()` | Tiempo relativo |

Ordenado por `created_at DESC`.

---

## 5. Salida (Output)

- Vista de timeline en el detalle de la OT (pestaña "Actividades" en el RelationManager)
- Sin acciones de usuario — solo consulta

---

## 6. Seguridad

- RLS en tabla nueva: sí (4 políticas estándar + FORCE)
- Sin SoftDeletes — actividades inmutables
- No expone datos cross-tenant
- Sin permisos Spatie adicionales (solo consulta, misma política que WorkOrder)

---

## 7. Tests (4)

- [x] `test_work_order_activity_can_be_created` — campos persisten correctamente
- [x] `test_work_order_activity_tenant_isolation` — otro tenant no ve la actividad
- [x] `test_work_order_has_activities_relation` — `$wo->activities` retorna colección ordenada
- [x] `test_activity_type_enum_has_four_cases` — enum tiene 4 cases exactos

---

## 8. Dependencias

- Features previos: Sprint 1 (WorkOrder Core Fields), Sprint 2 (WorkOrderItem type)
- Paquetes nuevos: ninguno
- Servicios externos: ninguno

---

## 9. Checklist de Aprobación

- [x] Nombre del feature cumple Zero Redundancy
- [x] No duplica lógica existente
- [x] El modelo de datos es agnóstico de industria
- [x] RLS + FORCE RLS incluidos en la migración
- [x] Sin SoftDeletes
- [x] 111 tests, 304 assertions — 0 regresiones
