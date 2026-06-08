# FEATURE_SPEC — Work Order Fixes Visuales

> Estado: Completo | Autor: opencode | Fecha: 2026-06-06

---

## Descripción

Correcciones visuales y funcionales al módulo Talleres para mejorar la experiencia de creación de órdenes de trabajo y visualización de items.

---

## Fix 1 — ItemsRelationManager: nombre según tipo

**Archivos**:
- `app/Modules/Talleres/Models/WorkOrderItem.php` — `serviceCatalog()` relation (existente)
- `app/Modules/Talleres/Models/ServiceCatalog.php` — `newFactory()` agregado
- `app/Filament/Resources/WorkOrderResource/RelationManagers/ItemsRelationManager.php` — columna calculada

### Cambio

La columna `item.name` se reemplazó por `display_name` calculado:

```php
TextColumn::make('display_name')
    ->label('Insumo / Servicio')
    ->getStateUsing(fn (WorkOrderItem $record): string =>
        match ($record->type->value) {
            'part' => $record->item?->name ?? '—',
            'service', 'labor' => $record->serviceCatalog?->name ?? '—',
            default => '—',
        }
    )
    ->searchable(false),
```

**Clave**: `$record->type` es un objeto `WorkOrderItemTypeEnum`, no un string. Se usa `->value` para comparar.

---

## Fix 2 — Pre-cargar inspección con defaults al crear OT

**Archivo**: `app/Filament/Resources/WorkOrderResource/Pages/CreateWorkOrder.php`

### Cambio

Se agregó `afterCreate()` que itera `config('inspection-defaults.mechanic')` (13 ítems) y crea registros en `inspections()`:

```php
protected function afterCreate(): void
{
    $defaults = config('inspection-defaults.mechanic', []);

    foreach ($defaults as $index => $itemName) {
        $this->record->inspections()->create([
            'item_name' => $itemName,
            'status' => InspectionItemStatusEnum::Ok,
            'sort_order' => $index,
        ]);
    }
}
```

**Importante**: `'status'` debe ser `InspectionItemStatusEnum::Ok` (objeto enum), no el string `'ok'`, por el cast strict en `WorkOrderInspection`.

---

## Fix 3 — Inspecciones en Step 1 del Wizard

**Archivo**: `app/Filament/Resources/WorkOrderResource.php`

### Cambio

La `Section::make('Inspección de Ingreso')` se movió de `step3Schema()` a `step1Schema()`, agregada después de la sección de estado estético del vehículo. Sin cambios funcionales.

---

## Tests

**Archivo**: `tests/Feature/Talleres/WorkOrderFixesTest.php`

| # | Test | Assert |
|---|------|--------|
| 1 | `test_items_relation_shows_service_catalog_name` | Item tipo service/labor muestra nombre del catálogo |
| 2 | `test_inspection_defaults_created_on_work_order_creation` | OT nueva tiene 13 inspecciones pre-cargadas con status=ok |

---

## Estado

- [x] Fix 1: ItemsRelationManager muestra nombre correcto por tipo (part→item.name, service/labor→serviceCatalog.name)
- [x] Fix 2: afterCreate() precarga 13 inspecciones con status=Ok
- [x] Fix 3: Inspección movida a Step 1 del wizard
- [x] 156 tests, 442 assertions — 0 regresiones
