# FEATURE_SPEC: Inventory Foundation (Phase 1)

## Resumen

Infraestructura base del módulo de inventario: bodegas como entidad propia y trazabilidad absoluta de cada movimiento de stock. Integra descuento/reposición automática en transacciones y órdenes de trabajo.

---

## Stack

| Componente | Versión |
|---|---|
| Laravel | ^13 |
| PostgreSQL | 16 |
| BelongsToTenant | trait existente |
| Auditable | trait existente (spatie/laravel-activitylog) |

---

## Decisiones de diseño

### Warehouse != Location

`Location` existe como "sede física del taller" (dónde opera). `Warehouse` es el almacén/bodega donde se guarda el inventario. Un taller puede tener múltiples bodegas (repuestos, consumibles, insumos generales) aunque tenga una sola sede.

Relación: `warehouse.location_id` nullable FK → `locations` para mapear qué bodega está en qué sede. No obligatorio.

### StockMovement como único source of truth

El campo `stock` en `items` se mantiene como cache/read-model. La verdadera fuente de saldo es la sumatoria de `stock_movements.quantity` por `(item_id, warehouse_id)`. Un Job/Evento sincroniza el cache al crear cada movimiento.

### Movimiento polimórfico

`stock_movements.reference_type` + `reference_id` (morphs) permite rastrear qué originó el movimiento: una transacción, una orden de trabajo, un ajuste manual, una transferencia. Esto da trazabilidad completa.

### Integración Transaction → Stock

- `TransactionService::issue()` (status draft → issued): descuenta stock de cada `TransactionItem` que referencia un `Item` de tipo `spare`, `product` o `raw_material`.
- `TransactionService::cancel()` (status issued → cancelled): repone el stock previamente descontado.
- La transacción DEBE poder emitirse aunque no haya stock suficiente (se registra el movimiento como `exit` con cantidad que puede dejar stock negativo). El control de stock mínimo es alerta, no bloqueo.

### Integración WorkOrder → Stock

- Cuando una `WorkOrder` se marca como `work_done` (cierre), los `WorkOrderItem` de tipo `part` descuentan stock automáticamente.
- (Esto se implementa hasta Fase 4 cuando exista el flujo de cierre completo; en Fase 1 solo está la integración con transacciones.)

---

## Modelos

### Warehouse

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | UUID (PK) | |
| `tenant_id` | UUID (FK) | |
| `location_id` | UUID (FK→locations, nullable) | Sede asociada |
| `code` | string(30) | Código interno único por tenant |
| `name` | string(255) | Nombre de la bodega |
| `address` | text (nullable) | Dirección física |
| `is_default` | boolean | default false — única default por tenant |
| `is_active` | boolean | default true |
| `metadata` | jsonb | default '{}' |
| timestamps + soft deletes | | |

Índices: `(tenant_id)`, `UNIQUE (tenant_id, code)`, `(tenant_id, is_default)`.
RLS: 4 políticas estándar.

### StockMovement

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | UUID (PK) | |
| `tenant_id` | UUID (FK) | |
| `item_id` | UUID (FK→items) | Item movido |
| `warehouse_id` | UUID (FK→warehouses) | Bodega donde ocurre |
| `user_id` | UUID (FK→users, nullable) | Quién ejecutó |
| `movement_type` | string(20) | `entry`, `exit`, `adjustment`, `transfer_in`, `transfer_out` |
| `quantity` | integer | Positivo = entrada, negativo = salida |
| `stock_before` | integer | Stock del item en esa bodega antes del movimiento |
| `stock_after` | integer | Stock después del movimiento |
| `unit_cost` | decimal(12,2) | Costo unitario al momento del movimiento |
| `reference_type` | string(50) | `transaction`, `work_order`, `adjustment`, `transfer` |
| `reference_id` | UUID (nullable) | ID del documento origen |
| `reason` | string(100) | Motivo legible (`Venta #FV-000123`, `Ajuste por daño`) |
| `notes` | text (nullable) | |
| timestamps | | (sin soft deletes — los movimientos no se eliminan) |

Índices: `(tenant_id)`, `(item_id, warehouse_id)`, `(reference_type, reference_id)`, `(created_at)`.
RLS: 4 políticas estándar (solo SELECT/INSERT — los movimientos no se editan ni eliminan).

---

## Enums

### MovementTypeEnum

```php
enum MovementTypeEnum: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
}
```

Reglas:
- `entry` y `exit` se generan automáticamente desde transacciones/WO.
- `adjustment` se usa para correcciones manuales (Fase 2 con InventoryAdjustment).
- `transfer_in` y `transfer_out` se usan en transferencias entre bodegas (Fase 2).

---

## Acciones / Servicios

### AdjustItemStockAction

```php
class AdjustItemStockAction
{
    public function execute(
        Item $item,
        Warehouse $warehouse,
        MovementTypeEnum $type,
        int $quantity,  // siempre positiva; el action pone el signo según $type
        ?string $reason = null,
        ?Model $reference = null,
        ?float $unitCost = null,
    ): StockMovement;
}
```

Es la única forma de crear un StockMovement. Se encarga de:
1. Calcular `stock_before` (sumatoria actual de movimientos).
2. Validar que `quantity > 0`.
3. Determinar signo según `movement_type` (entry/transfer_in → +; exit/transfer_out/adjustment → -).
4. Crear `StockMovement` con todos los campos.
5. Actualizar `Item.stock` (cache) en el mismo `DB::transaction`.

### StockSyncService

```php
class StockSyncService
{
    public function syncItemStock(Item $item): void;
    // Recalcula item.stock = SUM(stock_movements.quantity) agrupado
    
    public function syncTransactionStock(Transaction $transaction): void;
    // Descuenta/repone stock de todos los TransactionItems
}
```

### TransactionStockObserver

```php
// Registrado en TransactionService::issue() y TransactionService::cancel()
// NO como Eloquent observer — se invoca desde el service existente
```

En `TransactionService::issue()`:
```php
// Antes de cambiar status a 'issued'
foreach ($transaction->items as $tItem) {
    if ($tItem->item && in_array($tItem->item->item_type, ['spare', 'product', 'raw_material'])) {
        app(AdjustItemStockAction::class)->execute(
            item: $tItem->item,
            warehouse: $defaultWarehouse,  // o warehouse según la transacción
            type: MovementTypeEnum::Exit,
            quantity: $tItem->quantity,
            reason: "Venta {$transaction->code}",
            reference: $transaction,
            unitCost: $tItem->item->cost,
        );
    }
}
```

En `TransactionService::cancel()`:
```php
// Solo revertir si la transacción estaba 'issued'
foreach ($transaction->items as $tItem) { /* entry en vez de exit */ }
```

---

## API / UI

### Fase 1.1 — WarehouseResource (Filament)

- **Navigation**: Grupo "Inventario", icono `heroicon-o-building-storefront`, sort 3
- **List**: tabla con código, nombre, sede, default, activo, items count
- **Create/Edit**: code, name, location (Select), address, is_default (toggle), is_active (toggle)
- **Delete**: solo si no tiene movimientos asociados (onDelete restrict en FK)
- **Pages**: ListWarehouses, CreateWarehouse, EditWarehouse

### Fase 1.2 — StockMovementsRelationManager en ItemResource

En `EditItem`:
- RelationManager anidado: tabla de movimientos del item
- Columnas: fecha, tipo (badge), bodega, cantidad, stock antes/después, costo, referencia, usuario, razón
- Acciones: ninguna (solo lectura — los movimientos se crean por acciones del sistema)

### Fase 1.3 — Ajuste Rápido de Stock (Action en ItemResource)

Botón "Ajustar Stock" en la tabla del `ItemResource`:
- Modal con: bodega (Select), tipo (entry/exit/adjustment), cantidad, motivo (TextInput)
- Ejecuta `AdjustItemStockAction` en el submit
- Notificación de éxito/error

### Fase 1.4 — Migración Datos Legacy

Seeder/migration para crear `Warehouse` default por tenant y migrar `Item.stock` actual a un `StockMovement` inicial:
- Para cada `Item` con `stock > 0`, crear un movimiento `entry` con `reason: 'Saldo inicial'`, `reference_type: 'migration'`, `created_at` igual al `created_at` del item.
- Se ejecuta UNA VEZ como comando artisan `inventory:migrate-legacy-stock`.

---

## Tests

| Archivo | Tests | Descripción |
|---|---|---|
| `WarehouseAppScopeTest.php` | 7 | CRUD, validación código único, default único, soft delete, tenant isolation scope |
| `WarehouseRlsTest.php` | 4 | RLS: SELECT/INSERT/UPDATE/DELETE cross-tenant |
| `StockMovementAppScopeTest.php` | 5 | Creación vía AdjustItemStockAction, signos, referencias polimórficas, cache sync |
| `StockMovementRlsTest.php` | 3 | RLS: aislamiento cross-tenant, solo SELECT/INSERT |
| `TransactionStockIntegrationTest.php` | 4 | Transaction::issue descuenta, cancel repone, validate movement creation, no descuenta servicios |
| `ItemResourceStockActionTest.php` | 2 | Ajuste rápido desde Filament |
| `LegacyStockMigrationTest.php` | 1 | Seeder migra saldos correctamente |

Total estimado: **26 tests**.

---

## Migraciones

### Crear `warehouses`

```sql
CREATE TABLE warehouses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    location_id UUID REFERENCES locations(id) ON DELETE SET NULL,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    is_default BOOLEAN NOT NULL DEFAULT false,
    is_active BOOLEAN NOT NULL DEFAULT true,
    metadata JSONB NOT NULL DEFAULT '{}',
    deleted_at TIMESTAMP(0),
    created_at TIMESTAMP(0) NOT NULL,
    updated_at TIMESTAMP(0) NOT NULL
);

CREATE INDEX idx_warehouses_tenant ON warehouses(tenant_id);
CREATE UNIQUE INDEX idx_warehouses_tenant_code ON warehouses(tenant_id, code);
CREATE INDEX idx_warehouses_tenant_default ON warehouses(tenant_id, is_default);

ALTER TABLE warehouses ENABLE ROW LEVEL SECURITY;
ALTER TABLE warehouses FORCE ROW LEVEL SECURITY;
-- 4 políticas con current_tenant_id()
```

### Crear `stock_movements`

```sql
CREATE TABLE stock_movements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    item_id UUID NOT NULL REFERENCES items(id) ON DELETE RESTRICT,
    warehouse_id UUID NOT NULL REFERENCES warehouses(id) ON DELETE RESTRICT,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    movement_type VARCHAR(20) NOT NULL CHECK (movement_type IN ('entry','exit','adjustment','transfer_in','transfer_out')),
    quantity INTEGER NOT NULL CHECK (quantity != 0),
    stock_before INTEGER NOT NULL,
    stock_after INTEGER NOT NULL,
    unit_cost DECIMAL(12,2),
    reference_type VARCHAR(50),
    reference_id UUID,
    reason VARCHAR(100) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP(0) NOT NULL,
    updated_at TIMESTAMP(0) NOT NULL
);

CREATE INDEX idx_stock_movements_tenant ON stock_movements(tenant_id);
CREATE INDEX idx_stock_movements_item_warehouse ON stock_movements(item_id, warehouse_id);
CREATE INDEX idx_stock_movements_reference ON stock_movements(reference_type, reference_id);
CREATE INDEX idx_stock_movements_created ON stock_movements(created_at);

ALTER TABLE stock_movements ENABLE ROW LEVEL SECURITY;
ALTER TABLE stock_movements FORCE ROW LEVEL SECURITY;
-- 2 políticas: SELECT + INSERT (UPDATE/DELETE no se permiten)
```

### Agregar `warehouse_id` a `items` (nullable, default warehouse)

```sql
ALTER TABLE items ADD COLUMN default_warehouse_id UUID REFERENCES warehouses(id) ON DELETE SET NULL;
```

---

## DoD (Definition of Done)

- [ ] FEATURE_SPEC.md aprobado (GATE 0)
- [ ] Schema SQL aprobado → migración ejecutada (GATE 2)
- [ ] Warehouse model con BelongsToTenant, HasUuids, SoftDeletes
- [ ] StockMovement model (sin SoftDeletes — solo timestamps)
- [ ] MovementTypeEnum
- [ ] AdjustItemStockAction (único punto de creación de movimientos)
- [ ] StockSyncService (recalcular cache)
- [ ] TransactionService integrado: issue() descuenta, cancel() repone
- [ ] WarehouseResource Filament (List/Create/Edit + Delete con restricción)
- [ ] StockMovements RelationManager en EditItem (solo lectura)
- [ ] Ajuste Rápido Action en ItemResource (modal)
- [ ] Artisan command `inventory:migrate-legacy-stock`
- [ ] 26 tests pasando
- [ ] Suite completa en verde
- [ ] Pint formateado
- [ ] engram.json actualizado
- [ ] Module `inventory` activado en tenant_modules

---

## Pendientes (Fases posteriores)

- InventoryTransfer + InventoryAdjustment (Fase 2)
- PhysicalCount (Fase 3)
- InventoryValuation + KPIs + LowStockAlert (Fase 4)
- Barcode + Import/Export (Fase 5)
- Integración WorkOrder → Stock al cerrar (Fase 4)

---

> **Para aprobar:** John escribe "APROBADO" como respuesta a este documento.
> Ninguna otra palabra activa la ejecución.
