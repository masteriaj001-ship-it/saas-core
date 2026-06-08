# FEATURE_SPEC — Sprint 3b: work_order_inspections

> Estado: Borrador | Fecha: 2026-06-04

---

## 1. Entrada (Input)

Checklist visual del vehículo al momento de la recepción. El mecánico registra el estado de cada ítem.

### Formularios / Componentes Filament

| Componente | Campo | Tipo | Validación |
|------------|-------|------|------------|
| `TextInput::make('item_name')` | `item_name` | `string:max:100` | required |
| `Select::make('status')` | `status` | `InspectionItemStatusEnum` | required |
| `Textarea::make('notes')` | `notes` | `text` | visible solo si status ≠ Ok |
| `Hidden::make('sort_order')` | `sort_order` | `integer` | default auto-incremental |

### Datos de Contexto

- `tenant_id`: resuelto por `TenantManager` automáticamente
- `work_order_id`: OT asociada
- `item_name`: puede venir pre-cargado desde `config/inspection-defaults.php`

### Validaciones Clave

- `status` debe ser uno de: `ok`, `damaged`, `missing`
- `notes` requerido si `status = damaged`

---

## 2. Proceso (Processing)

No requiere servicios o acciones nuevas. Creación directa vía Filament RelationManager (Create/Edit desde UI).

---

## 3. Estado (State)

### Tablas

| Tabla | Operación | RLS |
|-------|-----------|-----|
| `work_order_inspections` | CREATE | ✅ 4 políticas + FORCE |

### Campos

| Campo | Tipo | Requerido | Default | Descripción |
|-------|------|-----------|---------|-------------|
| `id` | `uuid PK` | sí | `gen_random_uuid()` | |
| `tenant_id` | `uuid FK → tenants` | sí | — | inyectado por RLS |
| `work_order_id` | `uuid FK → work_orders ON DELETE CASCADE` | sí | — | OT asociada |
| `item_name` | `varchar(100)` | sí | — | Ej: "Parabrisas", "Espejo izquierdo" |
| `status` | `varchar(20)` | sí | — | `ok` / `damaged` / `missing` |
| `notes` | `text` | no | `null` | Descripción del daño si aplica |
| `photo_path` | `varchar(500)` | no | `null` | Reservado para Sprint 4 (MinIO) |
| `sort_order` | `smallint` | sí | `0` | Orden del checklist |
| `created_at` | `timestamptz` | sí | `now()` | |
| `updated_at` | `timestamptz` | sí | `now()` | |

Sin `deleted_at`.

### Índices

- `(tenant_id)` — `idx_woi_tenant`
- `(tenant_id, work_order_id)` — `idx_woi_work_order`

### Checklist por defecto

`config/inspection-defaults.php` — 13 ítems estándar para taller mecánico.

---

## 4. Renderizado (Rendering)

InspectionsRelationManager en WorkOrderResource — permite Create/Edit.

| Columna | Componente | Descripción |
|---------|-----------|-------------|
| `item_name` | `TextColumn` | Nombre del ítem |
| `status` | `TextColumn::make('status')->badge()` | Color según enum (success/danger/warning) |
| `notes` | `TextColumn::make('notes')->limit(50)` | Descripción del daño |

Ordenado por `sort_order ASC`.

---

## 5. Salida (Output)

- Checklist visual en el detalle de la OT (pestaña "Inspección" en el RelationManager)
- Base para acta de entrega y protección legal del taller

---

## 6. Seguridad

- RLS en tabla nueva: sí (4 políticas estándar + FORCE)
- Sin SoftDeletes
- No expone datos cross-tenant
- `photo_path` reservado (vacío hasta Sprint 4)

---

## 7. Tests (5)

- [ ] `test_work_order_inspection_can_be_created` — campos persisten
- [ ] `test_work_order_inspection_tenant_isolation` — otro tenant no ve la inspección
- [ ] `test_work_order_has_inspections_relation` — `$wo->inspections` ordenada por sort_order
- [ ] `test_inspection_status_enum_has_three_cases` — enum 3 cases exactos
- [ ] `test_inspection_defaults_config_exists` — config retorna array no vacío

---

## 8. Dependencias

- Features previos: Sprint 1 (WorkOrder Core Fields), Sprint 2 (WorkOrderItem type), Sprint 3a (work_order_activities)
- Paquetes nuevos: ninguno
- Servicios externos: ninguno

---

## 9. Checklist de Aprobación

- [ ] Nombre del feature cumple Zero Redundancy
- [ ] No duplica lógica existente
- [ ] El modelo de datos es agnóstico de industria
- [ ] RLS + FORCE RLS incluidos en la migración
- [ ] Sin SoftDeletes
- [ ] 116 tests, 320+ assertions — 0 regresiones
