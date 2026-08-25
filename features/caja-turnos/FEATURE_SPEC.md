# FEATURE_SPEC.md — Caja con Turnos

## Resumen
Módulo de caja con gestión de turnos para talleres automotrices. Permite abrir/cerrar turnos, registrar ventas, gastos y reembolsos, y generar reportes de actividad por turno.

## Estado
- **Implementado:** ✅ Completo
- **Tests:** 13 tests, 16 assertions — todos pasando
- **Fecha:** 2026-08-25

## Data Model

### `cash_shifts`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | uuid PK | Identificador único |
| tenant_id | uuid FK → tenants | Aislamiento multi-tenant |
| opened_by | uuid FK → users | Usuario que abrió el turno |
| closed_by | uuid FK → users (nullable) | Usuario que cerró el turno |
| opened_at | timestamp | Fecha/hora de apertura |
| closed_at | timestamp (nullable) | Fecha/hora de cierre |
| initial_amount | decimal:2 | Efectivo inicial al abrir |
| expected_cash | decimal:2 | Efectivo esperado (inicial + ventas - gastos) |
| actual_cash | decimal:2 (nullable) | Efectivo contado al cerrar |
| difference | decimal:2 (nullable) | Diferencia: actual - expected |
| status | enum: open\|closed | Estado del turno |
| notes | text (nullable) | Notas de cierre |
| metadata | json (nullable) | Datos adicionales |

### `cash_movements`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | uuid PK | Identificador único |
| tenant_id | uuid FK → tenants | Aislamiento multi-tenant |
| shift_id | uuid FK → cash_shifts (nullable) | Turno asociado |
| work_order_id | uuid FK → work_orders (nullable) | Orden de trabajo asociada |
| invoice_id | uuid FK → invoices (nullable) | Factura asociada |
| type | enum: sale\|expense\|income\|refund | Tipo de movimiento |
| payment_method | enum: cash\|card\|transfer\|check\|credit | Método de pago |
| amount | decimal:2 | Monto del movimiento |
| description | string | Descripción del movimiento |
| created_by | uuid FK → users | Usuario que registró |
| metadata | json (nullable) | Datos adicionales |

## Architecture

### Models
- `App\Modules\Caja\Models\CashShift` — Modelo principal del turno
- `App\Modules\Caja\Models\CashMovement` — Movimientos de caja

### Services
- `App\Modules\Caja\Services\CashMovementService` — Lógica de negocio:
  - `recordSale(Invoice)` — Registra venta automática desde factura confirmada
  - `recordRefund(Invoice)` — Registra reembolso automático desde factura cancelada
  - `openShift(User, float)` — Abre turno + crea movimiento income
  - `closeShift(User, float, string)` — Cierra turno + crea movimiento expense

### Exceptions
- `App\Modules\Caja\Exceptions\TurnoCerradoException` — Excepción para turnos cerrados

### Filament Resources
- `CashShiftResource` — Lista de turnos con filtros y vista detallada
- `CajaPage` — Dashboard interactivo para gestión de turno actual

## UI Components

### Dashboard (`/admin/caja`)
1. **Header** — Estado del turno (abierto/cerrado), tiempo transcurrido
2. **Cards de resumen** — Ventas, gastos, neto, monto inicial
3. **Desglose por método** — Efectivo, tarjeta, transferencia
4. **Registrar gasto** — Formulario inline para gastos manuales
5. **Cerrar turno** — Formulario con conteo de efectivo y cálculo de diferencia
6. **Tabla de movimientos** — Lista cronológica con badges de tipo

### Lista de Turnos (`/admin/cash-shifts`)
- Tabla con columnas: apertura, cierre, abierto por, montos, estado
- Filtros por estado (abierto/cerrado)
- Acción de vista detallada

### Vista de Turno (`/admin/cash-shifts/{id}`)
- Infolist con datos del turno, resumen, cierre y estadísticas

## Business Rules
1. Solo un turno abierto por tenant a la vez
2. No se pueden reabrir turnos cerrados
3. La diferencia se calcula automáticamente: efectivo contado - efectivo esperado
4. Cada venta confirmada crea un movimiento `sale` automáticamente
5. Cada factura cancelada crea un movimiento `refund` automáticamente
6. Los gastos manuales se registran como movimiento `expense`
7. El monto inicial se registra como movimiento `income`
8. El efectivo contado al cierre se registra como movimiento `expense`

## Security
- `$guarded` incluye `id`, `tenant_id`, `created_at`, `updated_at` (R-02 compliance)
- RLS activo en PostgreSQL + trait `BelongsToTenant`
- `tenant_id` inyectado vía trait, nunca vía input

## Tests
| Test | Descripción |
|------|-------------|
| `test_puede_abrir_turno_con_monto_inicial` | Abre turno con monto |
| `test_no_puede_abrir_dos_turnos_simultaneos` | Validación de turno único |
| `test_turno_cerrado_no_puede_reabrirse` | Impedir reapertura |
| `test_calcula_diferencia_al_cierre_correctamente` | Cálculo de diferencia |
| `test_rls_turno_no_visible_en_otro_tenant` | Aislamiento multi-tenant |
| `test_factura_confirmada_crea_movimiento_automatico` | Venta automática |
| `test_factura_cancelada_crea_reembolso` | Reembolso automático |
| `test_venta_sin_turno_abierto_lanza_excepcion` | Excepción sin turno |
| `test_reembolso_sin_turno_abierto_lanza_excepcion` | Excepción sin turno |
| `test_cierre_calcula_diferencia_correctamente` | Diferencia con ventas |
| `test_open_shift_crea_income_movement` | Movimiento income al abrir |
| `test_close_shift_crea_expense_movement` | Movimiento expense al cerrar |
| `test_rls_cash_shift_no_visible_en_otro_tenant` | RLS cross-tenant |
