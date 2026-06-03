# FEATURE SPEC — VIN + Owner en Assets

> Estado: **ACTIVO** — GATE 1 superado
> Fecha: 2026-06-03

---

## User Story

**Como** taller mecánico multi-tenant,
**Quiero** registrar el número VIN (chasis) y el propietario de cada vehículo,
**Para** tener trazabilidad completa del vehículo y su dueño.

**Criterios de éxito:**
- El VIN es único por tenant (índice compuesto `tenant_id` + `vin` con soft delete)
- El VIN puede repetirse entre distintos tenants
- El propietario se modela como FK → `contacts` (reutilizando entidad `Contact`)
- `color` se almacena en `metadata` JSONB (cosmético, no necesita índice)

---

## Schema Changes

### Tabla `assets` — columnas nuevas

| Campo | Tipo | Restricciones |
|---|---|---|
| `vin` | `varchar(100)` | NULLABLE |
| `owner_id` | `uuid` | NULLABLE, FK → `contacts.id` |

**Índices nuevos:**
- `INDEX idx_assets_tenant_vin  ON assets (tenant_id, vin)  WHERE deleted_at IS NULL`
- `INDEX idx_assets_owner       ON assets (tenant_id, owner_id)`

### Nota de arquitectura

`vin` es columna directa (como `plate`), no viola CLAUDE.md porque la regla de abstracción multi-industria aplica a **nombres de modelos/tablas**, no a columnas. `color` va en metadata JSONB (cosmético).

---

## Filament UI Changes

### AssetResource

| Elemento | Cambio |
|---|---|
| `getModelLabel()` | `'Vehículo'` |
| `getPluralModelLabel()` | `'Vehículos'` |
| Form field: `vin` | `TextInput` después de `plate`, con `unique(tenant_id, vin, ignoreRecord)` |
| Form field: `owner_id` | `Select` con `relationship('owner', 'name')` |
| Form field: `color` | NO como columna — va dentro del `KeyValue` de `metadata` |
| Table column: `vin` | `TextColumn` searchable |
| Table column: `owner.name` | `TextColumn` |

---

## Testing Plan

### Tests nuevos en `tests/Feature/Talleres/AssetTallerTest.php`

| ID | Test | Asserts clave |
|---|---|---|
| T8 | Crear asset con `vin` y `owner_id` | Columnas en DB, relación `owner()` retorna `Contact` |
| T9 | `vin` único por tenant (mismo tenant, mismo VIN → excepción) | `QueryException` por unique |
| T10 | Mismo `vin` permitido en distinto tenant | Cross-tenant OK |
| T11 | `owner` relationship retorna `Contact` correcto | `$asset->owner->is($owner)` |

---

## Plan de implementación (orden estricto SDD)

1. **Tests** — Escribir T8-T11 (FALLAN primero) ✅
2. **Docs** — FEATURE_SPEC.md actualizado
3. **Code** — Migración → Modelo → Resource → Tests pasan
4. **Report** — Suite completa en verde + PROJECT_STATE.md
