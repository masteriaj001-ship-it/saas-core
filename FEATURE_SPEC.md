# FEATURE SPEC — Vertical Talleres Mecánicos (Fase 1)

> Estado: Borrador — Pendiente de aprobación del Arquitecto
> Fecha: 2026-06-03

---

## User Story

**Como** taller mecánico multi-tenant,
**Quiero** registrar vehículos y órdenes de servicio simples,
**Para** gestionar el mantenimiento y las reparaciones de los vehículos de mis clientes.

**Criterios de éxito:**
- Un taller puede registrar vehículos con placa, modelo y metadatos
- Un taller puede crear órdenes de servicio asociadas a un vehículo
- Ningún taller ve datos de otro taller
- La placa es única por taller (índice compuesto `tenant_id` + `plate`)

---

## Schema Design

### Tabla `assets` (existente — extender)

| Campo | Tipo | Restricciones |
|---|---|---|
| `id` | `uuid` | PK, `gen_random_uuid()` |
| `tenant_id` | `uuid` | NOT NULL, FK → `tenants.id` |
| `plate` | `varchar(20)` | NOT NULL |
| `model` | `varchar(100)` | NOT NULL |
| `asset_type` | `varchar(50)` | NOT NULL, DEFAULT `'vehicles'` |
| `metadata` | `jsonb` | NULLABLE, datos específicos del taller |
| `created_at` | `timestamptz` | — |
| `updated_at` | `timestamptz` | — |
| `deleted_at` | `timestamptz` | NULLABLE (soft delete) |
| `brand` | `varchar(100)` | NULLABLE |
| `year` | `integer` | NULLABLE |

**Índices:**
- `UNIQUE INDEX idx_assets_tenant_plate ON assets (tenant_id, plate)`
- `INDEX idx_assets_tenant ON assets (tenant_id)`

### Tabla `work_orders` (existente — extender)

| Campo | Tipo | Restricciones |
|---|---|---|
| `id` | `uuid` | PK, `gen_random_uuid()` |
| `tenant_id` | `uuid` | NOT NULL, FK → `tenants.id` |
| `asset_id` | `uuid` | NOT NULL, FK → `assets.id` |
| `service_description` | `text` | NOT NULL, descripción del servicio |
| `status` | `varchar(20)` | NOT NULL, DEFAULT `'pending'` |
| `created_at` | `timestamptz` | — |
| `updated_at` | `timestamptz` | — |
| `deleted_at` | `timestamptz` | NULLABLE (soft delete) |

**Índices:**
- `INDEX idx_work_orders_tenant ON work_orders (tenant_id)`
- `INDEX idx_work_orders_asset ON work_orders (asset_id)`

### Migración específica

1. Migración para añadir índice único compuesto en `assets`:
   ```sql
   CREATE UNIQUE INDEX IF NOT EXISTS idx_assets_tenant_plate
   ON assets (tenant_id, plate)
   WHERE deleted_at IS NULL;
   ```

2. Migración para añadir columna `brand` y `year` a `assets` (nullable).

3. Migración para añadir columna `service_description` a `work_orders` (si no existe).

---

## Filament UI Plan

### AssetResource
- **Campos del formulario:**
  - `plate` (TextInput, required, unique per tenant)
  - `brand` (TextInput, nullable)
  - `model` (TextInput, required)
  - `year` (TextInput / numeric, nullable)
  - `metadata` (KeyValue o Json, opcional)
- **Listado:**
  - Columnas: plate, brand, model, year, created_at
  - Búsqueda por plate, brand, model
  - Filtro por brand

### WorkOrderResource
- **Campos del formulario:**
  - `asset_id` (Select → assets del tenant)
  - `service_description` (Textarea, required)
  - `status` (Select: pending, in_progress, completed, cancelled)
- **Listado:**
  - Columnas: id (código), asset.plate, service_description (truncado), status, created_at
  - Filtro por status

---

## Security Plan

### RLS (PostgreSQL)
- Política existente en `assets` y `work_orders`:
  ```sql
  CREATE POLICY tenant_isolation ON assets
  USING (tenant_id = current_setting('app.current_tenant_id')::uuid);
  ```
- `FORCE ROW LEVEL SECURITY` ya activo en ambas tablas.

### Global Scope (Laravel)
- Trait `BelongsToTenant` ya aplicado en `Asset` y `WorkOrder` models.
- `tenant_id` se inyecta automáticamente en `creating` event.
- `$guarded` incluye `id`, `tenant_id`, `created_at`, `updated_at`, `deleted_at`.

### Policy (Laravel)
- `WorkOrderPolicy` existente: admin/editor pueden crear/editar, viewer solo lectura.
- `AssetPolicy`: misma estructura.

### Validación del índice compuesto
- `plate` debe ser único por `tenant_id`. La validación se hace con `Rule::unique('assets', 'plate')->where('tenant_id', tenant()->id)` en `FormRequest`.

---

## Testing Plan

### Tests mínimos requeridos

| ID | Test | Tipo |
|----|------|------|
| T1 | Admin puede crear un asset con plate, brand, model, year | Feature |
| T2 | Admin no puede crear asset con plate duplicado (mismo tenant) | Feature |
| T3 | Admin puede crear asset con plate igual en distinto tenant | Feature |
| T4 | Admin puede crear work_order con service_description | Feature |
| T5 | Admin no puede ver assets de otro tenant | Feature (TenantIsolation) |
| T6 | Superadmin puede ver todos los assets | Feature (GlobalScope bypass) |
| T7 | Índice compuesto `(tenant_id, plate)` funciona correctamente | Migration |

### Esquema de tests
```php
// tests/Feature/Talleres/AssetTest.php
// tests/Feature/Talleres/WorkOrderTest.php
// tests/Feature/Security/TallerTenantIsolationTest.php
```

---

## Plan de implementación (orden estricto TDD)

1. **Tests** — Escribir T1-T7 (fallan primero)
2. **Docs** — Actualizar `docs/PROJECT_STATE.md`, `docs/LEARNING_GUIDE.md`
3. **Code** — Migraciones → Modelos → Policies → FormRequests → Services → Filament Resources
4. **Report** — Suite completa en verde + cobertura
5. **Update** — Este FEATURE_SPEC actualizado con desviaciones del plan

---

*Pendiente de aprobación. John debe escribir "APROBADO" para activar GATE 1.*
