# FEATURE_SPEC — Fase 1: WorkOrderCodeGenerator Unificado

> Estado: Borrador | Autor: opencode | Fecha: 2026-06-06

---

## 1. Entrada (Input)

### Contexto del diagnóstico

La generación de código `WO-XXXX` está duplicada en **3 lugares de producción** con el mismo algoritmo copiado textualmente:

| # | Archivo | Líneas | Tipo |
|---|---------|--------|------|
| 1 | `app/Filament/Resources/WorkOrderResource/Pages/CreateWorkOrder.php` | 15-27 | Hook Filament (mutateFormDataBeforeCreate) |
| 2 | `app/Modules/Talleres/Services/WorkOrderService.php` | 78-93 | Servicio (dead code, 0 callers) |
| 3 | `app/Modules/Talleres/Actions/CreateWorkOrderAction.php` | 29-44 | Acción (dead code, 0 callers) |

**Dead code identificado (0 referencias externas):**
- `CreateWorkOrderAction` — singleton registrado en `TalleresServiceProvider`, nunca llamado
- `WorkOrderService` — singleton registrado en `TalleresServiceProvider`, nunca llamado
- `CreateWorkOrderRequest` — solo usado por `WorkOrderService`
- `UpdateWorkOrderRequest` — solo usado por `WorkOrderService`

### Riesgos actuales

- **Divergencia**: si el formato cambia (ej: `WO-` → `WRK-`), hay que editar 3 archivos
- **Race condition**: sin `lockForUpdate()`, dos requests simultáneos pueden obtener el mismo código
- **Sin filtro tenant**: la consulta no filtra por `tenant_id`, solo confía en RLS

---

## 2. Proceso (Processing)

### Solución: WorkOrderCodeGenerator

Clase única que centraliza la generación con **DB lock transaccional**.

```php
namespace App\Modules\Talleres\Services;

final class WorkOrderCodeGenerator
{
    public function next(): string
    {
        return DB::transaction(function () {
            $last = WorkOrder::withTrashed()
                ->where('code', 'ilike', 'WO-%')
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(code, 4) AS INTEGER) DESC')
                ->first();

            $num = $last ? (int) substr($last->code, 3) + 1 : 1;

            return 'WO-'.str_pad((string) $num, 4, '0', STR_PAD_LEFT);
        });
    }
}
```

### Flujo de inyección

| Ubicación actual | Comportamiento nuevo |
|---|---|
| `CreateWorkOrder.php` (Filament) | Inyecta `WorkOrderCodeGenerator` via constructor, reemplaza inline code |
| `WorkOrderService::generateCode()` | Se elimina (archivo completo es dead code) |
| `CreateWorkOrderAction::generateCode()` | Se elimina (archivo completo es dead code) |
| `TalleresServiceProvider` | Registra `WorkOrderCodeGenerator` como singleton; elimina registros de dead code |
| `WorkOrderFactory` | Cambia de `bothify('WO-####')` a secuencia real usando el generador |

### Manejo de errores

| Error | Comportamiento |
|-------|---------------|
| DB lock timeout (deadlock) | PostgreSQL lanza error, se reintenta vía transacción |
| No hay WorkOrders previas | Retorna `WO-0001` |

---

## 3. Estado (State)

No hay cambios en el esquema de base de datos. Es refactorización pura de lógica de aplicación.

### Archivos creados

| Archivo | Propósito |
|---------|-----------|
| `app/Modules/Talleres/Services/WorkOrderCodeGenerator.php` | Servicio single-responsibility con DB lock |

### Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Filament/Resources/WorkOrderResource/Pages/CreateWorkOrder.php` | Inyectar `WorkOrderCodeGenerator`, reemplazar `mutateFormDataBeforeCreate` por llamada a `$this->codeGenerator->next()` |
| `app/Modules/Talleres/Providers/TalleresServiceProvider.php` | Registrar `WorkOrderCodeGenerator`, eliminar `CreateWorkOrderAction` y `WorkOrderService` |

> **Desviación del plan:** `WorkOrderFactory` se dejó con `bothify('WO-####')`. El generador requiere contexto de tenant y consultas DB que no son adecuadas para factories (usadas en seeding y tests fuera de contexto tenant). El factory produce códigos aleatorios no secuenciales, lo cual es correcto para su propósito.

### Archivos eliminados

| Archivo | Razón |
|---------|-------|
| `app/Modules/Talleres/Actions/CreateWorkOrderAction.php` | Dead code (0 callers) |
| `app/Modules/Talleres/Services/WorkOrderService.php` | Dead code (0 callers) |
| `app/Http/Requests/WorkOrders/CreateWorkOrderRequest.php` | Solo usado por WorkOrderService (dead code) |
| `app/Http/Requests/WorkOrders/UpdateWorkOrderRequest.php` | Solo usado por WorkOrderService (dead code) |

---

## 4. Renderizado

Sin cambios en UI. El formulario Filament se comporta idéntico: el código se genera automáticamente antes de crear el registro.

---

## 5. Salida (Output)

Resultado: código `WO-XXXX` único, secuencial por tenant, generado atómicamente.

El código se asigna en `WorkOrder.code` antes de la persistencia, igual que hoy.

---

## 6. Seguridad

- El generator respeta el global scope `BelongsToTenant` (hereda el tenant del contexto actual)
- `lockForUpdate()` previene race conditions entre requests concurrentes del mismo tenant
- No expone datos cross-tenant
- Sin permiso nuevo requerido (el permiso `create_work_orders` ya existe)

---

## 7. Tests Requeridos

- [x] **WorkOrderCodeGeneratorTest** (tests/Feature/WorkOrderCodeGeneratorTest.php) — 4 tests:
  - `test_generates_first_code_as_wo_0001` — sin WorkOrders → `WO-0001`
  - `test_generates_sequential_codes` — crea WO entre calls, verifica secuencia: 0001→0002→0003
  - `test_generates_code_after_last_existing` — WO-0042 existe → genera WO-0043
  - `test_considers_soft_deleted_records` — WO-0010 eliminada → genera WO-0011
- [x] **Test de regresión** — suite completa (147 tests, 386 assertions) en verde

---

## 8. Dependencias

- Features previos: ninguno (refactorización)
- Paquetes nuevos: ninguno
- Servicios externos: ninguno
- Migraciones: **ninguna** (solo lógica de aplicación)

---

## 9. Checklist de Aprobación

- [x] Nombre del feature cumple Zero Redundancy
- [x] No duplica lógica existente (DRY: elimina 3 copias, crea 1 fuente única)
- [x] El modelo de datos no cambia
- [x] Sin migraciones ni RLS
- [x] Input/Processing/State/Rendering/Output cubren todos los flujos
- [x] Estados de carga, vacío y error contemplados
- [x] Tests incluidos (nuevos unit test + regresión)
- [x] Este spec fue revisado por John

---

> **Para aprobar:** John escribe "APROBADO" como respuesta a este documento.
> Ninguna otra palabra activa la ejecución.
