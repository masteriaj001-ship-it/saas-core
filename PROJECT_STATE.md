# ProyectDashboard - SaaS Multitenant Talleres

> **Version:** 1.1.0 | **Status:** active_development | **Updated:** 2026-06-17

## Stack

| Technology | Version |
|---|---|
| laravel | ^13.8 |
| php | ^8.5 |
| postgresql | 16.14 |
| filament | ^5.6 |
| sail | Docker |

## Modules

### taller_filament

- **Status:** implemented
- **Last check:** 2026-06-08
- **Notes:** AdminPanelProvider configurado con SetTenantContext (sin clearTenantContext), VerifyTenantStatus, slug {tenant:slug}. Todos los Resources usan Schema API.
- **Tenant Resources:**
  - App\Filament\Resources\AssetResource (navigationSort: 1)
  - App\Filament\Resources\WorkOrderResource (navigationSort: 4)
  - App\Filament\Resources\ContactResource
  - App\Filament\Resources\TransactionResource
  - App\Filament\Resources\ItemResource
  - App\Filament\Resources\LocationResource
  - App\Filament\Resources\ServiceCatalogResource
  - App\Filament\Resources\InvoiceResource
- **Superadmin Resources:**
  - App\Filament\Superadmin\Resources\TenantResource
  - App\Filament\Superadmin\Resources\GlobalAssetResource
  - App\Filament\Superadmin\Resources\GlobalWorkOrderResource
- **Bug fixes:**
  - PreventRequestForgery eliminado del middleware stack del panel (CSRF livewire)
  - SetTenantContext: eliminado clearTenantContext del finally (403 en getSearchResultsUsing)
  - ContactPolicy creado (403 por falta de policy en Laravel 11+)
  - TransactionResource: Section namespace corregido (Schemas vs Forms)
  - InvoiceResource: status column type hint corregido (enum vs string)
- **Checklist:** `checklists/taller_filament.yaml`

### taller_permissions

- **Status:** implemented
- **Last check:** 2026-06-08
- **Notes:** Custom Role/Permission models con BelongsToTenant + HasUuids. RBAC/ABAC definido por entidad.
- **Roles:** Superadmin, Admin Tenant, Member, Cliente
- **Checklist:** `checklists/taller_permissions.yaml`

### facturacion

- **Status:** implemented
- **Last check:** 2026-06-08
- **Features:**
  - InvoiceResource con List/Create/Edit
  - InvoiceCodeGenerator con DB lock (FV-000001)
  - GenerateInvoiceFromWorkOrderAction (botón en EditWorkOrder)
  - IVA configurable por tenant (settings.es_responsable_iva)
  - PDF condicional (oculta IVA si tax_total=0)
- **Notes:** Toggle IVA en Superadmin TenantResource → Section Facturación.

### work_order_reception

- **Status:** implemented
- **Last check:** 2026-06-17
- **Features:**
  - CreateWorkOrderReceptionAction con normalización Contact/Asset/WorkOrder
  - createOptionForm en Select de Cliente y Vehículo (modal de creación rápida)
  - Campos de inspección: kilometraje, batería, notas estéticas
  - 5 tests PHPUnit (creación, reuso, aislamiento, ID existente)
- **Notes:** Hybrid (C) — operador ve campos planos, Action normaliza en background. asset_id NOT NULL en schema (siempre requiere vehículo).

### taller_locations

- **Status:** implemented
- **Last check:** 2026-06-08
- **Features:**
  - LocationResource con CRUD (List/Create/Edit)
  - WorkOrdersRelationManager en EditLocation
  - location_id FK en WorkOrder con ON DELETE SET NULL
  - LocationFactory con estados main/inactive
  - 8 tests PHPUnit (CRUD, validación, tenant isolation)
  - WorkOrderFactory con estado withLocation
  - WorkOrderResource: campo location_id en formulario
- **Notes:** Pest → PHPUnit conversion de LocationWorkOrderRelationTest. Missing Location import en WorkOrder corregido.

## Test Suite

- **Total tests:** 204
- **Passing:** 204
- **Assertions:** 562
- **Status:** green
- **Last run:** 2026-06-17

## Architecture Rules

- **naming_zero_redundancy:** ✅
- **tenant_isolation_double_layer:** ✅
- **spatie_teams_disabled:** ✅
- **superadmin_tenant_id_null:** ✅
- **uuid_pks:** ✅
- **soft_deletes_exceptions:** ✅

## Features Implemented

- Multi-tenancy con RLS PostgreSQL
- Spatie Permission custom con BelongsToTenant
- Filament 5 admin panel multi-tenant
- WorkOrder con Activities, Inspections, Items, Media
- Asset management (vehicles, equipment, phones, computers, space)
- Transaction/Invoice/Receipt
- Invoice desde WorkOrder con generación automática
- IVA configurable por tenant (settings JSONB)
- Contact management
- Item catalog con stock
- Location management
- ServiceCatalog
- Superadmin panel global
- Onboarding flow
- Tenant suspension
- Wizard de creación

## Security Status

- **mfa_superadmin:** pending
- **rls_enabled:** gaps_documented_and_partially_fixed
- **rls_audit_date:** 2026-06-17
- **rls_gaps:** GAP-002, GAP-003, GAP-004, GAP-005
- **rls_fixed:** GAP-001
- **fix_priority:** before_deploy
- **audit_logs:** pending
- **rate_limiting:** pending

## Next Actions

- [ ] Fix GAP-002: Superadmin tenant context (antes de deploy)
- [ ] Fix GAP-003: Jobs tenant context (antes de jobs multi-tenant)
- [ ] Activar MFA Superadmin (USR-001)
- [ ] Completar checklist taller_workorders.yaml
- [ ] Completar checklist taller_assets.yaml
- [ ] Completar checklist taller_transactions.yaml
- [ ] Completar Work Orders (UC-01 a UC-04)
- [ ] Completar Facturación (Http/Controllers, vistas, docs)

---

> **Source:** `engram.json` | **Generated by:** `php artisan jaosoft:project-state`
