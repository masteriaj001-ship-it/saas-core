# FEATURE_SPEC — Work Orders (Órdenes de Trabajo)

> Estado: En desarrollo activo | Autor: opencode | Fecha: 2026-06-06

---

## Descripción

Módulo para gestionar órdenes de trabajo asignadas a activos (assets) del tenant. Cada orden registra responsable (contact), repuestos/insumos consumidos (items), servicios del catálogo, mano de obra, actividades, inspecciones visuales y archivos multimedia. Agnóstico de industria: aplica a talleres mecánicos, clínicas, mantenimiento industrial, construcción, etc.

---

## Módulo

**WorkOrders** — Existe. Tablas: `work_orders`, `work_order_items`, `work_order_activities`, `work_order_inspections`, `work_order_media`, `contact_roles`. Las entidades existentes `Asset`, `Item`, `Contact` y `ServiceCatalog` se relacionan vía FK.

---

## Funcionalidades implementadas

### Núcleo
- [x] Creación de orden con wizard de 3 pasos (Recepción → Diagnóstico → Cierre)
- [x] WorkOrderItem type = Part / Service / Labor con campos condicionales
- [x] ItemsRelationManager con display_name calculado según tipo
- [x] ServiceCatalog como fuente de servicios con autoprecio
- [x] Stock validation para items tipo product
- [x] Actividades (WorkOrderActivity) con type StatusChange/Note/Assignment/Qc
- [x] Inspección visual (WorkOrderInspection) con 13 defaults pre-cargados al crear
- [x] Archivos multimedia (WorkOrderMedia) vía MinIO S3-compatible
- [x] VehicleFormSchema reusable para datos maestros de vehículos
- [x] Contact Roles separados de contact_type

### Fixes visuales
- [x] ItemsRelationManager muestra nombre de serviceCatalog para servicios y mano de obra
- [x] Inspección de ingreso movida a Step 1 del wizard
- [x] 13 inspecciones pre-cargadas automáticamente al crear orden

---

## Datos

### Tablas

- [x] Tabla: `work_orders` — principal
- [x] Tabla: `work_order_items` — pivote (items consumidos)
- [x] Tabla: `work_order_activities` — log de actividades (inmutable, sin SoftDeletes)
- [x] Tabla: `work_order_inspections` — inspección visual (inmutable, sin SoftDeletes)
- [x] Tabla: `work_order_media` — archivos multimedia vía MinIO (inmutable, sin SoftDeletes)
- [x] Tabla: `contact_roles` — roles laborales separados de contact_type

---

## Casos de Uso

- [ ] UC-01: **Creación de orden** — Admin/Editor puede crear una orden de trabajo con datos básicos (código, asset, contacto asignado, descripción, prioridad) cuando el tenant está activo.
- [ ] UC-02: **Asignación de Contact** — Admin/Editor puede asignar o reasignar un contacto como responsable de la orden.
- [ ] UC-03: **Adición de Items consumidos** — Admin/Editor puede registrar repuestos/insumos consumidos en la orden con cantidad, precio unitario y descripción opcional.
- [ ] UC-04: **Cambio de estado** — Admin/Editor puede transicionar la orden entre estados: `draft → in_progress → completed | cancelled`.

### Roles involucrados

| Rol | Permisos |
|-----|----------|
| Admin | Crear, editar, eliminar, cambiar estado |
| Editor | Crear, editar (no eliminar), cambiar estado |
| Viewer | Solo ver |

---

## Datos

### Tablas

- [x] Tabla nueva: `work_orders` — principal
- [x] Tabla nueva: `work_order_items` — pivote (items consumidos)
- [ ] Tabla modificada: ninguna

### Campos — `work_orders`

| Campo | Tipo | Requerido | Default | Descripción |
|-------|------|-----------|---------|-------------|
| `id` | `uuid PK` | sí | `gen_random_uuid()` | Identificador único |
| `tenant_id` | `uuid FK → tenants` | sí | — | Aislamiento multi-tenant |
| `asset_id` | `uuid FK → assets` | sí | — | Activo al que pertenece la orden |
| `contact_id` | `uuid FK → contacts` | no | `null` | Contacto responsable asignado |
| `code` | `varchar(50)` | sí | — | Código secuencial único por tenant (ej: WO-0001) |
| `title` | `varchar(255)` | sí | — | Título descriptivo de la orden |
| `description` | `text` | no | `null` | Descripción detallada |
| `priority` | `varchar(20)` | no | `'normal'` | `low`, `normal`, `high`, `urgent` |
| `status` | `varchar(20)` | sí | `'draft'` | `draft`, `in_progress`, `completed`, `cancelled` |
| `started_at` | `timestamptz` | no | `null` | Fecha/hora de inicio real |
| `completed_at` | `timestamptz` | no | `null` | Fecha/hora de finalización real |
| `metadata` | `jsonb` | no | `'{}'` | Datos específicos de industria (ej: odómetro, lote, turno) |
| `deleted_at` | `timestamptz` | no | `null` | Soft delete |
| `created_at` | `timestamptz` | no | `now()` | Timestamp |
| `updated_at` | `timestamptz` | no | `now()` | Timestamp |

### Campos — `work_order_items`

| Campo | Tipo | Requerido | Default | Descripción |
|-------|------|-----------|---------|-------------|
| `id` | `uuid PK` | sí | `gen_random_uuid()` | Identificador único |
| `tenant_id` | `uuid FK → tenants` | sí | — | Aislamiento multi-tenant |
| `work_order_id` | `uuid FK → work_orders` | sí | — | Orden padre (cascade delete) |
| `item_id` | `uuid FK → items` | sí | — | Repuesto/insumo consumido |
| `quantity` | `decimal(12,4)` | sí | `1` | Cantidad consumida |
| `unit_price` | `decimal(14,4)` | sí | `0` | Precio unitario al momento de la orden |
| `description` | `text` | no | `null` | Nota opcional sobre este ítem |
| `metadata` | `jsonb` | no | `'{}'` | Datos adicionales (ej: número de serie, lote) |
| `deleted_at` | `timestamptz` | no | `null` | Soft delete |
| `created_at` | `timestamptz` | no | `now()` | Timestamp |
| `updated_at` | `timestamptz` | no | `now()` | Timestamp |

### Índices — `work_orders`

- `(tenant_id)` — obligatorio (performance RLS)
- `(tenant_id, status)` — filtro frecuente por estado
- `UNIQUE (tenant_id, code)` — código único por tenant
- `(tenant_id, asset_id)` — listar órdenes de un activo

### Índices — `work_order_items`

- `(tenant_id)` — obligatorio (performance RLS)
- `(work_order_id)` — join con orden padre
- `(tenant_id, item_id)` — filtro por insumo

### Restricciones de negocio

- Regla 1: El `status` solo puede transicionar en un sentido: `draft → in_progress → completed | cancelled`. No se permite volver a un estado anterior (ej: de `completed` a `draft`).
- Regla 2: Una orden completada debe tener `completed_at` con timestamp. Una orden en progreso debe tener `started_at`.
- Regla 3: `quantity` en `work_order_items` debe ser > 0.

---

## Seguridad

- RLS en tabla `work_orders`: sí — 4 políticas (SELECT, INSERT, UPDATE, DELETE) usando `public.current_tenant_id()`
- RLS en tabla `work_order_items`: sí — 4 políticas usando `public.current_tenant_id()`
- FORCE ROW LEVEL SECURITY: sí (ambas tablas)
- Políticas de Laravel: `WorkOrderPolicy` requerida (admin=full, editor=crear/editar, viewer=solo ver)
- ¿Expone datos cross-tenant? No. RLS aísla por tenant_id en ambas tablas. Toda query pasa por `BelongsToTenant` global scope.

---

## Frontend

- [ ] Filament Resource: `WorkOrderResource` con panel principal y relationship manager para `work_order_items`
- [ ] Tabs en el formulario: "Información general" (campos base), "Items consumidos" (relación manejable)
- [ ] Selects con búsqueda para `asset_id` (AssetResource) y `contact_id` (ContactResource)
- [ ] Badge de estado con colores: `draft` (gray), `in_progress` (warning), `completed` (success), `cancelled` (danger)
- [ ] Acción personalizada "Completar orden" que valida y establece `completed_at`

---

## API (opcional — postergable)

- `GET /api/work-orders` — listar paginado
- `POST /api/work-orders` — crear
- `PUT /api/work-orders/{id}` — actualizar
- `DELETE /api/work-orders/{id}` — eliminar
- `GET /api/work-orders/{id}/items` — listar items de una orden
- `POST /api/work-orders/{id}/items` — agregar item consumido

---

## Tests requeridos

- [ ] TenantIsolationTest para `WorkOrder` — verificar que Tenant A no ve órdenes de Tenant B
- [ ] TenantIsolationTest para `WorkOrderItem` — mismo aislamiento
- [ ] Policy test: Admin puede eliminar, Editor no
- [ ] Feature test: ciclo completo de estados
- [ ] Feature test: restricción de unicidad `(tenant_id, code)`

---

## Dependencias

- Features previos: **Assets**, **Items**, **Contacts** — las 3 tablas existen con sus modelos y RLS. Una orden depende de estas entidades.
- Paquetes nuevos: ninguno

---

## Orden de implementación (FASE 3 — SDLC)

1. Migración `create_work_orders_table` + `create_work_order_items_table` (con RLS e índices)
2. Modelos `WorkOrder` y `WorkOrderItem` (extienden `TenantModel`)
3. `WorkOrderPolicy` (autorización por rol)
4. `CreateWorkOrderRequest` + `UpdateWorkOrderRequest` (validación)
5. `WorkOrderService` (lógica de negocio)
6. `WorkOrderResource` (Filament, con tabs y relationship manager)
7. Tests (aislamiento + políticas + CRUD)

---

## Checklist de aprobación

- [x] Nombre del feature cumple Zero Redundancy — "WorkOrder" es genérico
- [x] No duplica lógica existente — no existe feature equivalente
- [x] El modelo de datos es agnóstico de industria — metadata JSONB para datos específicos
- [x] RLS + FORCE RLS incluidos en la migración — 4 políticas por tabla
- [ ] Este spec fue revisado por John

---

> **Para aprobar:** John escribe "APROBADO" como respuesta a este documento.
> Ninguna otra palabra activa la ejecución.
