# FEATURE SPEC — Separación ClientVehicles / Assets

> **Estado:** COMPLETED  
> **Commit:** `0d10e3b`  
> **Fecha:** 2026-08-21  
> **Suite:** 474/474 verde

## Resumen

Separación de vehículos de clientes (`client_vehicles`) de activos del taller (`assets`). La tabla `assets` ahora solo contiene equipos/herramientas del taller. Los vehículos que entran a reparar viven en `client_vehicles` con relación al contacto propietario.

## Modelo de datos

### Tabla `client_vehicles`
| Campo | Tipo | Descripción |
|---|---|---|
| id | uuid PK | |
| tenant_id | uuid FK | tenants NOT NULL |
| owner_contact_id | uuid FK | contacts NOT NULL |
| plate | varchar(20) | NOT NULL, unique per tenant |
| brand | varchar(100) | nullable |
| model | varchar(100) | nullable |
| version | varchar(100) | nullable |
| year | smallint | nullable |
| vin | varchar(50) | nullable, unique per tenant |
| engine_number | varchar(50) | nullable |
| color | varchar(50) | nullable |
| fuel_type | varchar(30) | nullable (gasoline/diesel/electric/hybrid/gas/other) |
| vehicle_type | varchar(30) | nullable (sedan/suv/pickup/van/motorcycle/truck/other) |
| current_mileage | integer | nullable |
| notes | text | nullable |
| metadata | jsonb | NOT NULL DEFAULT '{}' |
| timestamps | | |
| soft_deletes | | |

### Tabla `vehicle_mileage_logs`
| Campo | Tipo | Descripción |
|---|---|---|
| id | uuid PK | |
| tenant_id | uuid FK | tenants NOT NULL |
| client_vehicle_id | uuid FK | client_vehicles NOT NULL |
| work_order_id | uuid FK | work_orders nullable |
| mileage | integer | NOT NULL |
| recorded_at | timestamp | NOT NULL |
| notes | text | nullable |
| timestamps | | |

### Cambios en `work_orders`
- `client_vehicle_id` uuid FK → client_vehicles (nullable temporal, luego NOT NULL)
- `asset_id` se mantiene nullable para activos propios del taller

### Cambios en `assets`
- Eliminadas columnas: plate, brand, model, version, year, vin, engine_number, color, fuel_type, vehicle_type, current_mileage, owner_contact_id
- Eliminados índices y constraints relacionados

## Reglas de negocio

| Regla | Descripción | Estado |
|---|---|---|
| R-01 | Un vehículo pertenece a un contacto (owner_contact_id obligatorio) | ✅ |
| R-02 | La placa es obligatoria y única por tenant | ✅ |
| R-03 | Al crear una OT, el vehículo debe existir o crearse inline | ✅ |
| R-04 | Al seleccionar contacto en OT → filtrar vehículos por owner_contact_id | ✅ |
| R-05 | Búsqueda alternativa por placa directa sin filtro de contacto | ✅ |
| R-07 | Al crear/cerrar una OT → registrar mileage_km en vehicle_mileage_logs | ✅ |
| R-08 | current_mileage en client_vehicles = último valor de mileage_logs | ✅ |
| R-09 | RLS: tenant_id en client_vehicles y vehicle_mileage_logs | ✅ |
| R-10 | Asset con asset_type='vehicle' → migrado a client_vehicles, columnas eliminadas | ✅ |

## Archivos creados

| Archivo | Descripción |
|---|---|
| `app/Modules/Talleres/Models/ClientVehicle.php` | Modelo con relationships, casts, scopes |
| `app/Modules/Talleres/Models/VehicleMileageLog.php` | Modelo de historial de kilometraje |
| `app/Filament/Resources/ClientVehicleResource.php` | CRUD Filament completo |
| `database/factories/ClientVehicleFactory.php` | Factory con states sedan/suv/pickup/motorcycle |
| `database/factories/VehicleMileageLogFactory.php` | Factory con state forWorkOrder |
| `database/migrations/2026_08_21_190001_create_client_vehicles_table.php` | Tabla + RLS |
| `database/migrations/2026_08_21_190002_create_vehicle_mileage_logs_table.php` | Tabla + RLS |
| `database/migrations/2026_08_21_190003_add_client_vehicle_id_to_work_orders.php` | FK en work_orders |
| `database/migrations/2026_08_21_190004_migrate_vehicle_data_to_client_vehicles.php` | Data migration |
| `database/migrations/2026_08_21_190005_drop_vehicle_columns_from_assets.php` | Limpieza de assets |
| `database/migrations/2026_08_21_190007_add_soft_deletes_to_vehicle_mileage_logs.php` | Soft deletes |

## Archivos modificados

| Archivo | Cambios |
|---|---|
| `app/Modules/Talleres/Models/WorkOrder.php` | client_vehicle_id en fillable + BelongsTo relationship |
| `app/Modules/Talleres/Models/Asset.php` | Eliminados isVehicle(), owner(), campos de vehículo |
| `app/Filament/Resources/WorkOrderResource.php` | Select client_vehicle_id con inline create |
| `database/seeders/DatabaseSeeder.php` | 5 vehículos + 19 OTs con client_vehicle_id |
| `database/factories/AssetFactory.php` | Eliminado state vehicle() |
| 17 archivos de tests | Referencias migradas de asset_id a client_vehicle_id |

## Migración de datos

1. `client_vehicles` se creó vacía
2. Vehículos de `assets` (asset_type='vehicle') se copiaron a `client_vehicles`
3. `work_orders.asset_id` se actualizó para apuntar a `client_vehicles.id`
4. Columnas de vehículo se eliminaron de `assets`

## Tests

- 15 tests migrados de AssetVinOwnerTest + VehicleMasterDataTest
- 1 nuevo test de integración
- Suite completa: 474/474 verde
