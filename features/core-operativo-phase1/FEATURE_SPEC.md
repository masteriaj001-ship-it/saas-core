# FEATURE SPEC — Phase 1 Core Operativo

> Estado: **COMPLETADO** — 2026-08-27
> Tests: 547/547 green (25+ nuevos)

---

## Resumen

Phase 1 Core Operativo implementa 8 sub-módulos para el taller mecánico: Suppliers, Purchase Orders con CMP, Stock Consumption en Work Orders, Workshop Bays, Appointments con calendario, Price Lists, y Low Stock Alerts.

---

## Módulos Implementados

### 1. Suppliers (Proveedores)
- Tabla `suppliers` con `contact_id` 1:1 (contacts tiene NIT, teléfono, dirección)
- SupplierResource Filament CRUD
- SupplierPolicy (viewAny, view, create, update, delete)

### 2. Purchase Orders (Órdenes de Compra)
- Tabla `purchase_orders` + `purchase_order_items`
- Estados: draft → ordered → partial → received, draft/ordered → cancelled
- PurchaseOrderResource con ItemsRelationManager
- Acción "Recibir" con modal de recepción parcial (Repeater con cantidades por línea)
- PurchaseService::receive() con transacciones atómicas
- PurchaseOrderObserver auto-genera código (OC-YYYY-NNNNN)

### 3. Costo Promedio Ponderado (CMP)
- Tabla `item_cost_histories` para trazabilidad histórica
- Campo `average_cost` en items (valor operativo actual)
- CostingService::recalculateAverageCost() con fórmula CMP
- CostingService::getHistoricalCost() para reportes retroactivos

### 4. Stock Consumption on Work Order Complete
- WorkOrderObserver detecta transición a estado Completed
- StockConsumptionService::consumeForWorkOrder() descuenta stock
- Vincula `stock_movement_id` y `unit_cost_at_sale` en WorkOrderItem
- StockConsumptionService::reverseConsumption() revierte al reabrir WO

### 5. Low Stock Alerts
- Artisan command `inventory:check-low-stock` (con filtro --tenant)
- LowStockAlertService::isLowStock() verifica stock ≤ min_stock
- LowStockNotification (queued, mail) a usuarios owner/editor
- StockMovementObserver dispara alerta en cada salida de stock

### 6. Workshop Bays (Bahías de Trabajo)
- Tabla `workshop_bays` con location, code, name, type, is_active
- WorkshopBayResource Filament CRUD
- Tipos: standard, lift, paint, diagnostic

### 7. Appointments (Citas)
- Tabla `appointments` con contact, vehicle, bay, mechanic, status, scheduled_at
- AppointmentResource Filament CRUD
- AppointmentCalendar page con vista de calendario
- Estados: scheduled, confirmed, in_progress, completed, cancelled, no_show
- AppointmentStatus enum con labels en español

### 8. Price Lists (Listas de Precios)
- Tabla `price_lists` + `price_list_items`
- PriceListResource con ItemsRelationManager
- Soporte para lista default (is_default) y precios por volumen (min_quantity)

---

## Tests Nuevos (25+)

| Archivo | Tests | Cobertura |
|---------|-------|-----------|
| SupplierTest | 7 | CRUD, validación, tenant isolation |
| PurchaseOrderTest | 6 | CRUD, cancel, isFullyReceived, receive partial, receive full |
| CostingServiceTest | 4 | First purchase, subsequent purchases, history, historical cost |
| StockConsumptionOnCompleteTest | 3 | Consume on complete, reverse on reopen, link movement |
| StockMovementResourceTest | 4 | Read-only, cannot create, cannot update, tenant isolation |
| CheckLowStockCommandTest | 4 | Command runs, detects low stock, no notification when OK, filter by tenant |
| AppointmentTest | 5 | CRUD, overlap prevention, status transitions, null bay |
| WorkshopBayTest | 5 | CRUD, types, active/inactive, location relationship |

---

## Stack

- Laravel 13.11.2, PHP 8.5, PostgreSQL 16.14
- Filament v5.6.5, PHPUnit 12
- Multi-tenant con RLS + BelongsToTenant trait
- $guarded con R-02 (id, tenant_id, created_at, updated_at, deleted_at)

---

## Desviaciones del Spec Original

- `items.is_active` no implementado (columna no existe, se usa deleted_at)
- `items.minimum_stock` → renombrado a `min_stock` (ya existente)
- Mecánicos en `users` (no `contacts`) para auth en Filament
- Auto-cálculo de totales en PO eliminado (infinite recursion en observer)
- `price_lists.tenant_id` no incluido (lista de precios puede ser global)

---

## Próximos Pasos

- [ ] Fase 2: Integración POS con listas de precios (PriceResolverService)
- [ ] Fase 2: Validación de solapamiento de citas en bahía
- [ ] Fase 3: Reportes de rentabilidad con CMP histórico
- [ ] Fase 3: Exportación de movimientos de stock a Excel
