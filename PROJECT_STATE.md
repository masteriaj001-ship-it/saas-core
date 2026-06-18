# ProyectDashboard - SaaS Multitenant Talleres

> **Version:** 1.12.0 | **Status:** active_development | **Updated:** 2026-06-17

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

### work_order_closure

- **Status:** implemented
- **Last check:** 2026-06-17
- **Features:**
  - WorkOrderStatusEnum extendido: WorkDone, EvidencePending, WaitingClient, NoPickup, Breach
  - settings JSONB en work_orders con is_legacy_closure para migración legacy completed → work_done
  - signature_hash, signed_at, closure_notes en work_orders
  - SmsCode model con BelongsToTenant, validación (expiración, 3 reenvíos, 5 intentos)
  - blocked_until en contacts para restricción de clientes
  - sms_codes table con RLS
  - Migración de datos legacy: completed → work_done + flag is_legacy_closure
  - 10 tests PHPUnit (transiciones, SMS, legacy, RLS)
- **Notes:** FEATURE_SPEC.md en features/work-order-closure/. 7 estados del flujo de cierre. RLS en sms_codes. 222 tests total.

### tenant_job_context

- **Status:** implemented
- **Last check:** 2026-06-17
- **Features:**
  - BelongsToTenantJob trait captura tenant_id al dispatch
  - SetTenantContextForJob middleware restaura contexto en queue worker
  - 4 tests (dispatch con/sin contexto, middleware con/sin tenantId)
- **Notes:** GAP-003 fixeado. Proximo job multi-tenant SOLO necesita usar BelongsToTenantJob trait.

### rls_test_enforcement

- **Status:** implemented
- **Last check:** 2026-06-17
- **Features:**
  - TenantManager.setTenantContext/clearTenantContext sincronizan pgsql-rls
  - Helpers duplicados setRlsContext/clearRlsContext eliminados de 2 test files
  - Sync test verifica ambas conexiones tienen mismo contexto
  - 5/5 gaps RLS fixeados — arquitectura sellada
- **Notes:** GAP-004 fixeado. Production impact: zero (pgsql-rls solo existe en test). 235 tests.

### mfa_superadmin

- **Status:** implemented
- **Last check:** 2026-06-17
- **Features:**
  - User model implementa HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication
  - two_factor_secret encryptado via Crypt, two_factor_recovery_codes JSON
  - SuperadminPanelProvider: multiFactorAuthentication(isRequired: true)
  - AdminPanelProvider: multiFactorAuthentication(isRequired: false)
  - 5 tests (secret storage, recovery codes, TOTP verification, holder name, confirmation)
- **Notes:** USR-001 completado. Filament v5 built-in MFA (pragmarx/google2fa). TOTP codes generados programaticamente en tests.

### workorder_checklist

- **Status:** implemented
- **Last check:** 2026-06-17
- **Features:**
  - WorkOrderChecklistItem model con BelongsToTenant, SoftDeletes, HasUuids
  - WorkOrderChecklistStatusEnum: pending/done/ok/nok/na con labels y colores
  - WorkOrder::checklistItems() HasMany ordered by position
  - ChecklistRelationManager (CRUD inline en WorkOrder Edit)
  - 10 tests (5 app-scope + 5 RLS)
- **Notes:** Cierra vertical Talleres. Done=ejecutado (mecanico), Ok/Nok=revision calidad (supervisor). SoftDeletes evita cascade en soft delete (forceDelete si requiere cascade fisico).

### audit_logs

- **Status:** implemented
- **Last check:** 2026-06-17
- **Features:**
  - spatie/laravel-activitylog v5 installado y tenant-aware
  - App\Models\Activity extiende SpatieActivity con BelongsToTenant
  - Migration: tenant_id UUID FK + RLS + nullableUuidMorphs
  - App\Models\Concerns\Auditable wrapper sobre LogsActivity
  - Aplicado a: WorkOrder, Contact, Asset, Item, Tenant
  - BelongsToTenant: $ignoresMissingTenantContext para Activity model (no lanza en tests legacy)
  - 8 tests (5 app-scope + 3 RLS)
- **Notes:** Cross-cutting audit trail. nullableUuidMorphs necesario por UUIDs del proyecto. 273 tests total.

### facturacion_api

- **Status:** implemented
- **Last check:** 2026-06-17
- **Features:**
  - API REST /api/v1/invoices con CRUD + cancelación
  - InvoiceDocumentTypeEnum: Invoice, CreditNote, Pos
  - Tenant.regimen() devuelve declarante/no_declarante según settings
  - Tenant.documentTypeForRegimen(): declarante → FE, no_declarante → Pos
  - DocumentSequenceService con lockForUpdate (SELECT ... FOR UPDATE) por tenant y tipo
  - Formato FE-{000000}, NC-{000000}, POS-{000000} con prefix fijo
  - InvoiceCreationService con lógica de creación: items, impuestos, secuencia
  - InvoiceResource con ContactResource e InvoiceItemResource anidados
  - StoreInvoiceRequest con validación, creación de contacto nominal
  - UpdateInvoiceRequest con validación de draft-only
  - CancelInvoiceRequest con restricción issued-only
  - Route model binding para Invoice en modulo
  - 16 tests (6 API CRUD, 3 secuencia, 3 regimen, 3 RLS)
- **Notes:** FEATURE_SPEC.md en features/facturacion-api/. Rate limiting: throttle:60,1 en routes. POS sequence nullable. Tenant Auditable para regimen changes.

### login_redirect

- **Status:** implemented
- **Last check:** 2026-06-18
- **Features:**
  - Migration user_type a users (staff/client)
  - Custom LoginResponse sobreescribe Filament default
  - Client user_type → force redirect a Dashboard (sin intended)
  - Staff user_type → redirect()->intended() normal
  - UserFactory con state client()
  - 3 tests unitarios del LoginResponse
- **Notes:** Decisión: user_type field en vez de usar Spatie 'viewer' como proxy. Evita mezclar permisos con tipo de usuario.

## Test Suite

- **Total tests:** 276
- **Passing:** 276
- **Assertions:** 704
- **Status:** green
- **Last run:** 2026-06-18

## Architecture Rules

- **naming_zero_redundancy:** ✅
- **tenant_isolation_double_layer:** ✅
- **spatie_teams_disabled:** ✅
- **superadmin_tenant_id_null:** ✅
- **uuid_pks:** ✅
- **soft_deletes_exceptions:** ✅
- **module_activation_system:** ✅

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
- Module activation system (tenant_modules + middleware module:{key})
- Superadmin tenant context (SetTenantContext resuelve tenant para superadmin)
- current_tenant_id_or_null() PG function con fallback NULL
- TenantManager::withoutTenantContext() helper
- BelongsToTenantJob trait + SetTenantContextForJob middleware (queue tenant context)
- TenantManager dual-connection sync (pgsql + pgsql-rls) for RLS test enforcement
- MFA Superadmin (TOTP + Email code via Filament v5 built-in)
- Work Order Checklist (WorkOrderChecklistItem con status pending/done/ok/nok/na)
- Audit Logs (spatie/laravel-activitylog tenant-aware)
- ADR 001: Multi-tenant architecture documented

## Security Status

- **mfa_superadmin:** implemented
- **rls_enabled:** verified_5of5_fixed
- **rls_audit_date:** 2026-06-17
- **rls_gaps:** 
- **rls_fixed:** GAP-001, GAP-002, GAP-003, GAP-004, GAP-005
- **fix_priority:** none
- **audit_logs:** implemented
- **rate_limiting:** implemented
- **connections:** app logic tests (BYPASSRLS=true, default connection), security integration tests (NOBYPASSRLS, app_user)

## Next Actions

- [ ] Dashboard financiero con KPIs por tenant

---

> **Source:** `engram.json` | **Generated by:** `php artisan jaosoft:project-state`
