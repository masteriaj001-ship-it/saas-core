# FEATURE SPEC: Work Order Checklist

## ID
**WORKORDER-CHECKLIST-001**

## Goal
Add a reactive checklist to Work Orders so workshop teams can track completion of per-order tasks independently of the vehicle inspection checklist (`WorkOrderInspection`) that already exists.

## Motivation
Work Order Closure (PR #4) sealed the status lifecycle, but operators have no way to track granular "to-do" items per order (e.g., "change oil", "test drive", "clean interior"). Currently these are either unwritten or stuffed into `notes` fields. A dedicated checklist with state-dependent visibility closes the Talleres vertical.

## Status Enum Semantics
There is no `done`/`ok` synonym — they express different concepts:

| Value | Meaning | Stage |
|-------|---------|-------|
| `pending` | Task not started | Initial state on creation |
| `done` | Task executed — action completed | Operational (mechanic) |
| `ok` | Task reviewed and correct — quality verified | Quality control (supervisor) |
| `nok` | Task reviewed and not correct — quality failed, requires corrective action | Quality control (supervisor) |
| `na` | Not applicable for this specific work order | Any stage |

**Rule:** `done` is the mechanic's completion; `ok`/`nok` is the supervisor's QC review. A task can go `done → ok` (passed QC) or `done → nok → done` (rework cycle).

## Database Schema

### Table: `work_order_checklist_items`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | UUID | PK, default `gen_random_uuid()` | |
| work_order_id | UUID | NOT NULL, FK → work_orders(id) ON DELETE CASCADE | |
| tenant_id | UUID | NOT NULL, FK → tenants(id) ON DELETE CASCADE | Injected via BelongsToTenant |
| task | VARCHAR(255) | NOT NULL | Description of the task |
| status | VARCHAR(20) | NOT NULL, DEFAULT 'pending' | Enum: pending/done/ok/nok/na |
| position | INTEGER | NOT NULL, DEFAULT 0 | Ordering within checklist |
| notes | TEXT | NULLABLE | Free text (e.g., reason for nok) |
| assigned_to | UUID | NULLABLE, FK → contacts(id) ON DELETE SET NULL | ⚠️ NOT REQUIRED — no assignment logic in this feature |
| completed_by | UUID | NULLABLE, FK → contacts(id) ON DELETE SET NULL | Auto-set when status changes to done/ok/nok |
| completed_at | TIMESTAMPTZ | NULLABLE | Auto-set when status changes to done/ok/nok |
| created_at | TIMESTAMPTZ | NOT NULL | |
| updated_at | TIMESTAMPTZ | NOT NULL | |
| deleted_at | TIMESTAMPTZ | NULLABLE | Soft deletes |

**Indexes:**
- `(work_order_id, position)` — ordering per WO
- `(tenant_id)` — RLS policy
- `(work_order_id, status)` — filtering by status

**RLS:** ENABLED with tenant isolation policies (same pattern as all other Talleres tables).

## Non-Goals
- Task assignment workflow (Slack/email notifications, due dates, SLA). `assigned_to` is stored purely for display.
- Checklist templates/library (reusable task lists across WOs). Tasks are created per WO.
- Integration with invoicing or inventory. Checklist items are pure tracking.
- Photo attachments per item. Use existing `WorkOrderMedia` if needed.
- Bulk operations (complete all, reset all).

## Implementation Plan

### Model
`app/Modules/Talleres/Models/WorkOrderChecklistItem.php`
- Extends `TenantModel` (provides BelongsToTenant, HasUuids, SoftDeletes, $guarded)
- `$fillable`: work_order_id, task, status, position, notes, assigned_to, completed_by, completed_at
- `$casts`: status → WorkOrderChecklistStatusEnum
- Relationships: `workOrder()` BelongsTo, `assignee()` BelongsTo Member (assigned_to), `completer()` BelongsTo Member (completed_by)

### Enum
`app/Enums/WorkOrderChecklistStatusEnum.php`
- Cases: Pending, Done, Ok, Nok, Na
- `getLabel()` returning Spanish labels
- `getColor()` for Filament badge colors

### WorkOrder Model Update
Add `checklistItems()` HasMany relationship (ordered by position).

### Filament UI: RelationManager
`app/Filament/Resources/WorkOrderResource/RelationManagers/ChecklistRelationManager.php`
- Table: position, task, status badge, notes, assigned_to, completed_by, completed_at
- Actions: Create, Edit, Delete (inline via modal)
- Status change triggers: update completed_by + completed_at automatically
- Position reorderable (drag)

### Factory
`database/factories/WorkOrderChecklistItemFactory.php`

### Tests

#### `tests/Feature/Modules/Talleres/WorkOrderChecklistAppScopeTest.php`
1. Create checklist item on a WO
2. Update task status (pending → done → ok)
3. Status nok requires notes (optional validation in UI, not DB)
4. Soft delete restores correctly
5. Assigned_to is nullable (no validation required)

#### `tests/Feature/Modules/Talleres/WorkOrderChecklistRlsTest.php`
1. Tenant A cannot see Tenant B's checklist items (via pgsql-rls connection)
2. Cascade delete: deleting WO removes its checklist items

### Files Modified
| File | Change |
|------|--------|
| `app/Modules/Talleres/Models/WorkOrderChecklistItem.php` | Create |
| `app/Enums/WorkOrderChecklistStatusEnum.php` | Create |
| `app/Modules/Talleres/Models/WorkOrder.php` | Add `checklistItems()` relation |
| `app/Filament/Resources/WorkOrderResource/RelationManagers/ChecklistRelationManager.php` | Create |
| `database/factories/WorkOrderChecklistItemFactory.php` | Create |
| `database/migrations/xxxx_xx_xx_xxxxxx_create_work_order_checklist_items_table.php` | Create |
| `tests/Feature/Modules/Talleres/WorkOrderChecklistAppScopeTest.php` | Create |
| `tests/Feature/Modules/Talleres/WorkOrderChecklistRlsTest.php` | Create |
| `engram.json` | Update v1.9.0 |

## Validation / QA

### DoD Checklist
- [x] FEATURE_SPEC.md approved (GATE 0)
- [x] Branch `feature/workorder-checklist` created
- [x] Schema SQL → John escribe "APROBADO" → migration generated
- [x] Migration → John escribe "APROBADO" → artisan migrate
- [x] Tests: 10 checklist tests pass (5 app-scope + 5 RLS) — all green
- [x] Full suite: 250 tests, 250 passed, 636 assertions
- [x] Pint: 1 file fixed (unused imports)
- [x] engram.json v1.9.0
- [x] `vendor/bin/sail php -r 'function_exists("opcache_reset") && opcache_reset();'`
- [ ] PR → CI green → merge (admin override)
- [ ] Delete feature branch

### Tests Added
- 5 app-scope tests: creation, status transition (pending→done→ok), soft delete restore, nullable assigned_to, position ordering
- 5 RLS tests: cross-tenant read isolation, cross-tenant update isolation, insert without context fails, force delete cascade, soft delete no cascade
- 1 test removed from spec (nok requires notes — no DB validation to assert)
- 2 tests added beyond spec: force_delete_cascades, soft_delete_does_not_cascade (needed because TenantModel uses SoftDeletes)
- Total: 10 new → 250 total on main
