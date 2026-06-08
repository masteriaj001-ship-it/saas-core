# Vehicle Master Data

## Modelo

Asset representa cualquier activo del taller utilizando `asset_type` como discriminador.

```
Asset
 ├─ vehicle   ← Vehículo (master data completo)
 ├─ equipment → Equipamiento / Maquinaria
 ├─ phones    → Celulares
 ├─ computers → Cómputo
 └─ space     → Espacio / Infraestructura
```

## Tabla `assets` — Columnas Vehicle

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `name` | VARCHAR(255) | Nombre / alias del vehículo |
| `plate` | VARCHAR(20) | Placa (unique por tenant) |
| `brand` | VARCHAR(100) | Marca |
| `model` | VARCHAR(100) | Modelo |
| `year` | SMALLINT | Año (>= 1900) |
| `version` | VARCHAR(100) | Versión / trim level |
| `vin` | VARCHAR(100) | VIN (unique por tenant) |
| `engine_number` | VARCHAR(100) | Número de motor |
| `current_mileage` | INTEGER | Kilometraje actual (>= 0) |
| `fuel_type` | VARCHAR(50) | Tipo combustible (FuelTypeEnum) |
| `color` | VARCHAR(50) | Color |
| `vehicle_type` | VARCHAR(50) | Tipo vehiculo (VehicleTypeEnum) |
| `owner_contact_id` | UUID (FK) | Propietario (Contact, ON DELETE SET NULL) |

## Relaciones

- `owner()` → `Contact` via `owner_contact_id`
- `workOrders()` → `WorkOrder` (HasMany)

## Helper

```php
$asset->isVehicle(): bool  // asset_type === 'vehicle'
```

## Enums

### FuelTypeEnum

| Case | Label | Color |
|------|-------|-------|
| Gasoline | Gasolina | warning |
| Diesel | Diésel | danger |
| Hybrid | Híbrido | success |
| Electric | Eléctrico | info |
| Gas | Gas | gray |
| Other | Otro | gray |

### VehicleTypeEnum (existente)

Sedán, Motocicleta, Pickup, SUV, Van/Furgón, Camión, Otro

## Formularios

### VehicleFormSchema (`app/Filament/Schemas/VehicleFormSchema.php`)

Schema reusable usado por:

- **AssetResource** — formulario completo de creación/edición de activos (sección vehicle visible por defecto)
- **WorkOrderResource** — `createOptionForm` para creación inline de vehículos desde la OT

Secciones:
1. Información General (name, plate, vehicle_type)
2. Datos Técnicos (brand, model, version, year, vin, engine_number, color, fuel_type, current_mileage)
3. Propietario (owner_contact_id → filtrado por contacts de tipo client)

## Migración

`2026_06_04_000007_add_vehicle_detail_fields_to_assets`

- Renombra `owner_id` → `owner_contact_id`
- Agrega: version, engine_number, current_mileage, fuel_type, color
- CHECK constraints: `current_mileage >= 0`, `year >= 1900`
- Data migration: `'vehicles'` → `'vehicle'` (normalización legacy)

## Tests

`tests/Feature/Talleres/VehicleMasterDataTest.php` — 10 tests:
- Creación con todos los campos vehicle
- Relación owner()
- Valores FuelTypeEnum
- Helper isVehicle()
- Tenant isolation
- FK ON DELETE SET NULL
- Validación kilometraje no negativo
- Validación año mínimo 1900
- Version/engine_number nullables
- Creación inline desde WorkOrder

## Roadmap

- Agregar `vehicle_images` como caso de uso de WorkOrderMedia
- Agregar `service_history` (vinculado a WorkOrder)
- Agregar `warranty` fields (warranty_start, warranty_end, warranty_mileage)
- Agregar `technical_inspections` recurrente
- Agregar `insurance` fields
