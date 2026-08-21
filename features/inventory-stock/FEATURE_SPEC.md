# FEATURE SPEC — Inventario / Stock Inteligente

> Estado: **PENDIENTE** — Spec cerrado, esperando ejecución
> Fecha: 2026-08-21
> Prioridad: Crítica para piloto

---

## Problema Actual

1. **Stock fantasma**: El campo `stock` existe en Item pero nunca se descuenta al usar repuestos en OTs
2. **Servicios duplicados**: Item (tipo service) y ServiceCatalog son dos fuentes de verdad
3. **UI fragmentada**: ItemsRelationManager solo muestra Items, no ServiceCatalog
4. **Trigger incorrecto**: No hay lógica de descuento de stock en ningún flujo

---

## User Story

**Como** taller mecánico multi-tenant,
**Quiero** un inventario que se actualice al facturar,
**Para** saber cuántos repuestos me quedan sin procesos manuales.

**Criterios de éxito:**
- Stock se descuenta al facturar (no al agregar a OT)
- Servicios y repuestos en un solo selector en la OT
- Cada tenant puede decidir si usa inventario o no
- Tres orígenes de repuesto: stock propio, compra externa, cliente trae

---

## Decisiones Clave

| Decisión | Resolución | Razón |
|----------|------------|-------|
| ¿Stock opcional por tenant? | SÍ | No todos los talleres manejan inventario |
| ¿Cuándo se descuenta stock? | Al facturar | Evita productos en limbo si se cancela OT |
| ¿Múltiples bodegas? | NO (una sola) | Multi-bodega es roadmap |
| ¿Servicios en Item o ServiceCatalog? | ServiceCatalog | Item queda solo para productos físicos |
| ¿UI unificada en OT? | SÍ | Mecánico ve todo en un selector |

---

## Modelo de Datos

### Tabla `items` — cambios

| Campo | Estado | Acción |
|---|---|---|
| `item_type` | spare, product, service, raw_material | ELIMINAR service |
| `stock` | global | MANTENER (se descuenta al facturar) |
| `min_stock` | global | MANTENER |

**Valores válidos de `item_type` después del refactor:**
- `spare` → Repuesto
- `product` → Producto
- `raw_material` → Materia prima

### Tabla `service_catalog` (ya existe)

Campos: `name`, `description`, `base_price`, `estimated_minutes`, `is_active`

**Es la fuente de verdad para servicios.**

### Tabla `work_order_items` — cambios

| Campo | Tipo | Descripción |
|---|---|---|
| `item_id` | uuid, nullable | FK → items (repuestos/productos) |
| `service_catalog_id` | uuid, nullable | FK → service_catalog (servicios) |
| `origin` | varchar | 'stock', 'external', 'client' |
| `unit_price` | decimal | Precio editable al momento de agregar |

**Constraint:** `item_id` OR `service_catalog_id` (no ambos, no ninguno)

### Tabla `stock_movements` — cambios

| Campo | Tipo | Descripción |
|---|---|---|
| `work_order_id` | uuid, nullable | FK → work_orders |
| `invoice_id` | uuid, nullable | FK → invoices |
| `movement_type` | varchar | 'out' al facturar, 'in' al cancelar factura |

---

## Triggers

### Facturar OT → Descontar stock

```
Invoice::created
  └── Por cada WorkOrderItem con item_id (spare/product)
      └── AdjustItemStockAction::execute(
            item: $item,
            warehouse: $warehouse,
            movementType: MovementTypeEnum::Exit,
            quantity: $workOrderItem->quantity,
            reference: $invoice,
            notes: "OT {$workOrder->code}"
          )
```

### Cancelar factura → Revertir stock

```
Invoice::cancelled
  └── StockMovement reverso
      └── AdjustItemStockAction::execute(
            item: $item,
            warehouse: $warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: $workOrderItem->quantity,
            reference: $invoice,
            reason: 'invoice_cancelled'
          )
```

---

## UI Changes

### WorkOrderResource → ItemsRelationManager unificado

**Selector con tabs:**

| Tab | Fuente | Comportamiento |
|-----|--------|----------------|
| Servicios | ServiceCatalog | Precio base editable, sin stock |
| Repuestos | Item (spare/product) | Muestra stock disponible |
| Externo | Input manual | Precio libre, sin descuento |

**Flujo al seleccionar repuesto desde stock:**
1. Muestra stock disponible
2. Si stock = 0 → sugiere "Agregar como externo"
3. Al agregar → reserva visualmente (sin descontar)

**Flujo al seleccionar repuesto externo/cliente:**
1. Input para nombre del repuesto
2. Input para precio (editable)
3. Checkbox "Traído por cliente" (sin costo)

### ItemResource → Eliminar tipo service

```php
// ANTES
'options' => [
    'spare' => 'Repuesto',
    'product' => 'Producto',
    'service' => 'Servicio',      // ← ELIMINAR
    'raw_material' => 'Materia prima',
]

// DESPUÉS
'options' => [
    'spare' => 'Repuesto',
    'product' => 'Producto',
    'raw_material' => 'Materia prima',
]
```

### ServiceCatalogResource → Ya existe, no cambia

---

## Estructura de Archivos

```
app/Modules/Inventario/Actions/
└── InvoiceStockSyncAction.php  (NUEVO)

app/Listeners/
└── SyncStockOnInvoice.php  (NUEVO)

app/Providers/
└── EventServiceProvider.php  (MODIFICAR - registrar listener)

app/Filament/Resources/WorkOrderResource/RelationManagers/
└── ItemsRelationManager.php  (MODIFICAR - UI unificada)

app/Filament/Resources/ItemResource.php  (MODIFICAR - eliminar tipo service)

database/migrations/
└── update_work_order_items_origin.php  (NUEVO)
```

---

## Order of Execution

| # | Tarea | Dependencia |
|---|---|---|
| 1 | Migración: agregar `origin` a work_order_items | — |
| 2 | Migración: eliminar items con item_type='service' | #3 |
| 3 | Migración: migrar servicios a service_catalog | — |
| 4 | Model: WorkOrderItem → relationship unificada | #1 |
| 5 | Action: InvoiceStockSyncAction | — |
| 6 | Listener: SyncStockOnInvoice | #5 |
| 7 | EventServiceProvider: registrar listener | #6 |
| 8 | Filament: ItemsRelationManager unificado | #4 |
| 9 | Filament: ItemResource → eliminar tipo service | #2 |
| 10 | Tests: migrar tests de servicios | #2, #3 |
| 11 | Tests: test de descuento al facturar | #5, #6 |
| 12 | Suite completa verde | todo |

---

## Archivos Afectados

### Migraciones (2 archivos)
- `update_work_order_items_origin.php`
- `migrate_services_to_service_catalog.php`

### Actions (1 archivo nuevo)
- `InvoiceStockSyncAction.php`

### Listeners (1 archivo nuevo)
- `SyncStockOnInvoice.php`

### Providers (1 archivo)
- `EventServiceProvider.php`

### Filament (2 archivos)
- `ItemsRelationManager.php`
- `ItemResource.php`

### Models (1 archivo)
- `WorkOrderItem.php`

### Tests (~5 archivos)
- Tests existentes de Items
- Tests de WorkOrderItems
- Tests nuevos de descuento al facturar

---

## Rules

- Stock se descuenta SOLO al facturar, no al agregar a OT
- Si factura se anula → reversa completa de stock
- Cada tenant decide si usa inventario (campo en settings)
- ServiceCatalog es fuente de verdad para servicios
- Item solo para productos físicos (spare, product, raw_material)
- UI unificada en WorkOrder: servicios + repuestos en un solo selector
- Repuesto externo/cliente: sin descuento, precio libre

---

## Status

- [ ] Migraciones
- [ ] InvoiceStockSyncAction
- [ ] Listener
- [ ] EventServiceProvider
- [ ] ItemsRelationManager unificado
- [ ] ItemResource (eliminar service)
- [ ] WorkOrderItem (origin)
- [ ] Tests
- [ ] Suite verde
