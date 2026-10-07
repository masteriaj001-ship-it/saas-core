# Fase 2 — Correctitud UI/Tablas 2026-10-01

> Estado: Completo | Fecha: 2026-10-01

Segunda fase del diagnóstico integral: bugs que revientan tablas y formularios del panel. Todo con TDD (test RED visto fallar antes de cada fix). Sin migraciones, sin pérdida de datos.

---

## 2.1 Función global inexistente + observer muerto

**Síntoma:** `Call to undefined function App\Filament\Resources\formatClientVehicleLabel()` al listar WorkOrders con vehículo sin placa o sin vehículo.

**Causa:** `WorkOrderResource.php:737` llamaba la función global; solo existe el método estático `WorkOrderResource::formatClientVehicleLabel()` (`:824`, exige `ClientVehicle` no-nulo).

**Cambio (`app/Filament/Resources/WorkOrderResource.php`):**
```php
->getStateUsing(fn (WorkOrder $record): string => $record->clientVehicle?->plate ?? ($record->clientVehicle ? static::formatClientVehicleLabel($record->clientVehicle) : '—')),
```

**Observer muerto:** eliminado `app/Modules/Talleres/Observers/WorkOrderUpdateObserver.php` — declaraba `class WorkOrderObserver` duplicada con métodos vacíos y cero referencias. El observer real (`Observers/WorkOrderObserver.php`, registrado en `TalleresServiceProvider.php:44`) queda como único. Sin este archivo se elimina el riesgo de que el autoloader optimizado de producción cargue la clase equivocada.

**Test:** `tests/Feature/Talleres/WorkOrderTableTest.php` — render de lista con WO sin vehículo + búsqueda global. RED→GREEN.

---

## 2.2 Crear transacción bloqueada desde el panel

**Síntoma:** al crear transacción vía UI: `"The estado field is required."` — imposible crear transacciones desde el panel.

**Causa:** `Select status` con `required() + disabled()` sin valor: Filament valida el campo aunque esté deshabilitado.

**Cambio (`app/Filament/Resources/TransactionResource.php`):** removido `->required()` de `status`. El servidor siempre fuerza `draft` en `CreateTransaction::handleRecordCreation()`, así que el required sobraba.

**Aclaración importante (falso positivo del diagnóstico):** los totales `disabled()` sin `dehydrated()` están BIEN así — el servicio los recalcula al crear (`recalculateFromItems`) y en edición no deben sobrescribirse con valores stale de pantalla. No se tocaron.

**Test:** `test_can_create_transaction_from_ui` en `TransactionTest.php` — fill + create + `assertHasNoFormErrors` + `status=draft` en BD. RED→GREEN.

---

## 2.3 Sortables verificados + bug real en el buscador

**Veredicto con tests:** los `sortable()` sobre relaciones (`openedBy.name`, `contact.name`) y conteos (`items_count`) **funcionan en Filament v5** — 4 tests de ordenamiento en verde sin cambios. Hallazgo del diagnóstico descartado con evidencia; no se tocó nada.

**Bug real encontrado en el mismo barrido:** el buscador global de WorkOrders reventaba con `SQLSTATE[42703]: column "clientVehicle" does not exist` — `TextColumn::make('clientVehicle')` (nombre pelado + `getStateUsing`) no le da columna al buscador.

**Cambio:** `TextColumn::make('clientVehicle.plate')` — el search usa EXISTS sobre la relación (igual que `mechanic.name`); el display sigue por `getStateUsing`. Test `test_search_does_not_error` RED→GREEN.

---

## 2.4 N+1 en tablas principales

**Cambios (`getEloquentQuery` + `with()`):**
- `WorkOrderResource`: `->with(['mechanic', 'clientVehicle'])`.
- `InvoiceResource`: `->with(['contact', 'workOrder'])`.
- `StockMovementResource`: nuevo `getEloquentQuery()` con `->with(['item', 'warehouse', 'user'])` (no existía).

**Tests:** `test_table_query_eager_loads_relations` en `WorkOrderTableTest`, `InvoiceModelTest`, `StockMovementResourceTest` (assert sobre `getEagerLoads()`). RED→GREEN.

---

## Estado de tests (cierre de fase)

- Talleres + Transactions + Budget: **169/169**.
- Facturación + Inventario + Caja: **186/186**.
- Pint: limpio (revertidos archivos ajenos preexistentes que Pint siempre toca).

## Lecciones

1. En Filament v5, una columna con nombre que no es atributo ni relación válida rompe render, search u orden — probar las tres operaciones por tabla nueva.
2. `required() + disabled()` sin valor bloquea el submit: los campos display-only no llevan `required()`.
3. Archivos con clases duplicadas son una bomba con `composer install --optimize-autoloader` en producción.
4. Verificar hallazgos de auditoría con tests antes de cambiar código: 2 de 6 candidatos de esta fase eran falsos positivos (sortables, totales disabled).
