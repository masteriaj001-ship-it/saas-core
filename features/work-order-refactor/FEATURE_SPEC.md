# FEATURE SPEC — Refactor de Órdenes de Trabajo

> Estado: **PENDIENTE** — Spec cerrado con decisiones finales
> Fecha: 2026-08-21
> Prioridad: Módulo central del sistema
> Dependencia: client-vehicles-separation (asset_id → client_vehicle_id)

---

## Lo que está bien y NO se toca

- Flujo de estados robusto (18 estados) — refleja realidad operativa
- Cierre con checklist + fotos + firma — diferenciador
- Selector de items que diferencia repuesto vs servicio
- Relación con contacto, mecánico, asesor — modelo correcto
- WorkOrderActivity como historial de cambios

---

## Campos Finales (limpios)

### Campos que QUEDAN

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `client_vehicle_id` | uuid | SÍ | Reemplaza asset_id |
| `contact_id` | uuid | NO | Se vincula al vehículo |
| `client_report` | text | NO | Reemplaza service_description |
| `internal_notes` | text | NO | Reemplaza description |
| `mileage_km` | integer | NO | Snapshot ingreso → vehicle_mileage_logs |
| `fuel_level` | varchar | NO | Inspección de ingreso |
| `battery_level` | varchar | NO | Inspección de ingreso |
| `aesthetic_notes` | text | NO | Inspección de ingreso |
| `mechanic_id` | uuid | NO | Asignación |
| `advisor_id` | uuid | NO | Asignación |
| `priority` | varchar | SÍ | Control |
| `status` | varchar | SÍ | Control |
| `diagnosis_summary` | text | NO | Diagnóstico |
| `approval_channel` | varchar | NO | Aprobación |
| `approval_at` | timestamp | NO | Aprobación |
| `qc_passed` | boolean | NO | Control de calidad |
| `qc_notes` | text | NO | Control de calidad |
| `delivery_at` | timestamp | NO | Entrega |
| `completed_at` | timestamp | NO | Cierre |
| `signature_hash` | text | NO | Firma cliente |
| `signed_at` | timestamp | NO | Firma cliente |
| `closure_notes` | text | NO | Cierre |
| `metadata` | jsonb | SÍ | General |
| `settings` | jsonb | SÍ | Configuración |

### Campos NUEVOS

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `estimated_completion_at` | timestamp | Calculado al asignar items |
| `actual_started_at` | timestamp | Cuando pasa a InProgress |
| `actual_completed_at` | timestamp | Cuando pasa a WorkDone |

### Campos ELIMINADOS

| Campo | Razón |
|-------|-------|
| `reception_notes` | Redundante con client_report |
| `description` | Renombrado a internal_notes |
| `service_description` | Renombrado a client_report |
| `location_id` | Sin uso, multi-sede es roadmap |
| `asset_id` | Reemplazado por client_vehicle_id |

---

## Creación Inline de Cliente

### Formulario inline contacto

| Campo | Estado |
|-------|--------|
| nombre | ✅ Existe |
| teléfono | ✅ Existe |
| document_number | **AGREGAR** (cédula/NIT) |
| email | **AGREGAR** (para notificaciones futuras) |

---

## Creación Inline de Vehículo

### Flujo post-refactor

```
1. Seleccionar contacto primero (o crearlo inline)
2. Campo vehículo se filtra automáticamente por owner_contact_id
   └── Solo muestra vehículos del cliente seleccionado
3. Si no tiene vehículos → crear inline:
   ├── plate (obligatorio)
   ├── brand, model, year
   ├── color, vin (opcionales)
   └── owner_contact_id se asigna automáticamente del paso 1
```

### Búsqueda alternativa: por placa directa

```
Si la placa existe en otro contacto → alerta:
"Este vehículo está registrado a nombre de [nombre]. ¿Continuar?"
```

### Formulario inline vehículo

| Campo | Tipo | Requerido |
|-------|------|-----------|
| plate | TextInput | SÍ |
| brand | TextInput | NO |
| model | TextInput | NO |
| year | TextInput | NO |
| color | TextInput | NO |
| vin | TextInput | NO |

---

## Inspección de Ingreso Obligatoria

### Antes de Draft → Received

| Elemento | Descripción |
|----------|-------------|
| Checklist visual | Carrocería, vidrios, llantas, interiores |
| Nivel de combustible | Select (E, 1/4, 1/2, 3/4, F) |
| Objetos de valor declarados | Textarea |
| Fotos de ingreso | Mínimo 4: frente, atrás, lado izquierdo, lado derecho |
| Firma/confirmación del cliente | Firma digital o PIN |

---

## Firma del Cliente

### Opciones (configurable por tenant)

| Opción | Prioridad | Descripción |
|--------|-----------|-------------|
| Firma digital en pantalla/tablet | Principal | El cliente firma en el dispositivo |
| PIN de 4 dígitos por WhatsApp | Secundario | Envío automático |
| SMS | Fallback | Solo si no hay WhatsApp |

---

## Notificaciones Automáticas

### Por cambio de estado

| Estado | Mensaje | Canal |
|--------|---------|-------|
| Received | "Tu vehículo [placa] ingresó al taller" | WhatsApp |
| Quoted | "Tu presupuesto está listo, revísalo aquí: [link]" | WhatsApp |
| WaitingApproval | "Requiere tu aprobación" | WhatsApp |
| InProgress | "Comenzamos a trabajar en tu vehículo" | WhatsApp |
| WaitingParts | "Esperando repuestos, te avisamos cuando lleguen" | WhatsApp |
| WorkDone | "Tu vehículo está listo para recoger" | WhatsApp |

---

## Tiempo Estimado vs Real

### WorkOrder — campos nuevos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `estimated_completion_at` | timestamp | Calculado al asignar items |
| `actual_started_at` | timestamp | Cuando pasa a InProgress |
| `actual_completed_at` | timestamp | Cuando pasa a WorkDone |

### WorkOrderItem — campos nuevos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `estimated_minutes` | integer | Heredado de ServiceCatalog |
| `actual_minutes` | integer | Registrado al completar el item |

### Dashboard mecánico

| Indicador | Descripción |
|-----------|-------------|
| Verde | OT a tiempo |
| Amarillo | Por vencer |
| Rojo | Atrasada |

---

## Gestión de Compras (WaitingParts)

### Tabla nueva: `work_order_purchase_requests`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid | PK |
| `tenant_id` | uuid | FK → tenants |
| `work_order_id` | uuid | FK → work_orders |
| `item_id` | uuid | FK → items (nullable) |
| `description` | text | Descripción libre |
| `supplier_name` | varchar | Nombre del proveedor |
| `requested_at` | timestamp | Cuándo se pidió |
| `expected_at` | timestamp | Cuándo se espera |
| `received_at` | timestamp | Cuándo llegó |
| `unit_cost` | decimal | Costo unitario |

### Flujo

1. Mecánico registra solicitud de repuesto
2. Sistema cambia OT a WaitingParts
3. Al registrar recepción del repuesto → automático a InProgress

---

## Cambios en Modelo de Datos

### Tabla `work_orders` — cambios

| Campo | Estado actual | Cambio |
|-------|---------------|--------|
| `asset_id` | FK NOT NULL | Cambiar a `client_vehicle_id` (FK → client_vehicles) |
| `client_report` | NO existe | AGREGAR (reemplaza service_description) |
| `internal_notes` | NO existe | AGREGAR (reemplaza description) |
| `estimated_completion_at` | NO existe | AGREGAR (nullable) |
| `actual_started_at` | NO existe | AGREGAR (nullable) |
| `actual_completed_at` | NO existe | AGREGAR (nullable) |
| `reception_notes` | text | ELIMINAR |
| `description` | text | ELIMINAR (reemplazado por internal_notes) |
| `service_description` | text | ELIMINAR (reemplazado por client_report) |
| `location_id` | uuid | ELIMINAR |

### Tabla `work_order_items` — cambios

| Campo | Estado actual | Cambio |
|-------|---------------|--------|
| `estimated_minutes` | NO existe | AGREGAR (nullable) |
| `actual_minutes` | NO existe | AGREGAR (nullable) |

### Tabla nueva: `work_order_purchase_requests`

Ver spec arriba.

---

## Cambios en Código

### WorkOrderResource.php

| Cambio | Descripción |
|--------|-------------|
| asset_id → client_vehicle_id | FK a client_vehicles |
| Formulario | Renombrar campos, eliminar redundantes |
| Creación inline contacto | Agregar document_number, email |
| Creación inline vehículo | Crear ClientVehicle, filtrar por contacto |
| Wizard responsive | Desktop: formulario completo / Móvil: wizard |
| Inspección obligatoria | Validación antes de Draft → Received |

### WorkOrder.php (Model)

| Cambio | Descripción |
|--------|-------------|
| Relación | clientVehicle() en vez de asset() |
| Fillable | Actualizar campos |
| Campos | estimated_completion_at, actual_started_at, actual_completed_at |

### WorkOrderItem.php (Model)

| Cambio | Descripción |
|--------|-------------|
| Campos | estimated_minutes, actual_minutes |

### WorkOrderClosureService.php

| Cambio | Descripción |
|--------|-------------|
| Firma | Alternativas: firma digital, PIN WhatsApp, SMS |
| Validación | Aceptar cualquiera de las 3 opciones |

### Nuevos archivos

| Archivo | Descripción |
|---------|-------------|
| `WorkOrderPurchaseRequest.php` | Model |
| `PurchaseRequestRelationManager.php` | Filament RM |
| `WorkOrderNotificationService.php` | Notificaciones WhatsApp |

---

## Order of Execution

### CRÍTICO (antes del piloto)

| # | Tarea | Dependencia |
|---|---|---|
| 1 | Migración: asset_id → client_vehicle_id | client-vehicles-separation |
| 2 | Migración: renombrar campos (client_report, internal_notes) | — |
| 3 | Migración: eliminar location_id, reception_notes | — |
| 4 | Model: WorkOrder → relación clientVehicle | #1 |
| 5 | Filament: WorkOrderResource → campos limpios | #2, #3, #4 |
| 6 | Filament: Creación inline contacto → agregar document_number, email | — |
| 7 | Filament: Creación inline vehículo → crear ClientVehicle | #4 |
| 8 | Inspección de ingreso obligatoria | — |
| 9 | Tests: migrar referencias a asset_id | #1, #4, #5 |

### IMPORTANTE (primera semana post-piloto)

| # | Tarea | Dependencia |
|---|---|---|
| 10 | Notificaciones WhatsApp por estado | — |
| 11 | Firma en pantalla vs SMS | — |
| 12 | Wizard responsive (móvil) | — |

### ROADMAP (cuando haya 3+ tenants)

| # | Tarea | Dependencia |
|---|---|---|
| 13 | Tiempo estimado vs real | — |
| 14 | WorkOrderPurchaseRequest | — |
| 15 | Dashboard mecánico (semáforo) | #13 |

---

## Archivos Afectados

### Migraciones (5 archivos)
- `refactor_work_order_fields.php` (renombrar, eliminar, agregar)
- `refactor_work_order_to_client_vehicle.php`
- `add_timing_fields_to_work_orders.php`
- `add_timing_fields_to_work_order_items.php`
- `create_work_order_purchase_requests.php`

### Models (3 archivos)
- `WorkOrder.php`
- `WorkOrderItem.php`
- `WorkOrderPurchaseRequest.php` (nuevo)

### Services (2 archivos)
- `WorkOrderClosureService.php`
- `WorkOrderNotificationService.php` (nuevo)

### Filament (3 archivos)
- `WorkOrderResource.php`
- `InspectionsRelationManager.php`
- `PurchaseRequestRelationManager.php` (nuevo)

### Tests (~24 archivos)
- Todos los tests de WorkOrder que referencian asset_id, service_description, description

---

## Rules

- 18 estados NO se reducen — reflejan realidad operativa
- Inspección de ingreso obligatoria antes de Received
- Firma: firma digital > PIN WhatsApp > SMS (en ese orden)
- Notificaciones por WhatsApp como canal principal
- Tiempo estimado se calcula de ServiceCatalog.estimated_minutes
- WaitingParts registra solicitud de compra con proveedor
- Wizard solo en móvil, escritorio mantiene formulario completo
- Creación de vehículo filtra por contacto seleccionado
- Si placa pertenece a otro contacto → alerta
