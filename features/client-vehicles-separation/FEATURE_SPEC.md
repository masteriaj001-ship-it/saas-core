# FEATURE SPEC — Separación Assets / ClientVehicles

> Estado: **PENDIENTE** — Fase discovery, spec aprobado, esperando ejecución
> Fecha: 2026-08-21
> Prioridad: Alta (antes del piloto)
> Estimación: 1.5 semanas

---

## Problema Actual

La tabla `assets` mezcla dos entidades con ciclos de vida completamente distintos:

| | Activo del Taller | Vehículo del Cliente |
|---|---|---|
| **Propietario** | El taller | Un contacto/cliente |
| **Ciclo de vida** | Adquisición → mantenimiento → baja | Entrada → servicio → salida |
| **Relaciones** | OTs donde se usó como recurso | OTs donde fue reparado |
| **Kilometraje** | Del activo propio | Del carro del cliente (al ingreso) |

Los campos `owner_contact_id`, `vin`, `plate`, `brand`, `model`, etc. están en `assets` pero solo aplican a vehículos de clientes. Cada feature nueva (historial de servicio, RAG, recordatorios) requiere saber qué tipo es antes de hacer cualquier cosa.

---

## User Story

**Como** taller mecánico multi-tenant,
**Quiero** separar los activos del taller de los vehículos de clientes,
**Para** tener entidades limpias con ciclos de vida propios y facilitar el historial de servicio por vehículo.

**Criterios de éxito:**
- `assets` contiene solo activos del taller (equipos, herramientas, vehículos propios)
- `client_vehicles` contiene vehículos de clientes con su propietario
- `work_orders` apunta a `client_vehicle_id` para el vehículo del cliente
- Historial de kilometraje por entrada al taller
- Búsqueda en OT por cédula del cliente o placa

---

## Schema Changes

### Nueva tabla `client_vehicles`

| Campo | Tipo | Restricciones |
|---|---|---|
| `id` | `uuid` | PK, gen_random_uuid() |
| `tenant_id` | `uuid` | FK → tenants, cascade on delete |
| `owner_contact_id` | `uuid` | FK → contacts, null on delete |
| `plate` | `varchar(20)` | NULLABLE, índice único por tenant |
| `brand` | `varchar(100)` | NULLABLE |
| `model` | `varchar(100)` | NULLABLE |
| `version` | `varchar(100)` | NULLABLE |
| `year` | `integer` | NULLABLE, CHECK >= 1900 |
| `vin` | `varchar(100)` | NULLABLE, índice único por tenant |
| `engine_number` | `varchar(100)` | NULLABLE |
| `color` | `varchar(50)` | NULLABLE |
| `fuel_type` | `varchar(50)` | NULLABLE |
| `vehicle_type` | `varchar(50)` | NULLABLE (sedan, suv, pickup, motorcycle, etc.) |
| `current_mileage` | `integer` | NULLABLE, CHECK >= 0 |
| `notes` | `text` | NULLABLE |
| `metadata` | `jsonb` | DEFAULT '{}' |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

**Índices:**
- `idx_cv_tenant_plate ON client_vehicles (tenant_id, plate) WHERE deleted_at IS NULL`
- `idx_cv_tenant_vin ON client_vehicles (tenant_id, vin) WHERE deleted_at IS NULL`
- `idx_cv_tenant_owner ON client_vehicles (tenant_id, owner_contact_id)`

**RLS:** Políticas de aislamiento por tenant (misma estructura que assets).

### Nueva tabla `vehicle_mileage_logs`

| Campo | Tipo | Restricciones |
|---|---|---|
| `id` | `uuid` | PK, gen_random_uuid() |
| `tenant_id` | `uuid` | FK → tenants, cascade on delete |
| `client_vehicle_id` | `uuid` | FK → client_vehicles, cascade on delete |
| `work_order_id` | `uuid` | FK → work_orders, null on delete |
| `mileage` | `integer` | NOT NULL, CHECK >= 0 |
| `recorded_at` | `timestamp` | NOT NULL |
| `notes` | `text` | NULLABLE |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

**Índices:**
- `idx_vml_vehicle ON vehicle_mileage_logs (tenant_id, client_vehicle_id)`
- `idx_vml_work_order ON vehicle_mileage_logs (tenant_id, work_order_id)`

### Tabla `assets` — columnas a ELIMINAR

| Campo | Razón |
|---|---|
| `plate` | Se mueve a client_vehicles |
| `brand` | Se mueve a client_vehicles |
| `model` | Se mueve a client_vehicles |
| `version` | Se mueve a client_vehicles |
| `year` | Se mueve a client_vehicles |
| `vin` | Se mueve a client_vehicles |
| `engine_number` | Se mueve a client_vehicles |
| `color` | Se mueve a client_vehicles |
| `fuel_type` | Se mueve a client_vehicles |
| `current_mileage` | Se mueve a client_vehicles |
| `owner_contact_id` | Se mueve a client_vehicles |

### Tabla `work_orders` — cambios

| Campo | Cambio |
|---|---|
| `asset_id` | Se renombra a `client_vehicle_id`, FK → client_vehicles |
| `asset_id` (nuevo) | Se agrega como nullable, FK → assets (para activo propio del taller si aplica) |

---

## Estructura de Archivos

```
app/Modules/Talleres/Models/
├── ClientVehicle.php
├── VehicleMileageLog.php
└── (Asset.php se modifica)

app/Filament/Resources/
├── ClientVehicleResource.php
└── (WorkOrderResource.php se modifica)

database/migrations/
├── create_client_vehicles_table.php
├── create_vehicle_mileage_logs_table.php
└── drop_vehicle_columns_from_assets.php

database/factories/
├── ClientVehicleFactory.php
└── (AssetFactory.php se modifica)

tests/
├── ClientVehicleTest.php
└── (tests existentes se migran)
```

---

## Filament UI Changes

### Nuevo: ClientVehicleResource

- **Model label**: 'Vehículo del Cliente'
- **Navigation label**: 'Vehículos'
- **Navigation group**: 'Talleres'
- **Form**: owner_contact_id (Select searchable), plate, brand, model, year, vin, engine_number, color, fuel_type, vehicle_type, current_mileage, notes, metadata
- **Table**: owner.name, plate, brand, model, year, vin, current_mileage, created_at
- **Filters**: vehicle_type, fuel_type

### Modificado: WorkOrderResource

- Campo `asset_id` → `client_vehicle_id` (Select searchable)
- Búsqueda: por cédula del contacto → sus vehículos, o por placa directa
- Inline creation: crea ClientVehicle, no Asset
- Campo `asset_id` se mantiene nullable para activo propio del taller

### Modificado: AssetResource

- Eliminar campos de vehículo del formulario
- Labels: 'Activo' / 'Activos'
- Tipos: equipo, herramienta, vehículo_propio, inmueble

### Eliminado: VehicleFormSchema.php

- No es necesario, los campos van directamente en ClientVehicleResource

---

## Relationships

```
Contact (1) ──→ (N) ClientVehicle
ClientVehicle (1) ──→ (N) WorkOrder
ClientVehicle (1) ──→ (N) VehicleMileageLog
WorkOrder (1) ──→ (N) VehicleMileageLog
Asset (1) ──→ (N) WorkOrder (opcional, activo propio)
```

---

## Order of Execution

| # | Tarea | Dependencia |
|---|---|---|
| 1 | Migraciones: crear tablas nuevas (NO drop aún) | — |
| 2 | Modelo ClientVehicle + relaciones | #1 |
| 3 | Modelo VehicleMileageLog + relaciones | #1 |
| 4 | ClientVehicleFactory | #2 |
| 5 | WorkOrder: agregar client_vehicle_id (mantener asset_id temporal) | #1 |
| 6 | Actions: CreateWorkOrderReceptionAction → ClientVehicle | #2 |
| 7 | Actions: BudgetConversionService → ClientVehicle | #2 |
| 8 | ClientVehicleResource (nuevo) | #2 |
| 9 | WorkOrderResource: asset_id → client_vehicle_id | #5, #8 |
| 10 | AssetResource: limpiar campos de vehículo | — |
| 11 | Tests: migrar AssetVinOwnerTest → ClientVehicleTest | #2, #4 |
| 12 | Tests: migrar VehicleMasterDataTest → ClientVehicleTest | #2, #4 |
| 13 | Tests: migrar referencias en WorkOrder*Test | #5, #9 |
| 14 | Migración de datos: assets vehicle → client_vehicles | #1, #2 |
| 15 | DROP columnas de vehículo en assets | #14 |
| 16 | Limpiar Asset.php: isVehicle(), relación owner | #15 |
| 17 | industry-defaults.php: reemplazar vehículo por activo real | — |
| 18 | Suite completa verde | todo |

---

## Rules

- `work_orders.client_vehicle_id` es el FK principal para el vehículo del cliente
- `work_orders.asset_id` (nuevo) es nullable, para activo propio del taller usado en la OT
- Búsqueda en WorkOrder: por cédula del contacto → sus vehículos, o por placa directa
- Inline creation en OT: crea ClientVehicle, no Asset
- Kilometraje: cada vez que se crea/cierra una OT → registro en vehicle_mileage_logs
- `current_mileage` en client_vehicles = último km registrado
- owner_contact_id en client_vehicles: cambio de dueño = editar el campo

---

## Archivos Afectados (21 archivos)

### Tests (8 archivos, ~45 referencias)
- `AssetTallerTest.php` (11 refs)
- `AssetVinOwnerTest.php` (6 refs)
- `VehicleMasterDataTest.php` (3 refs)
- `WorkOrderTallerTest.php` (2 refs)
- `WorkOrderReceptionTest.php` (1 ref)
- `WorkOrderTest.php` (3 refs)
- `WorkOrderClosureTest.php` (2 refs)
- `WorkOrderClosurePhase2Test.php` (1 ref)
- `WorkOrderPhase3Test.php` (3 refs)
- `WorkOrderWizardTest.php` (2 refs)
- `WorkOrderCodeGeneratorTest.php` (4 refs)
- `WorkOrderFlowTest.php` (7 refs)
- `TenantIsolationTest.php` (2 refs)
- `ContactFlowTest.php` (3 refs)
- `IvaConfigurableTest.php` (2 refs)
- `GenerateInvoiceFromWorkOrderTest.php` (4 refs)
- `WorkOrderE2ETest.php` (2 refs)

### Factories (1 archivo)
- `AssetFactory.php` — state vehicle() se elimina, se crea ClientVehicleFactory

### Seeders (1 archivo)
- `DatabaseSeeder.php` — 3 vehículos de ejemplo migran a client_vehicles

### Actions (2 archivos)
- `CreateWorkOrderReceptionAction.php` — resolveAsset() → resolveClientVehicle()
- `BudgetConversionService.php` — crea Asset → crea ClientVehicle

### Filament (2 archivos)
- `WorkOrderResource.php` — asset_id → client_vehicle_id
- `LatestWorkOrdersTable.php` — select incluye asset_id

### Modelos (2 archivos)
- `Asset.php` — eliminar isVehicle(), relación owner
- `WorkOrder.php` — asset_id → client_vehicle_id

### Migraciones (4 archivos)
- `add_plate_brand_model_year_to_assets.php`
- `add_vin_and_owner_to_assets.php`
- `add_vehicle_detail_fields_to_assets.php`
- `create_work_orders_table.php`

### Config (1 archivo)
- `industry-defaults.php` — reemplazar vehículo de prueba por activo real

---

## Decisions Log

| Fecha | Decisión | Razón |
|---|---|---|
| 2026-08-21 | Opción A: Separar tablas | B y C son parches sobre error de modelo |
| 2026-08-21 | client_vehicles en módulo Talleres | Dependencia con WorkOrders |
| 2026-08-21 | owner_contact_id en client_vehicles | Cambio de dueño = editar campo |
| 2026-08-21 | vehicle_mileage_logs para historial | RAG futuro necesita historial |
| 2026-08-21 | asset_id se mantiene nullable en WorkOrder | Para activo propio del taller |

---

## Status

- [ ] Migraciones
- [ ] Modelos
- [ ] Factory
- [ ] WorkOrder FK
- [ ] Actions
- [ ] Filament Resources
- [ ] Tests migrados
- [ ] Migración de datos
- [ ] DROP columnas
- [ ] Limpiar Asset.php
- [ ] Actualizar industry-defaults
- [ ] Suite verde
