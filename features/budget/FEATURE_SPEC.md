# FEATURE_SPEC: Budget (Cotizaciones)

## Resumen

Modelo independiente de cotizaciones (Budget) que permite al taller:
- Crear cotizaciones sin necesidad de una WorkOrder abierta
- Registrar datos del vehículo como texto libre (sin crear Asset hasta aprobar)
- Hacer seguimiento de presupuestos enviados, aprobados, rechazados y vencidos
- Al aprobarse, crear automáticamente Contact + Asset + WorkOrder

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

### Budget no requiere WorkOrder ni Asset

Un Budget se crea con datos textuales del cliente y el vehículo. No necesita
que el vehículo esté presente en el taller. Esto cubre el escenario real:
"cliente llama, pide precio, nunca trae el vehículo".

### Al aprobarse → se materializa en Contact + Asset + WorkOrder

La aprobación de un Budget es el trigger que crea:
1. **Contact** si no existe (por teléfono/email). Si existe, se reusa.
2. **Asset** (vehículo) con los datos textuales del budget.
3. **WorkOrder** con los items copiados del budget.

El Budget mantiene `converted_to_work_order_id` para trazabilidad.

### Numeración

Los Budgets se numeran con prefijo `BGT-{000000}` usando el mismo
`DocumentSequenceService` (type=`BGT`) para mantener consistencia
con la numeración de invoices.

### RLS

Ambas tablas (`budgets`, `budget_items`) usan `BelongsToTenant`.
Aislamiento completo por tenant.

---

## Modelos

### Budget

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | UUID (PK) | |
| `tenant_id` | UUID (FK) | |
| `code` | string(50) | `BGT-{000000}` |
| `contact_id` | UUID (FK→contacts, nullable) | Si el contacto existe |
| `contact_name` | string(255) | Nombre textual (siempre) |
| `contact_phone` | string(40, nullable) | Teléfono |
| `contact_email` | string(255, nullable) | Email |
| `vehicle_data` | jsonb | `{make, model, plate, year, color}` — texto libre |
| `status` | string(20) | `draft`, `sent`, `approved`, `rejected`, `expired` |
| `subtotal` | decimal(14,2) | |
| `discount_total` | decimal(14,2) | |
| `tax_total` | decimal(14,2) | |
| `grand_total` | decimal(14,2) | |
| `notes` | text (nullable) | |
| `sent_at` | timestamp (nullable) | |
| `responded_at` | timestamp (nullable) | |
| `approved_at` | timestamp (nullable) | |
| `rejected_at` | timestamp (nullable) | |
| `rejection_reason` | text (nullable) | |
| `converted_to_work_order_id` | UUID (FK→work_orders, nullable) | WorkOrder creada al aprobar |
| `created_by` | UUID (FK→users, nullable) | Quién creó el budget |
| timestamps + soft deletes | | |

### BudgetItem

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | UUID (PK) | |
| `tenant_id` | UUID (FK) | |
| `budget_id` | UUID (FK→budgets, cascade) | |
| `description` | text | |
| `quantity` | decimal(12,4) | |
| `unit_price` | decimal(14,2) | |
| `discount` | decimal(14,2) | default 0 |
| `tax_rate` | decimal(5,2) | default 0 |
| `subtotal` | decimal(14,2) | |
| `total` | decimal(14,2) | |
| `sort_order` | integer | default 0 |
| timestamps | | |

---

## Enums

### BudgetStatusEnum

```php
enum BudgetStatusEnum: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
```

Transiciones permitidas:
- `draft` → `sent`
- `sent` → `approved` | `rejected`
- `approved` → (solo conversión a WorkOrder)
- `rejected` → (terminal)
- `expired` → (terminal, automático si > 30 días en sent)

### Transition rules:
- Solo en `sent` se puede aprobar/rechazar.
- Solo `approved` se puede convertir a WorkOrder.
- El expirado se maneja vía scope o scheduled job (no automático en save).

---

## Conversión Budget → WorkOrder

### Trigger: Cambio de status a `approved` en BudgetObserver

```php
public function updated(Budget $budget): void
{
    if ($budget->isDirty('status') && $budget->status === BudgetStatusEnum::Approved) {
        app(BudgetConversionService::class)->convert($budget);
    }
}
```

### BudgetConversionService::convert(Budget $budget): WorkOrder

1. **Contact**: Buscar por `contact_phone` o `contact_email`. Si no existe, crear:
   ```php
   Contact::create([
       'tenant_id' => $budget->tenant_id,
       'contact_type' => 'client',
       'name' => $budget->contact_name,
       'phone' => $budget->contact_phone,
       'email' => $budget->contact_email,
   ]);
   ```
2. **Asset**: Crear con `vehicle_data`:
   ```php
   Asset::create([
       'tenant_id' => $budget->tenant_id,
       'contact_id' => $contact->id,
       'make' => $budget->vehicle_data['make'] ?? null,
       'model' => $budget->vehicle_data['model'] ?? null,
       'plate' => $budget->vehicle_data['plate'] ?? null,
       'year' => $budget->vehicle_data['year'] ?? null,
       'color' => $budget->vehicle_data['color'] ?? null,
   ]);
   ```
3. **WorkOrder**: Crear con items copiados:
   ```php
   $workOrder = WorkOrder::create([
       'tenant_id' => $budget->tenant_id,
       'contact_id' => $contact->id,
       'asset_id' => $asset->id,
       'status' => WorkOrderStatusEnum::Received,
       'title' => "OT desde Budget {$budget->code}",
       'service_description' => $budget->notes,
   ]);
   
   foreach ($budget->items as $item) {
       $workOrder->items()->create([...]);
   }
   ```
4. **Actualizar Budget**:
   ```php
   $budget->update([
       'converted_to_work_order_id' => $workOrder->id,
       'contact_id' => $contact->id,
   ]);
   ```

---

## API / UI

### Fase 1 (Filament CRUD)

Budget Resource con:
- **List**: tabla con código, cliente, vehículo, total, status, días desde envío
- **Create**: formulario con datos de cliente y vehículo textuales + items repetibles
- **Edit**: solo si status = draft
- **View**: detalle completo + timeline de cambios

Acciones adicionales en el resource:
- `SendAction` → marca sent_at, cambia status a sent
- `ApproveAction` → marca approved_at, cambia status a approved
- `RejectAction` → marca rejected_at + reason, cambia status a rejected
- `ConvertToWorkOrderAction` → ejecuta BudgetConversionService

### Fase 2 (Dashboard Card 5)

Cuando Budget esté implementado, Card 5 del dashboard consulta:
- Budgets con status = sent en los últimos 7 días
- Budgets con status = sent > 3 días (fríos)
- Acciones: Llamar, Reenviar, Marcar perdido

---

## Tests

| Archivo | Tests | Descripción |
|---|---|---|
| `BudgetAppScopeTest.php` | 5 | CRUD, transiciones, conversión a WorkOrder |
| `BudgetRlsTest.php` | 3 | Aislamiento RLS multi-tenant |

Total estimado: **8 tests**.

---

## DoD (Definition of Done)

- [x] FEATURE_SPEC.md aprobado (GATE 0)
- [x] Schema SQL aprobado → migración ejecutada (GATE 2)
- [x] Budget model + BudgetItem model con BelongsToTenant, HasUuids, SoftDeletes
- [x] BudgetStatusEnum con transiciones
- [x] BudgetObserver → BudgetConversionService en approved
- [x] Budget Filament Resource (List/Create/Edit/View) + Send/Approve/Reject actions
- [x] 8 tests pasando (5 app-scope + 3 RLS)
- [x] Suite completa en verde
- [x] Pint formateado
- [x] engram.json actualizado
- [x] PR mergeado

---

## Pendientes (fuera de GATE 0)

- Dashboard Card 5 (depende de Budget existente)
- Job de expiración automática (Budgets > 30 días en sent → expired)
- Notificaciones al cliente cuando el budget se envía (email/WhatsApp)
