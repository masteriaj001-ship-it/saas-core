# Caja — Fixes Producción 2026-10-01

> Estado: Completo | Fecha: 2026-10-01 | Producción: `saas-core-1pdj.onrender.com`

Cuatro incidentes encadenados en el módulo Caja/Turnos, todos detectados en producción (Render) y verificados con tests de regresión en local. Sin migraciones, sin pérdida de datos.

---

## Fix 1 — `opened_by` null al crear turno desde la lista

**Commit:** `c150db2` — `fix(cashshift): remove CreateAction from list to prevent opened_by null violation`

**Síntoma:** `SQLSTATE[23502]: null value in column "opened_by" of relation "cash_shifts"` al usar Crear en `/cash-shifts`.

**Causa:** `ListCashShifts::getHeaderActions()` registraba `CreateAction::make()`. El form del resource solo pedía `initial_amount` + `status`, sin `opened_by`, burlando `CashShift::openShift()` que lo asigna y valida turno único.

**Cambio:** `getHeaderActions()` retorna `[]`. Los turnos solo se abren desde `CajaPage` → "Abrir Turno" (`CashMovementService::openShift()`).

**Archivos:**
- `app/Filament/Resources/CashShiftResource/Pages/ListCashShifts.php`

---

## Fix 2 — Vista Caja sin estilos + ícono dólar gigante

**Commits:** `f8448d8`, `a43c9b8`, `86e9f12`

**Síntoma:** `/caja` renderizaba texto plano sin estilos; el SVG `currency-dollar` (sin `width`/`height`, solo `viewBox`) se mostraba a 300×150px.

**Causa (doble):**
1. `AdminPanelProvider` no registraba `viteTheme()`, así que el panel nunca cargaba las utilidades Tailwind de las vistas custom (el sidebar sí se veía: CSS core de Filament por su propio pipeline).
2. Los `<x-heroicon-o-*>` son poco fiables bajo el middleware `DisableBladeIconComponents`.

**Cambios:**
- `resources/css/filament/admin/theme.css` (nuevo): importa el tema Filament + variante `dark` por clase (panel con dark forzado) + `@source` a `resources/` y `app/`.
- `AdminPanelProvider`: `->viteTheme('resources/css/filament/admin/theme.css')`.
- `vite.config.js`: tema agregado a los inputs.
- `Dockerfile` (stage `assets`): `COPY --from=vendor /app/vendor/filament` antes de `npm run build` — `vendor/` está excluido del build context (`.dockerignore`), sin esto el `@import` falla solo en Docker (build local pasaba).
- `caja.blade.php`: 6 `<x-heroicon-o-*>` → `<x-filament::icon :icon="Heroicon::...">` (pipeline propio de Filament).

**Lección:** lo que compila en local no garantiza el build Docker si hay `@import` relativos a `vendor/`. El stage `assets` no tiene `vendor/`.

**Intento fallido intermedio (`a43c9b8`, revertido):** apuntar `viteTheme` a `app.css` rompió TODO el CSS porque el tema Vite **reemplaza** al default de Filament en vez de sumarse. El tema dedicado es obligatorio.

---

## Fix 3 — `number_format()` TypeError al abrir turno

**Commit:** `3b19882` — `fix(caja): cast decimal amounts to float before number_format`

**Síntoma:** `number_format(): Argument #1 ($num) must be of type int|float, string given` en `CajaPage.php:96` tras crear el turno (el turno sí quedaba abierto en BD).

**Causa:** el cast `decimal:2` de Laravel devuelve **string** al leer de BD; `CajaPage.php` tiene `declare(strict_types=1)`, así que no hay coerción. Los tests no lo atraparon: ninguno ejercitaba `loadShiftData()`.

**Cambio:** `(float)` antes de `number_format` para `initial_amount`.

**Test:** `tests/Feature/Caja/CajaPageTest.php` — turno real + `fresh()` (decimal llega como string, igual que en prod) + `loadShiftData()`. Sin el fix reproduce el `TypeError` exacto; con el fix pasa.

---

## Fix 4 — `totalSales must return a relationship instance`

**Commit:** `55a397b` — `fix(cashshift): resolve computed columns via getStateUsing to avoid relationship clash`

**Síntoma:** `LogicException` en `GET /cash-shifts` (lista) — y el detalle hubiera caído igual.

**Causa:** el modelo define **métodos** `totalSales()/totalExpenses()/netAmount(): float`, y la tabla/detalle los referencian como atributos. Eloquent interpreta `$record->totalSales` como **relación** y exige un `Relation` (el log mostraba el `select sum(amount)... where type='sale'` ejecutándose como "relación").

**Cambios:**
- `CashShiftResource.php`: `getStateUsing(fn (CashShift $record): float => ...)` en las 3 columnas + removido `sortable()` (habría roto con error SQL: la columna no existe en BD).
- `ViewCashShift.php`: `getStateUsing()` en las 3 entries + fix extra: `TextEntry::make('cashMovements_count')->count()` — `count()` no existe en `TextEntry` v5; reemplazado por `getStateUsing(...->cashMovements()->count())`.

**Test:** `tests/Feature/Caja/CashShiftTableTest.php` — render de lista (`assertCanSeeTableRecords`) + detalle con turno real. Sin el fix reproduce el `LogicException`; con el fix pasa.

---

## Estado de tests

Suite Caja: **16/16** (`CashShiftTest` + `CajaIntegrationTest` + `CajaPageTest` + `CashShiftTableTest`).

## Pendiente (no bloqueante)

Barrido preventivo `(float)` en `number_format` con valores `decimal` en archivos `strict_types`: `PaymentMethodsBreakdown.php:45`, `FinancialStatsOverview.php:44-62`, `WorkOrderResource.php:700`, `EscPosService.php:29-36`, `CheckOverdueCreditsCommand.php:46`, `PlanResource.php:98`, `OverdueCreditNotification.php:33`. Mismo patrón de bug que Fix 3.
