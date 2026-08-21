# FEATURE SPEC — Refactor de Presupuestos

> Estado: **PENDIENTE** — Spec cerrado, esperando ejecución
> Fecha: 2026-08-21
> Dependencia: client-vehicles-separation (debe ir primero)

---

## Problema Actual

1. **Cliente como texto plano**: `contact_name`, `contact_phone`, `contact_email` no son FK
2. **Vehículo como JSONB**: `vehicle_data` no está vinculado a ClientVehicle
3. **Items sin catálogo**: Texto libre sin referencia a ServiceCatalog o Item
4. **Conversión automática**: Al aprobar se crea OT sin confirmación

---

## User Story

**Como** taller mecánico,
**Quiero** crear presupuestos vinculados a clientes y vehículos reales,
**Para** que al convertir a OT todo esté conectado al historial.

**Criterios de éxito:**
- Buscar cliente por cédula/nombre, crear inline si no existe
- Buscar vehículo por placa, crear inline si no existe
- Items con selector unificado (servicios, repuestos, manual)
- Conversión manual a OT (no automática)

---

## Formulario Crear Presupuesto — Spec Definitivo

### Sección 1: CLIENTE

**Campos:**

| Campo | Tipo | Requerido | Comportamiento |
|-------|------|-----------|----------------|
| `contact_id` | Select searchable | SI | FK a contacts |
| contact_name | TextInput | SI (inline) | Solo al crear nuevo |
| contact_phone | TextInput | NO (inline) | Solo al crear nuevo |
| contact_email | TextInput | NO (inline) | Solo al crear nuevo |
| contact_document_number | TextInput | NO (inline) | Cedula/RIF |

**Logica:**
- Select busca por `name`, `document_number`, `phone` (ilike)
- Al seleccionar precarga telefono y email (readonly)
- Si no encuentra muestra formulario inline para crear
- Al crear inline crea Contact y selecciona automaticamente

### Seccion 2: VEHICULO

**Campos:**

| Campo | Tipo | Requerido | Comportamiento |
|-------|------|-----------|----------------|
| `client_vehicle_id` | Select searchable | SI | FK a client_vehicles |
| vehicle_plate | TextInput | SI (inline) | Solo al crear nuevo |
| vehicle_brand | TextInput | NO (inline) | Solo al crear nuevo |
| vehicle_model | TextInput | NO (inline) | Solo al crear nuevo |
| vehicle_year | TextInput | NO (inline) | Solo al crear nuevo |
| vehicle_color | TextInput | NO (inline) | Solo al crear nuevo |
| vehicle_vin | TextInput | NO (inline) | Solo al crear nuevo |
| current_mileage | Numeric | NO | Se registra en mileage_logs |

**Logica:**
- Select busca por `plate`, `brand`, `model` (ilike)
- Al seleccionar precarga marca, modelo, anio, color (readonly)
- Si no encuentra muestra formulario inline para crear
- Al crear inline crea ClientVehicle y selecciona automaticamente
- Kilometraje se registra en `vehicle_mileage_logs` al guardar

### Seccion 3: ITEMS

**Campos por fila (Repeater):**

| Campo | Tipo | Requerido | Comportamiento |
|-------|------|-----------|----------------|
| `type` | Select | SI | 'service', 'spare', 'manual' |
| `service_catalog_id` | Select searchable | Condicional | Si type = 'service' |
| `item_id` | Select searchable | Condicional | Si type = 'spare' |
| `manual_description` | TextInput | Condicional | Si type = 'manual' |
| `quantity` | Numeric | SI | Default 1 |
| `unit_price` | Numeric | SI | editable (precio base del catalogo) |
| `discount` | Numeric | NO | Default 0 |
| `subtotal` | Numeric | Calculado | quantity x unit_price - discount |

**Logica por tipo:**

| Tipo | Selector | Precio | Stock |
|------|----------|--------|-------|
| **Servicio** | ServiceCatalog (name) | base_price (editable) | N/A |
| **Repuesto** | Item where item_type IN ('spare','product') | price (editable) | Muestra stock |
| **Manual** | Texto libre | Libre | N/A |

**Footer:**
- `subtotal`: Suma de subtotales
- `discount_total`: Suma de descuentos
- `tax_total`: IVA si aplica (configurable por tenant)
- `grand_total`: subtotal - discount_total + tax_total

### Seccion 4: NOTAS

| Campo | Tipo | Requerido |
|-------|------|-----------|
| `notes` | Textarea | NO |

### Seccion 5: RESUMEN (solo en vista/edicion)

Muestra estado, codigo, fecha de creacion.

---

## Cambios en Modelo de Datos

### Tabla `budgets` — cambios

| Campo | Estado actual | Cambio |
|-------|---------------|--------|
| `contact_id` | NO existe | AGREGAR (FK a contacts, nullable) |
| `contact_name` | varchar | MANTENER (para borradores sin contacto) |
| `contact_phone` | varchar | MANTENER |
| `contact_email` | varchar | MANTENER |
| `client_vehicle_id` | NO existe | AGREGAR (FK a client_vehicles, nullable) |
| `vehicle_data` | jsonb | ELIMINAR |
| `status` | varchar | MANTENER |

### Tabla `budget_items` — cambios

| Campo | Estado actual | Cambio |
|-------|---------------|--------|
| `type` | NO existe | AGREGAR ('service', 'spare', 'manual') |
| `service_catalog_id` | NO existe | AGREGAR (FK a service_catalog, nullable) |
| `item_id` | NO existe | AGREGAR (FK a items, nullable) |
| `description` | text | MANTENER (para tipo 'manual') |

---

## Cambios en Codigo

### BudgetResource.php

| Seccion | Cambio |
|---------|--------|
| Datos del Cliente | Select searchable + inline creation |
| Vehiculo | Select searchable + inline creation |
| Items | Repeater con selector unificado por tipo |
| vehicle_data | ELIMINAR |

### Budget.php (Model)

| Campo | Cambio |
|-------|--------|
| `$fillable` | Agregar contact_id, client_vehicle_id |
| `$fillable` | Eliminar vehicle_data |
| Relacion | Agregar contact(), clientVehicle() |

### BudgetConversionService.php

| Cambio | Descripcion |
|--------|-------------|
| Ya no crea Asset | Usa client_vehicle_id existente |
| Ya no crea Contact | Usa contact_id existente |
| Convierte items | Mapea service_catalog_id e item_id a WorkOrderItem |

### BudgetObserver.php

| Cambio | Descripcion |
|--------|-------------|
| ELIMINAR | Trigger automatico de conversion |

### ViewBudget.php

| Cambio | Descripcion |
|--------|-------------|
| Agregar boton | "Convertir a OT" (visible en status Approved) |
| Confirmacion | Modal de confirmacion antes de convertir |

---

## Estructura de Archivos

```
app/Filament/Resources/
├── BudgetResource.php  (MODIFICAR)
└── BudgetResource/Pages/
    ├── CreateBudget.php  (MODIFICAR)
    ├── EditBudget.php  (MODIFICAR)
    └── ViewBudget.php  (MODIFICAR)

app/Modules/Budget/Models/
├── Budget.php  (MODIFICAR)
└── BudgetItem.php  (MODIFICAR)

app/Modules/Budget/Services/
└── BudgetConversionService.php  (MODIFICAR)

app/Modules/Budget/Observers/
└── BudgetObserver.php  (MODIFICAR - eliminar trigger)

database/migrations/
└── refactor_budget_contacts_vehicles.php  (NUEVO)
```

---

## Order of Execution

| # | Tarea | Dependencia |
|---|---|---|
| 1 | Migracion: agregar contact_id, client_vehicle_id a budgets | client-vehicles-separation |
| 2 | Migracion: agregar type, service_catalog_id, item_id a budget_items | — |
| 3 | Migracion: migrar vehicle_data a client_vehicle_id | #1 |
| 4 | Model: Budget relationships contact, clientVehicle | #1 |
| 5 | Model: BudgetItem relationship serviceCatalog, item | #2 |
| 6 | BudgetResource: formulario con selects unificados | #4, #5 |
| 7 | BudgetConversionService: usar contact_id y client_vehicle_id | #4 |
| 8 | BudgetObserver: ELIMINAR trigger automatico | — |
| 9 | ViewBudget: boton "Convertir a OT" manual | #7 |
| 10 | Tests: migrar tests existentes | todo |
| 11 | Tests: test de conversion manual | #7, #9 |
| 12 | Suite completa verde | todo |

---

## Rules

- `contact_id` es nullable para permitir borradores sin cliente asignado
- `client_vehicle_id` es nullable para permitir borradores sin vehiculo
- Al aprobar: solo cambia estado, NO crea OT
- Conversion a OT: manual via boton con confirmacion
- Items: selector unificado con texto libre como escape
- Si servicio/item no esta en catalogo → tipo "manual"
