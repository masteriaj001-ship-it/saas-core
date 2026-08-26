# PROJECT STATE — ProyectDashboard

> Stack: Laravel ^13.8 · PHP ^8.3 · PostgreSQL 16.14 · Filament ^5.6 · RLS Nativo
> Última actualización: 2026-08-26 (SUPERADMIN PANEL — EXPIRY DOWNGRADE TO FREE. 505 TESTS, 1153 ASSERTIONS)

---

## 1. Resumen Ejecutivo

SaaS multi-tenant con aislamiento por **PostgreSQL RLS nativo** (sin paquetes de tenancy). Core base con módulos anexables por industria. 31 migraciones ejecutadas, aislamiento multi-tenant vía `->tenant(Tenant::class, slugAttribute: 'slug')` en Filament + middleware `SetTenantContext` (sin clearTenantContext en finally) + trait `BelongsToTenant` con global scope.

**Fase actual:** VERTICAL TALLERES — COMPLETO. Módulo Facturación — CREADO (invoices + invoice_items). Panel admin (`/admin/{slug}`) con 8 Resources + Dashboard widgets + bloqueo de tenants suspendidos. Panel superadmin (`/superadmin`) con plans/subscriptions, impersonation, plan limits, dashboard widgets, auto-downgrade de planes expirados a free. **505 tests, 1153 assertions — 0 regresiones.**

---

## 2. Estado Actual

### Migraciones ejecutadas (36/36)

| Orden | Archivo | Tabla | RLS |
|---|---|---|---|---|---|---|---|
| 1 | `0000_00_00_000001_create_current_tenant_id_function` | Función PG | — |
| 2 | `0000_00_00_000002_create_tenants_table` | `tenants` | Sin RLS (raíz) |
| 3 | `0001_01_01_000000_create_users_table` | `users` | 4 políticas (SELECT con permiso login sin contexto) |
| 4 | `0001_01_01_000001_create_cache_table` | `cache` | — |
| 5 | `0001_01_01_000002_create_jobs_table` | `jobs` | — |
| 6 | `2026_05_25_031519_create_assets_table` | `assets` | 4 políticas |
| 7 | `2026_05_25_031520_create_items_table` | `items` | 4 políticas |
| 8 | `2026_05_25_031521_create_contacts_table` | `contacts` | 4 políticas |
| 9 | `2026_05_25_192256_create_work_orders_table` | `work_orders` | 4 políticas |
| 10 | (misma que 9) | `work_order_items` | 4 políticas |
| 11 | `2026_05_26_160000_create_permission_tables` | `roles` | 4 políticas |
| 11 | (misma que 11) | `permissions` | 4 políticas |
| 11 | (misma que 11) | `model_has_roles` | 4 políticas |
| 11 | (misma que 11) | `model_has_permissions` | 4 políticas |
| 11 | (misma que 11) | `role_has_permissions` | 4 políticas |
| 12 | `2026_05_27_000001_create_transactions_table` | `transactions` | 4 políticas |
| 12 | (misma que 12) | `transaction_items` | 4 políticas |
| 13 | `2026_05_28_000001_create_modules_catalog_table` | `modules_catalog` | Sin RLS (catálogo global) |
| 14 | `2026_05_28_000002_create_categories_table` | `categories` | 4 políticas |
| 15 | `2026_05_28_000003_create_locations_table` | `locations` | 4 políticas |
| 16 | `2026_05_28_000004_create_tenant_modules_table` | `tenant_modules` | 4 políticas |
| 17 | `2026_05_28_000005_add_is_superadmin_to_users_table` | `users` (add col) | `is_superadmin boolean NOT NULL DEFAULT false` |
| 18 | `2026_05_28_032815_alter_users_table_make_tenant_id_nullable` | `users` (alter) | `ALTER COLUMN tenant_id DROP NOT NULL` (soporte superadmin global) |
| 19 | `2026_06_03_230122_add_vin_and_owner_to_assets` | `assets` (add cols) | `vin VARCHAR(100)` + `owner_id UUID FK→contacts` + índices `idx_assets_tenant_vin` (UNIQUE, partial WHERE deleted_at IS NULL) + `idx_assets_owner` |
| 20 | `2026_06_03_232437_add_vehicle_type_to_assets` | `assets` (add col) | `vehicle_type VARCHAR(50)` NULL |
| 21 | `2026_06_03_232437_create_service_catalogs_table` | `service_catalogs` | 7 columnas + RLS 4 políticas + índices `(tenant_id)`, `UNIQUE (tenant_id, name) WHERE deleted_at IS NULL`, `(tenant_id, is_active)` |
| 22 | `2026_06_04_000001_add_sprint1_fields_to_work_orders` | `work_orders` (add cols) | +10 columnas: `mechanic_id`/`advisor_id` UUID FK→contacts ON DELETE SET NULL, `reception_notes`, `fuel_level`, `diagnosis_summary`, `approval_channel`, `approval_at`, `qc_passed`, `qc_notes`, `delivery_at` + índices `idx_work_orders_mechanic (tenant_id, mechanic_id) WHERE deleted_at IS NULL` + `idx_work_orders_advisor` |
| 23 | `2026_06_04_000002_add_type_to_work_order_items` | `work_order_items` (add col) | `type VARCHAR(50) NOT NULL DEFAULT 'part'` — distingue Part/Service/Labor en líneas de OT |
| 24 | `2026_06_04_000003_create_work_order_activities_table` | `work_order_activities` | 4 políticas RLS + FORCE. Columnas: type, description, from_status, to_status, metadata JSONB. Índices: `(tenant_id)`, `(tenant_id, work_order_id)`, `(tenant_id, user_id)` partial. Sin deleted_at |
| 25 | `2026_06_04_000004_create_work_order_inspections_table` | `work_order_inspections` | 4 políticas RLS + FORCE. Columnas: item_name, status, notes, photo_path (reservado), sort_order. Índices: `(tenant_id)`, `(tenant_id, work_order_id)`. Sin deleted_at |
| 26 | `2026_06_04_000005_create_work_order_media_table` | `work_order_media` | 4 políticas RLS + FORCE. Columnas: storage_path, original_name, mime_type, size, metadata JSONB. Índices: `(tenant_id)`, `(tenant_id, work_order_id)`, `(tenant_id, work_order_inspection_id)` partial. Sin deleted_at |
| 27 | `2026_06_06_000001_make_item_id_nullable_in_work_order_items` | `work_order_items` (alter) | `DROP NOT NULL item_id`. Soporta items type=service (solo service_catalog_id) y type=labor (solo description) sin violación de constraint |
| 28 | `2026_06_06_000002_add_inspection_fields_and_code_index_to_work_orders` | `work_orders` (add cols + index) | `mileage_km INTEGER`, `battery_level VARCHAR(50)`, `aesthetic_notes TEXT`. Índice único parcial `(tenant_id, code) WHERE deleted_at IS NULL` |
| 29 | `2026_06_07_000001_add_document_fields_to_contacts` | `contacts` (add cols + index) | `document_type VARCHAR(10)`, `document_number VARCHAR(30)`, `city VARCHAR(100)`. Índice parcial `(tenant_id, document_number) WHERE document_number IS NOT NULL AND deleted_at IS NULL` |
| 30 | `2026_06_07_000002_create_invoices_table` | `invoices` | 20 columnas + RLS + 4 índices (incl. UNIQUE parcial tenant_id+document_number) |
| 31 | `2026_06_07_000003_create_invoice_items_table` | `invoice_items` | 14 columnas + RLS + 2 índices. Sin SoftDeletes (ítems inmutables) |

### Enums

| Enum | Values | Contracts | Uso |
|---|---|---|---|---|
| `WorkOrderStatusEnum` | Draft, Received, Diagnosing, Quoted, WaitingApproval, WaitingParts, InProgress, Paused, Qc, Completed, Delivered, Cancelled | `HasLabel`, `HasColor` | WorkOrder.model status cast + WorkOrderStatusChart + Factory |
| `VehicleTypeEnum` | Sedan, Motorcycle, PickupTruck, Suv, Van, Truck, Other | `HasLabel`, `HasColor` | Asset.model vehicle_type cast + Select form field |
| `WorkOrderItemTypeEnum` | Part, Service, Labor | `HasLabel`, `HasColor` | WorkOrderItem.model type cast + Select + badge en ItemsRelationManager |
| `WorkOrderActivityTypeEnum` | StatusChange, Note, Assignment, Qc | `HasLabel` | WorkOrderActivity.model type cast + badge en ActivitiesRelationManager |
| `InspectionItemStatusEnum` | Ok, Damaged, Missing | `HasLabel`, `HasColor` | WorkOrderInspection.model status cast + Select + badge en InspectionsRelationManager |
| `DocumentTypeEnum` | CC, NIT, CE, PAS, TI | `HasLabel`, `HasColor` | Contact.model document_type cast + Select en ContactResource + WorkOrderResource createOptionForm |
| `InvoiceStatusEnum` | Draft, Issued, Paid, Cancelled | `HasLabel`, `HasColor` | Invoice.model status cast |
| `InvoiceDocumentTypeEnum` | Invoice, CreditNote | `HasLabel` | Invoice.model document_type cast |

### Modelos (23 + trait)

| Modelo | Extiende | UUID PK | BelongsToTenant | SoftDeletes | Notas |
|---|---|---|---|---|---|---|---|---|
| `Tenant` | `Model` | ✅ HasUuids | ❌ (raíz) | ❌ | Slug usado como route key en Filament |
| `User` | `Authenticatable` | ✅ HasUuids + $incrementing=false + $keyType=string | ✅ trait (con excepción: salta si is_superadmin=true) | ✅ | Implementa `Filament\Models\Contracts\HasTenants` |
| `Asset` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | **Movido a `Modules\Talleres\Models\Asset`** |
| `Item` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `Contact` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | Sprint A: +document_type (DocumentTypeEnum cast), +document_number, +city |
| `WorkOrder` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | **Movido a `Modules\Talleres\Models\WorkOrder`**. +10 campos Sprint 1: mechanic/advisor FK→contacts, reception_notes, fuel_level, diagnosis_summary, approval_channel/at, qc_passed/notes, delivery_at |
| `WorkOrderItem` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | **Movido a `Modules\Talleres\Models\WorkOrderItem`**. Sprint 2: +type (WorkOrderItemTypeEnum cast) |
| `WorkOrderActivity` | `Model` | ✅ HasUuids | ✅ trait | ❌ (inmutable) | `Modules\Talleres\Models`. Sin SoftDeletes. Relaciones: workOrder(), user(). Cast: metadata=>array, type=>WorkOrderActivityTypeEnum |
| `WorkOrderInspection` | `Model` | ✅ HasUuids | ✅ trait | ❌ (inmutable) | `Modules\Talleres\Models`. Sin SoftDeletes. Relaciones: workOrder(), media(). Cast: status=>InspectionItemStatusEnum, sort_order=>integer. `photo_path` deprecated |
| `WorkOrderMedia` | `Model` | ✅ HasUuids | ✅ trait | ❌ (inmutable) | `Modules\Talleres\Models`. Sin SoftDeletes. Relaciones: workOrder(), inspection(), user(). Cast: metadata=>array, size=>integer |
| `Transaction` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `TransactionItem` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `Role` | `Spatie\Permission\Models\Role` | ✅ HasUuids + $incrementing=false + $keyType=string | ✅ trait | ❌ | |
| `Permission` | `Spatie\Permission\Models\Permission` | ✅ HasUuids + $incrementing=false + $keyType=string | ✅ trait | ❌ | |
| `Location` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `Category` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `ServiceCatalog` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | Catálogo de servicios por taller |
| `Invoice` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | Módulo Facturacion. +casts status (InvoiceStatusEnum), document_type (InvoiceDocumentTypeEnum). Relaciones: workOrder(), contact(), items() |
| `InvoiceItem` | `Model` | ✅ HasUuids | ✅ BelongsToTenant trait | ❌ | Módulo Facturacion. Sin SoftDeletes (ítems inmutables). Relaciones: invoice(), workOrderItem() |
| `TenantModule` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `ModuleCatalog` | `Model` | ✅ HasUuids | ❌ (global) | ❌ | |
| `TenantModel` | `Model` | Abstracto base | — | — | |
| (trait) `BelongsToTenant` | — | — | Global scope + creating event | — | Modificado: salta excepción si `is_superadmin=true` |

**Nota crítica:** `User`, `Role` y `Permission` requieren `$incrementing = false`, `$keyType = 'string'` y `HasUuids` para que Spatie's eager loading funcione (usa `whereIntegerInRaw` que falla con UUIDs si $incrementing es true).

### Factories (14)

| Factory | Modelo |
|---|---|
| `TenantFactory` | Tenant |
| `UserFactory` | User |
| `AssetFactory` | Asset |
| `ContactFactory` | Contact |
| `ItemFactory` | Item |
| `WorkOrderFactory` | WorkOrder (enum random status via `WorkOrderStatusEnum::cases()`, +10 null fields Sprint 1, `withMechanic()` state) |
| `WorkOrderItemFactory` | WorkOrderItem (default Part, states: asService, asLabor) |
| `WorkOrderActivityFactory` | WorkOrderActivity (default StatusChange, states: asNote, asAssignment) |
| `WorkOrderInspectionFactory` | WorkOrderInspection (default Ok, states: damaged, missing) |
| `WorkOrderMediaFactory` | WorkOrderMedia (default image/jpeg, states: asPdf, asVideo, forInspection) |
| `TransactionFactory` | Transaction (states: sale/purchase/draft/issued/cancelled) |
| `TransactionItemFactory` | TransactionItem |
| `InvoiceFactory` | Invoice (states: issued, paid, cancelled) |
| `InvoiceItemFactory` | InvoiceItem |

### Middleware + Servicios

| Componente | Ruta | Propósito |
|---|---|---|---|
| `SetTenantContext` | `app/Http/Middleware/` | Inyecta tenant_id en PG por request (registrado como persistent middleware en Filament). Retorna 403 si tenant_id está vacío. Sin clearTenantContext en finally — el contexto persiste durante todo el lifecycle del componente Livewire, incluyendo getSearchResultsUsing |
| `VerifyTenantStatus` | `app/Http/Middleware/` | Bloquea acceso al panel `/admin` si `tenant->is_active === false`. Corre después de `SetTenantContext`. Superadmins bypass. Retorna 403 con vista `errors/tenant-suspended.blade.php` |
| `EnsureIsSuperAdmin` | `app/Http/Middleware/` | Middleware del panel superadmin: retorna 403 si `auth()->user()->is_superadmin !== true` |
| `TenantManager` | `app/Services/` | Singleton, puente PHP ↔ PostgreSQL |
| `BelongsToTenant` | `app/Models/Concerns/` | Trait con global scope + creating event. Modificado para omitir exception si `is_superadmin=true` |
| `current_tenant_id()` | BD (función PG) | Firewall que valida UUID y lanza error si falta contexto |
| `WorkOrderService` | `app/Modules/Talleres/Services/` | CRUD + generación de códigos WO-XXXX (**movido a módulo**) |
| `WorkOrderCodeGenerator` | `app/Modules/Talleres/Services/` | Generación secuencial atómica con DB lock (via `lockForUpdate()`) |
| `WorkOrderWebhookService` | `app/Modules/Talleres/Services/` | HTTP POST fire-and-forget a n8n cuando cambia status de una OT |
| `TransactionService` | `app/Services/Transactions/` | Generación secuencial de facturas (contador atómico en tenants.settings), cálculo de IVA/totales, emitir/anular transacciones |
| `RegisterService` | `app/Services/Auth/` | Registro + creación tenant + defaults automáticos (location, categories, items, contacts, módulos) por industria |

### Rutas Web

| Ruta | Método | Controlador | Propósito |
|---|---|---|---|
| `/register` | GET/POST | `RegisterController` | Registro público con industria + defaults |
| `/forgot-password` | GET/POST | `ForgotPasswordController` | Solicitar recuperación de contraseña |
| `/reset-password/{token}` | GET/POST | `ResetPasswordController` | Restablecer contraseña |
| `/login` (named) | GET | Redirect → `/admin/login` | Named route para Filament login |

### Rutas API

| Ruta | Método | Controlador | Auth |
|---|---|---|---|
| `/api/sanctum/token` | POST | `ApiTokenController@createToken` | No (credenciales en body) |
| `/api/sanctum/token` | DELETE | `ApiTokenController@revokeCurrentToken` | Sanctum |
| `/api/sanctum/tokens` | GET/DELETE | `ApiTokenController@listTokens/revokeAllTokens` | Sanctum |

### Seeders

| Seeder | Propósito |
|---|---|
| `RolePermissionSeeder` | Crea 20 permisos + 4 roles (`owner`/`admin`/`editor`/`viewer`) por tenant |
| `ModulesCatalogSeeder` | Crea 5 módulos globales: inventory, transactions, contacts, work_orders, reports |
| `DatabaseSeeder` | Crea datos de prueba (actualizado: no crea usuarios huérfanos) |
| `TenantTemplateSeeder` | Crea defaults por industria: categories, items, assets, service_catalogs + marca onboarding_completed |

### Comandos Artisan

| Comando | Propósito |
|---|---|---|
| `tenant:create` | Crea tenant + usuario admin + ejecuta RolePermissionSeeder + asigna rol `owner` |
| `jaosoft:make-superadmin` | Crea superadmin global (`tenant_id=null`, `is_superadmin=true`). Interactivo: Nombre, Email, Contraseña |
| `jaosoft:create-tenant-admin` | Transaccional: Crea Tenant → setTenantContext → User (owner) → commit. Interactivo: Empresa, Slug, Admin Nombre, Admin Email, Contraseña |

### Filament Resources (Admin Panel — `/admin/{slug}`)

| Recurso | Páginas |
|---|---|
| `AssetResource` | List / Create / Edit — name, code, plate, vin, vehicle_type, owner, brand, model, year, asset_type, status, metadata, acquired_at |
| `ContactResource` | List / Create / Edit — contact_type, name, tax_id, email, phone, address, metadata, +Section "Identificación" (document_type Select, document_number, city) |
| `ItemResource` | List / Create / Edit — sku, name, item_type, unit, price, cost, stock, min_stock, metadata |
| `WorkOrderResource` | List (con permisos Spatie) / Create (wizard 3 pasos con auto-código, Selects async con búsqueda Client/Vehicle sin createOptionForm — usuarios crean Contact/Asset desde sus módulos dedicados) / Edit + ItemsRelationManager + ActivitiesRelationManager (read-only) + InspectionsRelationManager + MediaRelationManager. Sprint 1: +4 Sections (Asignación con mechanic/advisor async Select, Recepción, Diagnóstico+Aprobación, Control de Calidad), columna mechanic en tabla. Sprint 3a: ActivitiesRelationManager con badge type, description, user, created_at. Sprint 3b: InspectionsRelationManager con Create/Edit, Select status enum, badge color, notes condicional, defaultSort sort_order. Sprint 4: MediaRelationManager con FileUpload→MinIO, badge mime_type, size human readable. InspectionsRelationManager +media_count badge + ViewAction |
| `TransactionResource` | List / Create / Edit + ItemsRelationManager — type (sale/purchase), contact, invoice_number, CUFE, resolución DIAN, payment_method, items con IVA, totes automáticos, acciones de Emitir/Anular |
| `LocationResource` | List / Create / Edit — name, address, is_main (badge Principal), is_active |
| `ServiceCatalogResource` | List / Create / Edit — name, base_price, estimated_minutes, is_active |
| `InvoiceResource` 🆕 | List / Create (con auto-generación document_number via InvoiceCodeGenerator) / Edit — Encabezado (document_type, contact, work_order, status, fechas), Ítems (Repeater con cálculos live quantity/unit_price/discount/tax_rate), Totales readonly, notas |

### Filament Resources (Superadmin Panel — `/superadmin`)

| Recurso | Páginas | Query |
|---|---|---|
| `TenantResource` | List / Create / Edit / Delete | Global (sin scope) — name, slug, planName, is_active (toggle), filtros plan/estado, **impersonate action**. Create incluye admin Section con handleRecordCreation transaccional |
| `PlanResource` | List / Create / Edit | Global — CRUD para planes (free/pro/enterprise) con price, limits, features |
| `UserResource` | List (read-only) | Global — todos los usuarios con tenant.name, is_superadmin badge |
| `GlobalAssetResource` | List (read-only) | `withoutGlobalScope('tenant')` — todos los activos de la BD con columna tenant.name |
| `GlobalWorkOrderResource` | List (read-only) | `withoutGlobalScope('tenant')` — todas las WOs de la BD con columna tenant.name |

### Filament Pages (Superadmin Panel)

| Página | Ruta | Propósito |
|---|---|---|
| `SuperAdminDashboard` | `/superadmin` | Dashboard con 4 widgets: TenantStats, PlanDistribution (doughnut chart), RecentActivity, ChurnRisk |
| `ViewTenant` | `/superadmin/tenants/{record}` | Vista detallada del tenant con info, plan, acciones |

### Superadmin Panel — Plans & Subscriptions

| Componente | Descripción |
|---|---|
| `Plan` model | free/pro/enterprise con max_users, max_work_orders_monthly, price, features JSON |
| `Subscription` model | tenant_id (unique), plan_id, status (active/expired/suspended), starts_at, expires_at |
| `SubscriptionLog` model | audit trail de cambios de plan (changed_by nullable para cambios automáticos) |
| `ImpersonationLog` model | audit trail de impersonation (superadmin_id, tenant_id, impersonated_at) |
| `SubscriptionService` | changePlan(), getActivePlan(), isExpired(), isSuspended(), isActive() |
| `ImpersonationService` | start(), stop(), isImpersonating() — session flag |
| `WorkOrderLimitObserver` | Enforce work order limits; expired → free plan limits (not unlimited) |
| `UserLimitObserver` | Enforce user limits; expired → free plan limits (not unlimited) |
| `CheckExpiredSubscriptions` | Artisan command, hourly: downgrades expired to free + SubscriptionLog audit |
| `ImpersonationBanner` middleware | Shows yellow banner during impersonation |
| Routes | `/superadmin/impersonate/{tenant}`, `/superadmin/stop-impersonating` |

**Flujo de expiración (Option A — downgrade a free):**
1. Superadmin sube tenant a Pro con `expires_at` manual
2. Tenant opera con límites Pro
3. Al vencer → `subscriptions:check-expired` (hourly) → plan_id → free, status → active, expires_at → null
4. `SubscriptionLog` registra `reason = 'expired_downgraded_to_free'`
5. Tenant opera con límites free (20 OTs/mes, 2 usuarios)
6. Superadmin reactiva manualmente con `changePlan()`

### Filament Pages (Admin Panel)

| Página | Ruta | Propósito |
|---|---|---|
| `Dashboard` | `/admin/{tenant:slug}` | Dashboard principal con widgets |
| `Onboarding` | `/admin/{tenant:slug}/onboarding` | Onboarding post-registro (industria + defaults) |
| `TallerOnboarding` | `/admin/{tenant:slug}/talleres/onboarding` | Wizard 3 pasos Neon Garage (Fase 2 UX/UI) |

### Dashboard Widgets (3)

| Widget | Tipo | Datos |
|---|---|---|
| `DemoStatsOverview` | StatsOverviewWidget | 5 cards: Assets, Items (+stock bajo), Contacts, WorkOrders, Stock Bajo |
| `WorkOrderStatusChart` | BarChartWidget | WOs agrupadas por status dinámico desde `WorkOrderStatusEnum::cases()` con colores mapeados |
| `LatestWorkOrdersTable` | TableWidget | Últimas 5 WOs con código, título, asset, status, fecha |

### Tests (505 tests, 1153 assertions)

| Test Suite | Archivos | Tests | Propósito |
|---|---|---|---|---|---|---|---|---|
| WorkOrderTest | `tests/Feature/WorkOrders/` | 4 | CRUD + status transitions |
| AssetTallerTest | `tests/Feature/Talleres/` | 7 | Plate/brand/model/year + unique plate + VIN/owner CRUD + VIN unique per tenant + VIN cross-tenant + owner relationship |
| AssetVinOwnerTest | `tests/Feature/Talleres/` | 4 | VIN/owner CRUD (separado), VIN unique tenant, VIN cross-tenant, owner relationship |
| ServiceCatalogTest | `tests/Feature/Talleres/` | 5 | ServiceCatalog CRUD + tenant isolation + validación + VehicleTypeEnum values + mechanic template seeds 5 catalogs |
| WorkOrderTallerTest | `tests/Feature/Talleres/` | 5 | WorkOrder con service_description + modal inline Contact/Asset creation + items service/labor/part + FK inválido |
| WorkOrderCodeGeneratorTest | `tests/Feature/` | 4 | WorkOrderCodeGenerator: primer código, secuencia, post-existente, soft-delete |
| WorkOrderWizardTest | `tests/Feature/Talleres/` | 4 | Wizard 3 pasos CreateWorkOrder: validación, código, Edit sin wizard, schemas |
| WorkOrderPhase3Test | `tests/Feature/Talleres/` | 3 | Inspection fields, code unique per tenant, contacto duplicado por teléfono |
| CreateTenantWithAdminTest | `tests/Feature/Superadmin/` | 3 | Tenant creation with admin user: valid creation, duplicate email rejection, password mismatch |
| SubscriptionTest | `tests/Feature/SuperAdmin/` | 5 | Subscription CRUD, plan change, expiration, suspension, active plan retrieval |
| PlanManagementTest | `tests/Feature/SuperAdmin/` | 4 | Plan creation, edit, delete, features JSON, limits enforcement |
| PlanLimitsTest | `tests/Feature/SuperAdmin/` | 6 | WorkOrderLimitObserver, UserLimitObserver, limits enforcement, exception messages, expired→free limits |
| ImpersonationTest | `tests/Feature/SuperAdmin/` | 5 | Start/stop impersonation, session flag, audit log, banner middleware, unauthorized blocked |
| SuperAdminDashboardTest | `tests/Feature/SuperAdmin/` | 3 | Dashboard accessible, widgets render, tenant stats |
| CheckExpiredSubscriptionsTest | `tests/Feature/` | 5 | Downgrade expired to free, subscription log, no touch active/free, count |
| WorkOrderTenantIsolationTest | `tests/Feature/Security/` | 2 | Aislamiento cross-tenant WorkOrders + Assets |
| TallerOnboardingTest | `tests/Feature/Talleres/` | — | (pendiente — wizard requiere test de integración) |
| SpatieTenantIsolationTest | `tests/Feature/Security/` | 4 | Roles/permissions aislados entre tenants, global scope filtra, RLS policies existen |
| SpatiePermissionBypassTest | `tests/Feature/Security/` | 5 | Scope bloquea cross-tenant, creating event evita huérfanos, SQL directo muestra todo pero scope filtra, current_tenant_id() valida UUID |
| SpatieCacheIsolationTest | `tests/Feature/Security/` | 3 | Cache aislado por tenant, forgetCachedPermissions limpia ambos niveles, permisos recargan con RLS |
| TransactionTest | `tests/Feature/Transactions/` | 9 | CRUD (sale/purchase), counter atómico (FAC-XXXXX/OC-XXXXX), status transitions (draft→issued→cancelled), tenant isolation, recalculate totals |
| RegistrationTest | `tests/Feature/Auth/` | 12 | Registro (válido, duplicados, password, slug, rol owner, tenant activo) |
| PasswordResetTest | `tests/Feature/Auth/` | 7 | Forgot password, reset, token inválido, password débil |
| ApiTokenTest | `tests/Feature/Auth/` | 9 | Crear/revocar/listar tokens Sanctum, abilities, 401 |
| RegistrationWithDefaultsTest | `tests/Feature/Onboarding/` | 6 | Defaults post-registro: location, categories, items, contacts, módulos, industria default |
| TenantSuspensionTest | `tests/Feature/Security/` | 3 | VerifyTenantStatus: active → 200, inactive → 403, superadmin bypass → 200 |
| WorkOrderSprintOneTest | `tests/Feature/Talleres/` | 5 | Sprint 1: mechanic+advisor assignment, cross-tenant isolation, enum +4 values, QC fields, approval fields |
| WorkOrderItemTypeTest | `tests/Feature/Talleres/` | 4 | Sprint 2: default Part, all types accepted, enum 3 cases, tenant isolation |
| WorkOrderActivityTest | `tests/Feature/Talleres/` | 4 | Sprint 3a: create activity, tenant isolation, activities() relation, enum 4 cases |
| WorkOrderInspectionTest | `tests/Feature/Talleres/` | 5 | Sprint 3b: create inspection, tenant isolation, inspections() relation sorted, enum 3 cases, config defaults |
| WorkOrderMediaTest | `tests/Feature/Talleres/` | 12 | Sprint 4: create, tenant isolation, relations, image/pdf/video types, CASCADE, SET NULL, private visibility, storage_path UUID, disk config |
| WorkOrderObserverTest | `tests/Feature/Talleres/` | 4 | Observer + Webhook: status_change activity, from/to status, sin cambio sin actividad, webhook sin URL |
| WorkOrderFixesTest | `tests/Feature/Talleres/` | 2 | Fixes visuales: display_name por tipo, inspecciones precargadas |
| ContactDocumentTest | `tests/Feature/Talleres/` | 3 | Sprint A: document fields persist, enum 5 cases, index exists |
| InvoiceModelTest | `tests/Feature/Facturacion/` | 5 | Sprint B: create invoice, tenant isolation, items relation, work_order_id nullable, enum 4 cases |
| InvoiceCodeGeneratorTest | `tests/Feature/Facturacion/` | 3 | Sprint C: sequential document_number, per-tenant isolation, configurable prefix |
| **WorkOrderE2ETest** 🆕 | `tests/Browser/` | **3** (Dusk) | **E2E: wizard creación OT, cambio de estado, items RelationManager** |

### Reglas de gobernanza

| Documento | Propósito |
|---|---|
| `AGENTS.md` | Reglas mandatorias para agentes IA (no negociables) |
| `ARCHITECTURE_MANIFEST.md` | 5 reglas SOLID para arquitectura modular |
| `UI_UX_SPEC.md` | Sistema de diseño "Neon Garage" (Dark/Neon Premium) |
| `docs/LEARNING_GUIDE.md` | Guía de aprendizaje para el equipo |
| `docs/WORKFLOW.md` | Cómo pedir features y operar con agentes |
| `docs/features/FEATURE_SPEC_TEMPLATE.md` | Plantilla para specs de nuevos features |
| `docs/PROJECT_STATE.md` | **Este archivo** — estado actual para handoff |

---

## 3. Decisiones Arquitectónicas Fijas (NO Negociables)

| Decisión | Valor | Documento |
|---|---|---|
| ORM | Eloquent (Laravel) | AGENTS.md §3.1 |
| Multi-tenancy | PostgreSQL RLS nativo | AGENTS.md §2 |
| Inyección tenant | `set_config('app.current_tenant_id', ...)` | AGENTS.md §2.1 |
| PKs | UUID v4 (`gen_random_uuid()`) | AGENTS.md §1.1 |
| Soft delete | `deleted_at timestamptz` | AGENTS.md §2.5 |
| FORCE RLS | Obligatorio en todas las tablas con tenant_id | AGENTS.md §2.2 |
| Paquetes de tenancy | PROHIBIDOS (stancl/tenancy, spatie, etc.) | AGENTS.md §3.1 |
| Prisma | PROHIBIDO | AGENTS.md §3.1 |
| SELECT * | PROHIBIDO | AGENTS.md §3.1 |
| Nomenclatura core | Asset / Item / Contact / Transaction / Space | AGENTS.md §1.4 |
| Panel admin | Filament 5 (Schema API) | AGENTS.md §3.5 |
| Connection pooling | Session mode únicamente | AGENTS.md §6 |

---

## 4. Pendientes Inmediatos (ordenados)

| # | Tarea | Prioridad | Archivos involucrados | Depende de |
|---|---|---|---|---|---|---|---|
| **1** | ✅ **Filament Panel** | Alta | `AdminPanelProvider`, login en `/admin` | Nada |
| **2** | ✅ **Comando `tenant:create`** | Alta | `app/Console/Commands/CreateTenantCommand.php` | Nada |
| **3** | ✅ **Laravel Sanctum** | Media | `HasApiTokens` en User, migración tokens | Nada |
| **4** | 👤 **Primer tenant creado** | Hecho | `demo-company` | Comando |
| **5** | ✅ **Work Orders feature** | Alta | FEATURE_SPEC + migration + models + service + policy + form requests + Filament resource + tests | Nada |
| **6** | ✅ **Spatie Permission** | Alta | `config/permission.php`, migración roles/permisos, custom models, seeder, policy migration, Filament guards, 3 security test suites | Nada |
| **7** | ✅ **DatabaseSeeder demo** | Alta | `DatabaseSeeder` con 10 assets, 25 items, 15 contacts, 20 work orders, 15 transactions, tenant demo "Taller Mecánica Demo", admin user admin@demo.com/password | Nada |
| **8** | ✅ **Filament Resources (Asset/Item/Contact)** | Alta | `AssetResource`, `ItemResource`, `ContactResource` con List/Create/Edit, permisos Spatie, filtros, tenant scope | Resources ya existen |
| **9** | ✅ **Dashboard Widgets** | Media | `StatsOverviewWidget` (5 stats), `WorkOrderStatusChart` (bar chart), `LatestWorkOrdersTable` (5 últimas WOs) | Nada |
| **10** | ✅ **Módulo Fiscal (Transactions)** | Alta | FEATURE_SPEC + migration 2 tablas + modelos Transaction/TransactionItem + TransactionService (contador atómico en tenants.settings) + TransactionPolicy + TransactionResource + ItemsRelationManager + 9 tests | Contacts, Items, Spatie Permission |
| **11** | ✅ **Autenticación completa** | Alta | RegisterService, RegisterController, Forgot/Reset Password, ApiTokenController (Sanctum), ResetPasswordNotification, Blade views (register/forgot/reset), 28 tests, RateLimiter, custom login renderHook | Nada |
| **12** | ✅ **Onboarding post-registro** | Alta | 4 migraciones (modules_catalog, categories, locations, tenant_modules), 4 modelos (ModuleCatalog, Category, Location, TenantModule), config/industry-defaults.php (5 industrias), RegisterService con defaults automáticos, ModulesCatalogSeeder, LocationResource Filament, 6 tests | Auth completo |
| **13** | ✅ **SetTenantContext en Filament** | Alta | Agregado `SetTenantContext::class` al middleware stack de Filament en AdminPanelProvider. Sin esto, el global scope BelongsToTenant no se activaba en rutas Filament, mostrando datos del tenant demo a todos los usuarios. | Auth completo |
| **14** | ✅ **UX/UI Resources** | Media | Eliminados Grid anidados en forms (Filament Section columns bastan). Eliminados headerActions duplicados de table() (se usan solo getHeaderActions en List pages). Recreado WorkOrderResource.php y ListWorkOrders.php (estaban vacíos). | Resources existentes |
| **15** | ✅ **Panel Superadmin** | Alta | `/superadmin` sin tenant context. Middleware `EnsureIsSuperAdmin`. Migración `is_superadmin`. Login/passwordReset nativos. | Nada |
| **16** | ✅ **Deploy Tools** | Alta | `jaosoft:make-superadmin`, `jaosoft:create-tenant-admin`. Trait BelongsToTenant modificado. `tenant_id` nullable. | Nada |
| **17** | ✅ **Filament tenant()->slug** | Alta | `->tenant(Tenant::class, slugAttribute: 'slug')`. HasTenants contract. | Nada |
| **18** | ✅ **Superadmin Resources** | Alta | `TenantResource`, `GlobalAssetResource`, `GlobalWorkOrderResource`. `withoutGlobalScope('tenant')`. | Nada |
| **19** | ✅ **Tenant Suspension** | Alta | Middleware `VerifyTenantStatus` bloquea tenants inactivos. Vista `errors/tenant-suspended`. 3 tests. | Nada |

---

## 5. Reglas de Operación para Agentes

### Activación de migraciones

**"APROBADO"** es la única palabra que autoriza ejecutar `php artisan migrate`.
Sin ella: **NO** ejecutar migraciones. Ni "ok", ni "dale", ni "procede".

### Flujo de trabajo

1. John dice qué necesita (lenguaje natural)
2. Agente analiza código existente y produce `FEATURE_SPEC.md`
3. John revisa y escribe "APROBADO"
4. Agente ejecuta: migración → modelo → policy → service → filament → tests
5. Agente reporta cambios en tabla

### Prohibiciones absolutas

- ❌ Escribir código en el chat (editar archivos directamente)
- ❌ Sugerir o instalar Prisma
- ❌ Usar `SELECT *` o `Model::all()`
- ❌ Crear tablas sin `tenant_id` y RLS
- ❌ Ejecutar migraciones sin "APROBADO"
- ❌ Instalar paquetes nuevos sin aprobación explícita

### Post-ejecución

Después de cada cambio, reportar:

```markdown
## Cambios aplicados

| Archivo | Operación | Notas |
|---|---|---|
| `ruta/archivo.php` | CREATE/MODIFY | Descripción |

## Verificación requerida
- [ ] php artisan migrate ejecutado
- [ ] RLS verificado
```

---

## 6. Comandos Útiles

```bash
# Migraciones
php artisan migrate                        # Ejecutar pendientes
php artisan migrate:fresh                  # Reconstruir desde cero
php artisan migrate:rollback               # Revertir último lote
php artisan migrate:status                 # Ver estado

# Seeders
php artisan db:seed --force                # Seedear datos demo
php artisan tenant:create "Nombre" slug    # Crear tenant + admin

# Deploy Tools (interactivos)
php artisan jaosoft:make-superadmin        # Crear superadmin global
php artisan jaosoft:create-tenant-admin    # Crear tenant + admin (transaccional)

# Filament
php artisan filament:install --panels      # Instalar panel admin
php artisan make:filament-resource Asset   # Crear Resource

# Testing
php artisan test --filter=TenantIsolation  # Tests de aislamiento
php artisan test                            # Todos los tests

# Laravel general
php artisan route:list                     # Ver rutas
php artisan tinker                         # Consola interactiva
php artisan make:command Nombre            # Crear comando Artisan
```

---

## 7. Handoff Log

| Fecha | Agente | Qué se hizo | Próximo paso |
|---|---|---|---|---|---|
| 2026-05-25 | opencode | Migraciones iniciales + RLS + modelos + traits + middleware + TenantModel | Crear Filament Panel + comando tenant:create + Sanctum + Spatie |
| 2026-05-25 | opencode | Filament Panel + Sanctum + comando tenant:create + HasApiTokens | Spatie Permission + Factories + Filament Resources |
| 2026-05-25 | opencode | Work Orders feature completo (migración 2 tablas + modelos WorkOrder/WorkOrderItem + YamlPolicy + WorkOrderService + form requests + FilamentResource con form/table/filters/ItemsRelationManager + 4 tests). Fixes: Filament 5 Schema API (form() usa Schema no Form, Section/Grid movidos a Filament\Schemas\Components), tipos de propiedades Resource corregidos, factories creados (Tenant, WorkOrder, WorkOrderItem), tenant() relationship en TenantModel, HasFactory en Tenant. Login sin CSS -> filament:assets publicado. 6/6 tests passing. | Spatie Permission + Factories restantes + Filament Resources restantes + Seeders |
| 2026-05-26 | opencode | Spatie Permission Opción B implementado: `config/permission.php` (teams=false, store=array), migración 5 tablas con UUID+tenant_id+RLS, custom Role/Permission models con BelongsToTenant+HasUuids, User con HasRoles+$incrementing=false+$keyType=string, WorkOrderPolicy migrada a $user->can(), ListWorkOrders con guards Filament, RolePermissionSeeder (4 roles+16 permisos), CreateTenantCommand ejecuta seeder y asigna owner. Fixes: current_tenant_id() regex acepta v4/v7, HasUuids en Role/Permission, DatabaseSeeder sin usuarios huérfanos, User con UUID config. 3 security test suites (13 tests): TenantIsolation, PermissionBypass, CacheIsolation. 18/18 tests pasando. | Filament Resources restantes (Asset/Item/Contact) |
| 2026-05-26 | opencode | Filament Resources Asset/Item/Contact (3 resources, 12 archivos). DatabaseSeeder demo: tenant "Taller Mecánica Demo", admin admin@demo.com/password, 10 assets, 25 items, 15 contacts, 20 work orders, 23 work order items. Factories actualizados (AssetFactory, ItemFactory, ContactFactory, WorkOrderFactory) con estados y datos realistas. 18/18 tests pasando. | QA funcional + Dashboard widgets |
| 2026-05-26 | opencode | Dashboard widgets: StatsOverviewWidget (5 cards: assets, items, contacts, work orders, stock bajo), WorkOrderStatusChart (bar chart por estado), LatestWorkOrdersTable (últimas 5). 18/18 tests pasando. | QA funcional en navegador |
| 2026-05-26 | opencode | Bugfixes post-QA: imports de acciones Filament 5 corregidos (`Filament\Tables\Actions\` → `Filament\Actions\` en 4 archivos), `->tenant()` eliminado de 3 Resources (global scope ya filtraba), `scopeTenant()` agregado al trait, `id` agregado a select de LatestWorkOrdersTable. 18/18 tests pasando. | Módulo Fiscal (Transactions) |
| 2026-05-27 | opencode | **Módulo Fiscal (Transactions)**: migración 2 tablas con RLS + FORCE + 7 índices, modelos Transaction/TransactionItem, TransactionService (contador atómico en tenants.settings via jsonb + RETURNING, cálculos IVA/totales, emitir/anular), TransactionPolicy, TransactionFactory/TransactionItemFactory, TransactionResource con form/table/filters/ItemsRelationManager, 4 nuevos permisos Spatie, 9 tests (CRUD, counter, transiciones, aislamiento). Fixes: `TenantFactory.settings` → DB default (evita doble encode array vs object), `TransactionService.createWithItems` → `make()` + `tenant_id` explícito, counter usa `jsonb ||` en vez de `jsonb_set`. DatabaseSeeder: +15 transacciones demo (10 ventas + 5 compras, estados variados), +~50 transaction_items. 27/27 tests pasando. | QA funcional en navegador + API REST |
| 2026-05-27 | opencode | **Autenticación completa**: RegisterService, RegisterController, ForgotPasswordController, ResetPasswordController, ApiTokenController (Sanctum), ResetPasswordNotification, 4 Blade views, 27 tests auth (registro, password reset, API tokens). Fixes: Sanctum tokenable_id BIGINT→UUID, Vite manifest para tests, Filament 5 login rate limiting nativo (sin custom Login page). 54/54 tests pasando. | Onboarding post-registro |
| 2026-05-28 | opencode | **Onboarding post-registro**: 4 migraciones (modules_catalog, categories, locations, tenant_modules), 4 modelos (ModuleCatalog, Category, Location, TenantModule), config/industry-defaults.php (5 industrias: mechanic, restaurant, construction, clinic, general), RegisterService extendido con createDefaults (location + categories + items + contacts + módulos), campo industry en formulario registro, ModulesCatalogSeeder, LocationResource Filament (List/Create/Edit con badge Principal), 6 tests defaults. 60/60 tests pasando. | QA funcional + wizard onboarding opcional |
| 2026-05-28 | opencode | **Fix SetTenantContext en Filament**: middleware agregado al stack de Filament (AdminPanelProvider). Sin esto el global scope BelongsToTenant no filtraba datos en rutas Filament porque el panel usa middleware stack propio que no hereda el grupo `web`. Todos los usuarios veían datos del tenant demo. | UX/UI Resources |
| 2026-05-28 | opencode | **UX/UI Resources**: Grid anidado eliminado de 4 Resources (inputs apilados → Section columns directo). headerActions duplicados eliminados de 5 Resources (doble botón New Asset). WorkOrderResource.php y ListWorkOrders.php recreados (0 líneas → contenido completo). 60/60 tests pasando. | Documentación |
| 2026-05-28 | opencode | **Pulido WorkOrderResource**: Sidebar navigation i18n (6 Resources convertidos a métodos `getNavigationGroup`/`getNavigationLabel`), async search en item_id (`getSearchResultsUsing` con LIKE name+sku, limit 15, tenant scope), subtotal row reactivo, total_amount con `Text::make`, `onBlur: true` en quantity/unit_price, validación de stock (`item_type === 'product'` → cantidad ≤ stock real). Fix: `scheduled_at` → `started_at` (field name mismatch con migración). 60/60 tests pasando. | Pulido UI |
| 2026-05-28 | opencode | **Unificación recepción WorkOrder**: `createOptionForm` en contact_id (name, phone, tax_id) y asset_id (name, asset_type, metadata.marca/modelo). Sección Metadatos (KeyValue) reemplazada por Inspección de Ingreso con campos estructurados en metadata JSONB (kilometraje, nivel_bateria, notas_esteticas). Sin migraciones. 60/60 tests pasando. | Nuevos features |
| 2026-05-28 | opencode | **Panel Superadmin**: Nuevo panel `/superadmin` sin `SetTenantContext` (datos globales). Middleware `EnsureIsSuperAdmin` verifica flag `is_superadmin` en users. Migración nueva columna `is_superadmin boolean NOT NULL DEFAULT false`. Login + password reset nativos. Sin conflictos con panel admin. 60/60 tests pasando. | Superadmin |
| 2026-05-28 | opencode | **Deploy Tools**: comandos `jaosoft:make-superadmin` (crea superadmin global con tenant_id=null, is_superadmin=true) y `jaosoft:create-tenant-admin` (transaccional: Tenant→context→User→roles). Trait BelongsToTenant modificado para saltar exception si is_superadmin=true. Migración `tenant_id` nullable en users. 60/60 tests pasando. | Vistas/resource superadmin (gestión global de tenants) |
| 2026-05-28 | opencode | **Filament tenant()->slug**: `->tenant(Tenant::class, slugAttribute: 'slug')` en AdminPanelProvider. URLs cambian a `/admin/{slug}/...`. Rutas Filament ahora requieren parámetro `{tenant}`. 60/60 tests. | Implementar HasTenants en User |
| 2026-05-28 | opencode | **HasTenants contract**: User implementa `Filament\Models\Contracts\HasTenants`. `getTenants()` devuelve todos los tenants para superadmin, `[$this->tenant]` para usuarios normales. `canAccessTenant()` valida por `$this->tenant_id`. Fix: `Support\Collection` vs `Eloquent\Collection`. 60/60 tests. | — |
| 2026-05-28 | opencode | **Fix route tenant param**: `DemoStatsOverview` reemplaza `route('filament.admin.resources.*')` por `XxxResource::getUrl('index')` que resuelve `{tenant}` automáticamente. 60/60 tests. | — |
| 2026-05-28 | opencode | **Superadmin Resources**: `TenantResource` (CRUD completo: tabla, formulario, filtros, badges), `GlobalWorkOrderResource` (read-only global, columna tenant.name, sin scope de tenant), `GlobalAssetResource` (read-only global, columna tenant.name, sin scope de tenant). 3 resources en menú izquierdo del panel rojo `/superadmin`. Rutas verificadas. 60/60 tests pasando. | Vistas/resource superadmin propias |
| 2026-05-28 | opencode | **Tenant Suspension**: Middleware `VerifyTenantStatus` bloquea `/admin/{slug}` si `tenant->is_active === false`. Superadmins bypass. Vista personalizada `errors/tenant-suspended.blade.php`. 3 tests. Pipeline: SetTenantContext → VerifyTenantStatus. 63/63 tests, 168 assertions. | — |
| 2026-06-03 | opencode | **Arquitectura Modular (DDD Lite)**: ARCHITECTURE_MANIFEST.md creado con 5 reglas SOLID. R-01 agregado a AGENTS.md. Asset, WorkOrder, WorkOrderItem movidos a app/Modules/Talleres/Models/. WorkOrderService movido a app/Modules/Talleres/Services/. CreateAssetAction y CreateWorkOrderAction creados en app/Modules/Talleres/Actions/. TalleresServiceProvider creado y registrado. Factories con $model property corregido. 80/80 tests, 205 assertions. 3 commits. | Documentar migración legacy restante |
| 2026-06-03 | opencode | **UX/UI Fase 2 — Neon Garage**: UI_UX_SPEC.md creado con sistema de diseño Dark/Neon Premium. talleres-theme.css con variables neon + @source. 9 Blade Components (PlateBadge, StatusDot, GlassCard, DataRow, NeonButton, GlowInput, MetricTile, TimelineStep) — todos dumb, cero lógica de negocio. TalleresServiceProvider extendido: loadViewsFrom + Blade::componentNamespace('talleres'). Wizard de 3 pasos (Identidad → Workflow → Lanzamiento) con TallerOnboarding page, datos persistidos en tenants.metadata vía DB::transaction. AdminPanelProvider registra TallerOnboarding::class. @source agregado a app.css para escaneo Tailwind de módulos. 80/80 tests, 205 assertions. 0 regresiones. | Probar wizard en navegador |
| 2026-06-03 | opencode | **VIN + Owner en Assets**: migración `add_vin_and_owner_to_assets` (vin VARCHAR(100), owner_id UUID FK→contacts, índices UNIQUE tenant+vin + tenant+owner). Modelo Asset actualizado (fillable + owner()+workOrders()). Resource AssetResource actualizado (vin + owner_id form/table). 4 tests nuevos en AssetTallerTest + 4 en AssetVinOwnerTest. 88/88 tests, 222 assertions. | WorkOrderStatusEnum |
| 2026-06-03 | opencode | **WorkOrderStatusEnum**: `app/Enums/WorkOrderStatusEnum.php` creado con 8 cases (Draft→Delivered) + HasLabel/HasColor de Filament. Cast agregado a WorkOrder.model. WorkOrderStatusChart migrado a labels/colors dinámicos desde enum. Factory usa `randomElement(WorkOrderStatusEnum::cases())`. Tests corregidos para compatibilidad con enum. 0 migraciones (strings en BD). 88/88 tests, 222 assertions. | VehicleTypeEnum + ServiceCatalog |
| 2026-06-03 | opencode | **VehicleTypeEnum + ServiceCatalog**: `app/Enums/VehicleTypeEnum.php` con 7 tipos de vehículo (Sedan→Other) + HasLabel/HasColor. Asset.model +vehicle_type fillable/cast. AssetResource +Select vehicle_type. 2 migraciones: `add_vehicle_type_to_assets` + `create_service_catalogs_table` (con RLS). ServiceCatalog.model creado. ServiceCatalogResource con List/Create/Edit. 4 tests. 92/92 tests, 228 assertions. | ServiceCatalog seeder |
| 2026-06-03 | opencode | **ServiceCatalog seeder mechanic**: service_catalogs agregado a config/industry-defaults.php (5 servicios: cambio aceite, frenos, diagnóstico, alineación, sincronización). TenantTemplateSeeder extendido con foreach firstOrCreate para ServiceCatalog. 5 tests en ServiceCatalogTest. 93/93 tests, 234 assertions. | SDD Features |
| 2026-06-04 | opencode | **Registration redirect fix**: `RegisterController.php` cambiado de `redirect()->intended('/admin')` a `redirect()->to('/admin')` para evitar redirects no intencionados a `/items`. Todos los 12 RegistrationTest pasan. | SDD Features |
| 2026-06-04 | opencode | **Bugfix WorkOrderResource asset Select TypeError**: `plate` como titleAttribute causaba TypeError si plate=null. Cambiado a `getOptionLabelFromRecordUsing()` con `name`. Status Select: hardcoded `['pending'=>...]` reemplazado por `WorkOrderStatusEnum::class`. Default cambiado de `'pending'` a `'received'`. LatestWorkOrdersTable status column: match/case/when reemplazado por `$state->getColor()`/`$state->getLabel()`. Commit 5abf8a8. | SDD Features |
| 2026-06-04 | opencode | **BelongsToTenant fallback**: cuando TenantManager no tiene contexto (Livewire Repeater requests), usa `Auth::user()->tenant_id` en vez de lanzar RuntimeException. Necesario para crear WorkOrderItem child records. | SDD Features |
| 2026-06-04 | opencode | **FEATURE 1 — TenantResource con admin user**: Sección admin (admin_name, admin_email, admin_password, admin_password_confirmation) en TenantResource form. `handleRecordCreation` transaccional: create Tenant → setTenantContext → RolePermissionSeeder::run() → create User con password hasheado → assignRole('owner') → TenantTemplateSeeder seed. 3 tests en CreateTenantWithAdminTest. | SDD Features |
| 2026-06-04 | opencode | **FEATURE 2 — Modal inline WorkOrderResource**: `contact_id` Select con `->createOptionUsing()` callback que crea Contact con contact_type='client'. Asset_id Select renombrado a "Vehículo" con createOptionForm (name, plate, VehicleTypeEnum) + `->createOptionUsing()` que crea Asset con asset_type='vehicle', status='active'. Tests en WorkOrderTallerTest. | SDD Features |
| 2026-06-04 | opencode | **Bugfix admin_email unique on Edit**: Section admin fields `->visibleOn('create')` + `->unique('users', 'email', ignoreRecord: true)` en admin_email. Evita SQL error `tenants.id` cross-table join en Edit. | SDD Features |
| 2026-06-04 | opencode | **HOTFIX Spatie cache poisoning**: `PermissionRegistrar` cacheaba permisos del tenant demo globalmente en el panel superadmin. `findOrCreate('view_work_orders')` retornaba permiso del tenant demo sin crear uno nuevo → `Permission::all()` devolvía 0 → `role_has_permissions` vacío → owner role sin permisos → 403 + sidebar sin resources. Fix: `forgetCachedPermissions()` en 3 lugares (seeder, createTenant, registerTenantAction). Commit 81a3cc2 → merge 7c210d7. 98 tests, 260 assertions, 0 regresiones. | — |
| 2026-06-04 | opencode | **Sprint 1 — WorkOrder Core Fields**: +4 estados en WorkOrderStatusEnum (WaitingParts, WaitingApproval, Qc, Paused). Migración `add_sprint1_fields_to_work_orders` (+10 columnas nullable: mechanic_id/advisor_id FK→contacts, reception_notes, fuel_level, diagnosis_summary, approval_channel, approval_at, qc_passed, qc_notes, delivery_at + 2 índices parciales). WorkOrder model +fillable/casts/relaciones mechanic()+advisor(). WorkOrderResource +4 Sections (Asignación, Recepción, Diagnóstico, QC) + columna mechanic en tabla. 5 tests nuevos. 103 tests, 283 assertions. | Sprint 2 — WorkOrderItem type (SERVICE/PART/LABOR) |
| 2026-06-04 | opencode | **Sprint 2 — WorkOrderItem type**: WorkOrderItemTypeEnum (Part/Service/Labor + HasLabel/HasColor). Migración `add_type_to_work_order_items` (ALTER TABLE work_order_items + type VARCHAR(50) DEFAULT 'part'). WorkOrderItem model +type fillable + cast. Factory +asService/+asLabor states. WorkOrderResource Repeater +Select type (col-span-1). ItemsRelationManager +badge type column. 4 tests. 107 tests, 293 assertions. | — |
| 2026-06-04 | opencode | **Sprint 3a — work_order_activities**: WorkOrderActivityTypeEnum (4 cases: StatusChange/Note/Assignment/Qc). Migración `create_work_order_activities_table` (con RLS + FORCE, JSONB metadata, índices, sin deleted_at). WorkOrderActivity model (sin SoftDeletes, BelongsToTenant + HasUuids, casts type/metadata). WorkOrder +activities() HasMany relation. WorkOrderActivityFactory con states asNote/asAssignment. ActivitiesRelationManager read-only (badge type, description, user, created_at since, defaultSort desc). 4 tests. 111 tests, 304 assertions. | — |
| 2026-06-04 | opencode | **Sprint 3b — work_order_inspections**: InspectionItemStatusEnum (Ok/Damaged/Missing + HasLabel/HasColor). Migración `create_work_order_inspections_table` (RLS + FORCE, item_name, status, notes, photo_path reservado, sort_order). WorkOrderInspection model (sin SoftDeletes). config/inspection-defaults.php (13 ítems). WorkOrder +inspections() HasMany sorted. InspectionsRelationManager con Create/Edit, Select enum, notes condicional, badge color. 5 tests. 116 tests, 318 assertions. | — |
| 2026-06-04 | opencode | **Sprint 4 — work_order_media**: MinIO en Docker Compose + disco S3 en filesystems.php. Migración `create_work_order_media_table` (RLS + FORCE, storage_path UUID-based, metadata JSONB, índices). WorkOrderMedia model (sin SoftDeletes). MediaService (upload/delete/temporaryUrl con sanitización). WorkOrder +media()/+generalMedia(). WorkOrderInspection +media(). MediaRelationManager con FileUpload→MinIO. InspectionsRelationManager +media_count badge + ViewAction. Config inspection-defaults.php. `photo_path` deprecated (no eliminado). 12 tests. 128 tests, 337 assertions. 0 regresiones. | — |
| 2026-06-06 | opencode | **Bugfix item_id nullable**: `work_order_items.item_id` NOT NULL constraint violaba al guardar WorkOrder con items `type=service` (item_id=null). Migración `2026_06_06_000001` hace `item_id` nullable. Validación condicional en Repeater: `item_id` required solo cuando `type=part`, `service_catalog_id` required solo cuando `type=service`. Tests: crea items part+service+labor + FK inválido. 143 tests, 380 assertions. 0 regresiones. | — |
| 2026-06-06 | opencode | **Fase 1 — WorkOrderCodeGenerator unificado**: Creado `WorkOrderCodeGenerator` (servicio con `lockForUpdate()` + transacción). Inyectado via constructor en `CreateWorkOrder.php` (Filament). Eliminado dead code: `CreateWorkOrderAction`, `WorkOrderService`, `CreateWorkOrderRequest`, `UpdateWorkOrderRequest`. `TalleresServiceProvider` limpio de dead singletons. 4 tests nuevos. 147 tests, 386 assertions. 0 regresiones. | — |
| 2026-06-06 | opencode | **Fase 2 — Wizard 3 pasos**: Convertido form monolítico de WorkOrderResource a Wizard en CreateWorkOrder. Extraídos `step1Schema/2/3()` estáticos. EditWorkOrder mantiene form plano. 4 tests nuevos (WorkOrderWizardTest). 151 tests, 393 assertions. 0 regresiones. | — |
| 2026-06-06 | opencode | **Labor usa ServiceCatalog**: Eliminado `labor_description` (TextInput libre) del Repeater. `service_catalog_id` ahora visible para type=service y type=labor. Label → "Servicio / Mano de obra". Sin cambios en modelo ni tests. 151 tests, 393 assertions. 0 regresiones. | — |
| 2026-06-06 | opencode | **Fase 3 — Metadata → columnas reales + índice code + contacto duplicado**: Migración `add_inspection_fields_and_code_index` (mileage_km, battery_level, aesthetic_notes + UNIQUE INDEX tenant_id+code). WorkOrder fillable + cast. WorkOrderResource step1: inspection fields moved from metadata JSONB. step3: metadata fields removed. contact_id createOptionUsing → firstOrCreate por teléfono. 3 tests nuevos. 154 tests, 399 assertions. 0 regresiones. | — |
| 2026-06-06 | opencode | **VERTICAL TALLERES — COMPLETO**: Observer + Webhook n8n (WorkOrderObserver + WorkOrderWebhookService + config/talleres.php + 4 tests). Fixes visuales (ItemsRelationManager display_name, inspección en step1, precarga 13 defaults). 160 tests, 451 assertions, 0 regresiones. | Dusk E2E |
| 2026-06-06 | opencode | **Laravel Dusk E2E**: Instalación (composer + artisan dusk:install). Selenium service en compose.yaml. `.env.dusk.local` + `DUSK_DRIVER_URL` en `.env.example`. `loginAsTenantUser()` helper en DuskTestCase. 3 tests E2E críticos: wizard creación OT, cambio de estado + Save, items RelationManager con nombre service catalog. 160 Feature + 4 Dusk tests. 0 regresiones. |
| 2026-06-07 | opencode | **Sprint A — Contact document fields**: Migración `add_document_fields_to_contacts` (+3 columnas nullable + índice parcial). `DocumentTypeEnum` (CC/NIT/CE/PAS/TI). Contact fillable/cast actualizados. ContactResource +Section "Identificación" en form + columna toggleable document_number en table. WorkOrderResource createOptionForm de contact_id +3 campos opcionales. 3 tests nuevos. 163 Feature tests, 465 assertions. 0 regresiones. | Módulo Facturación |
| 2026-06-07 | opencode | **Sprint B — Tablas invoices + invoice_items + RLS**: Migraciones `create_invoices_table` (20 cols + RLS + 4 índices) y `create_invoice_items_table` (14 cols + RLS + 2 índices, sin deleted_at). `InvoiceStatusEnum` (Draft/Issued/Paid/Cancelled), `InvoiceDocumentTypeEnum` (Invoice/CreditNote). Modelos Invoice (TenantModel) + InvoiceItem (Model, sin SoftDeletes). InvoiceFactory (3 estados) + InvoiceItemFactory. `FacturacionServiceProvider` registrado en bootstrap/providers.php. 5 tests nuevos. 168 Feature tests, 481 assertions. 0 regresiones. | Filament Resource Invoice |
| 2026-06-07 | opencode | **Sprint C — InvoiceCodeGenerator + InvoiceResource**: `InvoiceCodeGenerator` con DB lock (mismo patrón que WorkOrderCodeGenerator). `InvoiceResource` Filament con form (Encabezado + Ítems Repeater con cálculos live + Totales readonly) + table (document_number, contact, status badge, grand_total). Pages ListInvoices, CreateInvoice (handleRecordCreation llama generator), EditInvoice. WorkOrder +invoices() HasMany. 3 tests nuevos. 171 Feature tests, 493 assertions. 0 regresiones. | — | `vendor/bin/sail up -d` (levanta selenium) → `vendor/bin/sail dusk` |
| 2026-06-07 | opencode | **Bug Hunt 403 en Livewire Selects**: Diagnóstico de 403 persistente en `getSearchResultsUsing` de Filament Selects. 3 root causes identificadas y corregidas: (1) Missing `ContactPolicy` — Laravel 11+ deniega por defecto → `Policy::create()` returns `allow()`. (2) `SetTenantContext` llamaba `clearTenantContext()` en `finally` — el contexto PostgreSQL se limpiaba antes de que Livewire procesara `getSearchResultsUsing` → RLS bloqueaba queries. (3) `PreventRequestForgery` en `->middleware()` del panel — Filament aplica su propio stack, no respeta excepción livewire* de bootstrap/app.php. Fixes: `ContactPolicy` creado, `clearTenantContext` eliminado de `SetTenantContext`, `PreventRequestForgery` removido del middleware del panel. Fixes adicionales: `Auth::user()->fresh()->tenant_id` evita tenant_id stale, `createOptionForm` removido de contact_id/asset_id Selects (users crean desde módulos dedicados), `VehicleFormSchema` enums reemplazados por arrays manuales, ContactResource duplicados limpiados, AssetResource name opcional. OpCache reseteado. **174 tests, 497 assertions — 0 regresiones.** | — | — |
| 2026-08-26 | opencode | **Expiry downgrade to free (Option A)**: CheckExpiredSubscriptions now downgrades expired subscriptions to free plan + creates SubscriptionLog audit entry. Observers enforce free plan limits during the hourly window (previously expired = unlimited access). PlataformaServiceProvider registers the command. New migration makes subscription_logs.changed_by nullable for automated changes. SelectFilter dot notation fix for empty tenants list. 7 new tests. **505 tests, 1153 assertions — 0 regresiones.** Commits: bdf6d3b, 5782ba1. | — |

## 8. Arquitectura Modular (DDD Lite)

A partir de 2026-06-03, el proyecto migra a `app/Modules/{Modulo}/` siguiendo el Manifiesto en `ARCHITECTURE_MANIFEST.md`.

```
app/Modules/
├── Talleres/
│   ├── Models/
│   │   ├── Asset.php              ← movido desde app/Models/Asset.php
│   │   ├── WorkOrder.php          ← movido desde app/Models/WorkOrder.php
│   │   ├── WorkOrderItem.php      ← movido desde app/Models/WorkOrderItem.php
│   │   ├── WorkOrderActivity.php  ← nuevo (log de actividad, sin SoftDeletes)
│   │   ├── WorkOrderInspection.php← nuevo (checklist recepción, sin SoftDeletes)
│   │   └── WorkOrderMedia.php    ← nuevo (archivos MinIO, sin SoftDeletes)
│   │   └── ServiceCatalog.php     ← nuevo (catálogo de servicios)
│   ├── Actions/
│   │   ├── CreateAssetAction.php
│   │   └── RegisterTenantAction.php
│   │   └── (CreateWorkOrderAction → eliminado 2026-06-06, dead code)
│   ├── Services/
│   │   ├── MediaService.php
│   │   ├── WorkOrderCodeGenerator.php ← nuevo (2026-06-06)
│   │   └── WorkOrderWebhookService.php ← nuevo (2026-06-06, fire-and-forget HTTP POST n8n)
│   ├── Observers/
│   │   └── WorkOrderObserver.php ← nuevo (2026-06-06, status_change activity + webhook dispatch)
│   ├── Http/
│   │   └── Pages/
│   │       └── TallerOnboarding.php ← wizard 3 pasos (Fase 2 UX/UI)
│   ├── Resources/
│   │   ├── css/
│   │   │   └── talleres-theme.css ← variables neon + @source tailwind
│   │   └── Views/
│   │       ├── Components/
│   │       │   ├── PlateBadge.blade.php     ← placa con glow emerald
│   │       │   ├── StatusDot.blade.php      ← punto animado x estado
│   │       │   ├── GlassCard.blade.php      ← card glassmorphism
│   │       │   ├── DataRow.blade.php        ← label + valor mono
│   │       │   ├── NeonButton.blade.php     ← 4 variantes con glow
│   │       │   ├── GlowInput.blade.php      ← input con neon focus
│   │       │   ├── MetricTile.blade.php     ← ficha de métrica
│   │       │   └── TimelineStep.blade.php   ← timeline vertical
│   │       ├── pages/
│   │       │   └── taller-onboarding.blade.php
│   │       └── layouts/                    ← (futuro)
│   └── Providers/
│       └── TalleresServiceProvider.php      ← registra views + blade components
├── Facturacion/
│   ├── Models/
│   │   ├── Invoice.php          ← nuevo (Sprint B, TenantModel con SoftDeletes)
│   │   └── InvoiceItem.php      ← nuevo (Sprint B, Model sin SoftDeletes)
│   ├── Services/
│   │   └── InvoiceCodeGenerator.php ← nuevo (Sprint C, DB lock, singleton)
│   └── Providers/
│       └── FacturacionServiceProvider.php ← registrado en bootstrap/providers.php
├── Ventas/        ← (futuro)
├── Inventario/    ← (futuro)
└── ...
```

### Reglas de migración
- Cada bloque se mueve con su Factory y Policy
- Factories requieren `$model` property o `newFactory()` para mantener resolución correcta
- Los `use` statements se actualizan en todos los archivos que importan los modelos
- Cada bloque se commit por separado para permitir rollback granular

## 9. Notas Técnicas Críticas

### Campo `admin_email` — unique validation en Edit (2026-06-04)

En `TenantResource`, el campo `admin_email` usa `->unique('users', 'email', ignoreRecord: true)`. Esto es seguro en Create porque no hay record que ignorar. En Edit, `ignoreRecord: true` usa el ID del Tenant (`tenants.id`) como columna de exclusión en la query contra `users` — generando `WHERE "tenants"."id" <> $2` que falla porque `users` no tiene JOIN con `tenants`.

Fix: El Section de admin fields usa `->visibleOn('create')`. Filament **procesa validación incluso en componentes ocultos** a menos que `isHiddenAndNotDehydratedWhenHidden()` retorne true. Usar `visibleOn()` en vez de `hiddenOn()` porque el Section no necesita marcar `isDehydratedWhenHidden`.

### BelongsToTenant — Superadmin Exception

El trait `BelongsToTenant` en `app/Models/Concerns/BelongsToTenant.php` fue modificado para permitir `tenant_id = null`:

```php
static::creating(function (Model $model) {
    if (empty($model->tenant_id)) {
        $isSuperadmin = isset($model->is_superadmin) && $model->is_superadmin;
        if ($isSuperadmin) {
            return; // ← salta exception, permite tenant_id=null
        }
        // ... resto del código original
    }
});
```

### Superadmin — tenant_id nullable

- Migración `2026_05_28_032815_alter_users_table_make_tenant_id_nullable`: `ALTER COLUMN tenant_id DROP NOT NULL`
- Superadmins tienen `tenant_id = null` + `is_superadmin = true`
- `SetTenantContext` middleware retorna 403 si `tenant_id` está vacío → superadmins no pueden acceder al panel `/admin`, solo al `/superadmin`
- RLS en tabla `users` no tiene políticas, por lo que el cambio no afecta RLS

### Onboarding — Defaults Automáticos

- `config/industry-defaults.php` define 5 industrias: `general`, `mechanic`, `restaurant`, `construction`, `clinic`
- Cada industria tiene: `categories` (array de strings) e `items` (array con name, item_type, price, sku)
- `RegisterService::register()` ejecuta `createDefaults($industry)` después de crear user + asignar rol
- Defaults creados: 1 Location (Sede Principal), 4-5 Categories, 3 Items, 2 Contacts (cliente + proveedor), 3 módulos activados (inventory, transactions, contacts)
- `modules_catalog` es tabla global (sin RLS, sin tenant_id) — seedeada por `ModulesCatalogSeeder`
- `categories` y `locations` usan `TenantModel` con `SoftDeletes`
- `tenant_modules` usa `TenantModel` con `SoftDeletes` y unique constraint `(tenant_id, module_slug)`

### Filament 5 API Changes (vs Filament 3)
- `Resource::form()` usa `Schema` no `Form`: `form(Schema $schema): Schema`
- `Section` y `Grid` están en `Filament\Schemas\Components\`, no en `Filament\Forms\Components\`
- Componentes de formulario (TextInput, Select, DatePicker, Textarea) siguen en `Filament\Forms\Components\`
- Propiedades como `$navigationIcon` requieren tipo `string|\BackedEnum|null` (no `?string`)
- Propiedades como `$navigationGroup` requieren tipo `string|\UnitEnum|null` (no `?string`)
- **Acciones de tabla movidas:** `Filament\Tables\Actions\*` → `Filament\Actions\*` (EditAction, DeleteAction, CreateAction, BulkActionGroup, DeleteBulkAction)
- **Widgets en `Filament\Widgets\`:** StatsOverviewWidget, ChartWidget (BarChartWidget, PieChartWidget, etc.), TableWidget
- Activar assets: `php artisan filament:assets`

### Base de datos
- `testing` database configurada en `phpunit.xml`
- 23 migraciones, 22 tablas con RLS + FORCE RLS, 4 políticas c/u + 1 tabla global (modules_catalog) + 1 función PG
- `users` tiene política SELECT que permite login sin contexto de tenant
- `BelongsToTenant` global scope se salta silenciosamente si no hay contexto (no lanza error)
- `sail` DB user es SUPERUSER → RLS bypasseado en desarrollo. Doble-lock: BelongsToTenant scope compensa. Fix 403 livewire: SetTenantContext ahora usa `Auth::user()->fresh()->tenant_id` + NO hace clearTenantContext en finally.
- `tenants.settings` es JSONB. Almacenar como array PHP (no json_encode) para evitar doble encode. El contador de factura vive en `settings.transactions.{type}_counter`, incrementado atómicamente vía `jsonb ||` con RETURNING.
- Spatie cache configurado como `array` (per-request, no persistente entre requests cruzados)
- Permission names son genéricos y cross-tenant idénticos (`create_work_orders`). Aislamiento por `tenant_id` FK + RLS + global scope, no por unicidad de nombre.

### Spatie Cache Poisoning — Critical Fix (2026-06-04)

`PermissionRegistrar` con `store => array` cachea permisos en memoria por request. En el panel **superadmin** (sin `SetTenantContext`), el `sail` DB user es SUPERUSER y **bypassea RLS**. Cuando `Gate` se resuelve por primera vez, `initializeCache()` carga `Permission::with('roles')->get()` y obtiene **todos los permisos de todos los tenants**.

**El bug:**
1. `Permission::findOrCreate('view_work_orders')` busca en cache → encuentra el permiso del **tenant demo**
2. Retorna ese permiso **sin crear uno nuevo** para el nuevo tenant
3. `Permission::all()` con BelongsToTenant scope + contexto del nuevo tenant → **0 resultados**
4. `$owner->givePermissionTo([])` → `role_has_permissions` vacío
5. Usuario admin tiene rol `owner` pero **0 permisos** → 403 + sidebar sin resources

**El fix (commit 81a3cc2 → merge 7c210d7):**
```php
app(PermissionRegistrar::class)->forgetCachedPermissions();
```
Llamado antes de `RolePermissionSeeder::run()` en:
- `CreateTenant::handleRecordCreation()` — creación via superadmin
- `RegisterTenantAction::execute()` — registro público
- `RolePermissionSeeder::run()` — defensivo, protege todos los callers

**Regla:** Todo código que cree permisos para un nuevo tenant DEBE limpiar el cache de Spatie primero.

### Spatie Permission — Opción B (implementada)
- `config/permission.php` → `teams = false` (no usar Spatie teams), `store = array`
- Modelos custom: `App\Models\Role`, `App\Models\Permission` (extienden Spatie + BelongsToTenant + HasUuids)
- `tenant_id` en todas las tablas con `DEFAULT public.current_tenant_id()` (para pivotes que Spatie inserta sin team_id)
- 22 tablas con RLS + FORCE RLS (previas + 5 Spatie + 2 Transactions + 4 nuevas: categories, locations, tenant_modules, service_catalogs)
- 15 test suites: Spatie (3), WorkOrders (2), Transactions (1), Auth (3), Onboarding (1), TenantSuspension (1), VIN+Owner (2: AssetTallerTest+AssetVinOwnerTest), ServiceCatalog (1), TallerWorkOrders (1: WorkOrderTallerTest), SuperadminTenant (1: CreateTenantWithAdminTest) — 143 tests total, 380 assertions
- `current_tenant_id()` PG function regex actualizada: acepta cualquier versión UUID (v4 y v7) — Laravel 13 genera v7 por defecto
- **20 permisos totales**: work_orders, assets, items, contacts, transactions (× 4 acciones c/u)
- **28 migraciones**: 27 previas + `add_inspection_fields_and_code_index_to_work_orders`

### Paneles Filament

| Panel | Path | Tenant | Middleware extra | Recursos |
|---|---|---|---|---|
| Admin | `/admin/{tenant:slug}` | ✅ `->tenant(Tenant::class, slugAttribute: 'slug')` | `SetTenantContext` → `VerifyTenantStatus` (bloquea si `is_active=false`) | 8 Resources (Asset, Contact, Item, WorkOrder, Transaction, Location, ServiceCatalog, Invoice) |
| Superadmin | `/superadmin` | ❌ Sin tenant context | `EnsureIsSuperAdmin` (403 si no superadmin) | 3 Resources (Tenant, GlobalAsset, GlobalWorkOrder) |

### Filament Multi-Tenant Architecture

- **URL con slug**: `->tenant(Tenant::class, slugAttribute: 'slug')` en AdminPanelProvider cambia las rutas de `/admin/items` a `/admin/{tenant:slug}/items`
- **HasTenants contract**: `App\Models\User` implementa `Filament\Models\Contracts\HasTenants`
  - `getTenants(Panel $panel)`: superadmin → `Tenant::all()`, normal → `collect([$this->tenant])->filter()`
  - `canAccessTenant(Model $tenant)`: superadmin → `true`, normal → `$this->tenant_id === $tenant->id`
- **Pipeline de middleware (AdminPanelProvider)**: `SetTenantContext` → `VerifyTenantStatus` → resto del stack. `SetTenantContext` inyecta `app.current_tenant_id` en PG (RLS). `VerifyTenantStatus` verifica `tenant->is_active` y bloquea con 403 si está suspendido.
- **Resource::getUrl('index')**: Los widgets y enlaces deben usar `XxxResource::getUrl('index')` en vez de `route('filament.admin.resources.*')` porque `getUrl()` incluye automáticamente el `{tenant}` resuelto

### Tenant Suspension

- Middleware `VerifyTenantStatus` en `app/Http/Middleware/VerifyTenantStatus.php`
- Usa `Filament::getTenant()` (ya resuelto por Filament desde el slug en URL) para obtener el modelo Tenant
- Si `tenant->is_active === false` y el usuario NO es superadmin → `response()->view('errors.tenant-suspended', [], 403)`
- Superadmins siempre pasan (acceso irrestricto para auditoría/soporte)
- Vista en `resources/views/errors/tenant-suspended.blade.php` — template HTML estático con Tailwind, sin dependencias de layout
- 3 tests en `tests/Feature/Security/TenantSuspensionTest.php`:
  - `test_active_tenant_user_can_access_panel` → 200
  - `test_inactive_tenant_user_is_blocked_with_403` → 403
  - `test_superadmin_bypasses_suspension_on_inactive_tenant` → 200
- **Mocking**: Tests usan `createMock` + `Filament::swap()` (no Mockery) para evitar crash PHP ^8.3 con `tempnam()`

### HasTenants Contract

```php
class User extends Authenticatable implements HasTenants
{
    public function getTenants(Panel $panel): Collection
    {
        if ($this->is_superadmin) {
            return Tenant::all();
        }
        return collect([$this->tenant])->filter();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->is_superadmin) {
            return true;
        }
        return $this->tenant_id === $tenant->id;
    }
}
```

### Comandos de Consola (Deploy Tools)

```bash
# Crear superadmin global (tenant_id=null, is_superadmin=true)
php artisan jaosoft:make-superadmin
# → Pregunta interactiva: Nombre, Email, Contraseña
# → Crea usuario sin amarre a tenant

# Crear tenant + admin (transaccional)
php artisan jaosoft:create-tenant-admin
# → Pregunta interactiva: Empresa, Slug, Admin Nombre, Admin Email, Contraseña
# → Transacción: Tenant → setTenantContext → User (owner) → commit
```

### Sistema de Autenticación

- Registro público en `/register` con selección de industria (dropdown con 5 opciones)
- Login en `/admin/login` con rate limiting nativo de Filament 5 (5 intentos)
- Password reset con token de 30 minutos, notificación por email (template Blade)
- API tokens via Sanctum: crear/revocar/listar en `/api/sanctum/token`
- Rate limiter de registro: 10 intentos/hora por IP
- Requisitos de password: mínimo 8 caracteres, 1 mayúscula, 1 número, 1 especial

### Onboarding Post-Registro

- `config/industry-defaults.php` con 5 industrias: general, mechanic, restaurant, construction, clinic
- Cada industria define categorías (4) e items (3) predeterminados
- Después del registro se crean automáticamente:
  - 1 Location "Sede Principal" (is_main=true)
  - 4-5 Categories según industria
  - 3 Items según industria
  - 2 Contacts (Cliente Ejemplo + Proveedor Ejemplo)
  - 3 TenantModules activados: inventory, transactions, contacts
- `modules_catalog` tabla global seedeada con 5 módulos

### Comandos frecuentes
```bash
# Docker/Sail
docker exec -w /var/www/html proyect-dashboard-laravel.test-1 php artisan <comando>

# Tests
docker exec -w /var/www/html proyect-dashboard-laravel.test-1 php artisan test
docker exec -w /var/www/html proyect-dashboard-laravel.test-1 php artisan test --filter=Transaction
docker exec -w /var/www/html proyect-dashboard-laravel.test-1 php artisan test --filter=WorkOrder
docker exec -w /var/www/html proyect-dashboard-laravel.test-1 php artisan migrate:fresh

# Dusk E2E
vendor/bin/sail up -d                                    # levantar todos los servicios (incluye selenium)
vendor/bin/sail dusk tests/Browser/WorkOrderE2ETest.php  # ejecutar solo los 3 tests E2E
vendor/bin/sail dusk                                     # ejecutar todos los tests Dusk

# Ver RLS
docker exec -w /var/www/html proyect-dashboard-laravel.test-1 php artisan tinker --execute="DB::select(\"SELECT tablename, rowsecurity FROM pg_tables WHERE schemaname='public'\")"
```

---

## Governance Audit — 2026-06-04 (actualizado)

### Violaciones corregidas
- **`app/Models/Permission.php:19`** — `$guarded` incompleto (faltaban `created_at`, `updated_at`, `deleted_at`)
- **`app/Models/Role.php:19`** — `$guarded` incompleto (faltaban `created_at`, `updated_at`, `deleted_at`)
- **Cross-tenant Spatie cache poisoning** — `PermissionRegistrar::initializeCache()` cargaba permisos de TODOS los tenants en el panel superadmin (sin tenant context + RLS bypass). `findOrCreate` retornaba permisos del tenant demo sin crear nuevos. Fix: `forgetCachedPermissions()` antes de cada `RolePermissionSeeder::run()`.

### Sin violaciones (verificado)
- **Filament 5**: Todas las `form()` usan `Schema $schema): Schema`. No hay `Filament\Tables\Actions\` ni `$navigationGroup` estático.
- **Tenant Isolation**: `withoutGlobalScope('tenant')` solo en recursos Superadmin. Ningún `::all()` en producción sin scope.
- **Naming Zero Redundancy**: Tablas, modelos y clases usan nombres canónicos (`Asset`, `Item`, `Contact`, `Transaction`, `Member`). Los términos prohibidos aparecen solo como valores discriminante en columnas (`asset_type`, `contact_type`, `item_type`).
- **Dependencias**: No hay Prisma, stancl/tenancy ni spatie/laravel-multitenancy.

---

## LAST_DECISION_LOG (últimas 5 decisiones)

| Fecha | Decisión | Alternativa descartada | Razón |
|---|---|---|---|---|
| 2026-06-03 | industry en metadata JSON | columna dedicada | evitar migración |
| 2026-06-03 | Boost instalado | solo CLAUDE.md manual | docs versionadas |
| 2026-06-03 | Talleres Mecánicos Fase 1 | — | assets con plate/brand/model/year + service_description en work_orders + índice único (tenant_id, plate) WHERE deleted_at IS NULL |
| 2026-06-03 | Migración a DDD Lite (ARCHITECTURE_MANIFEST.md) | todo en app/Models/ | SRP, modularidad, mantenibilidad. Asset/WorkOrder/WorkOrderItem movidos a Modules |
| 2026-06-03 | CreateAssetAction + CreateWorkOrderAction | lógica en Filament Resources | encapsular validación unique plate + generación código en casos de uso dedicados |
| 2026-06-03 | Neon Garage Design System (UI_UX_SPEC.md) | — | Dark/Neon Premium: Gray-950, acentos Cyan/Emerald, glassmorphism, glow, monospaced data. Fase 2 UX/UI |
| 2026-06-03 | VIN + Owner en Assets | `vin` en metadata JSONB | `vin` es columna directa como `plate` — no viola CLAUDE.md. Índice UNIQUE (tenant_id, vin) WHERE deleted_at IS NULL |
| 2026-06-03 | Arquitectura modular: `app/Modules/Talleres/` extiende TenantModel | Modelos duplicados | Single-table con modular models. Assets en Modules/Talleres/Models/ usa misma tabla `assets` |
| 2026-06-03 | WorkOrderStatusEnum con 8 estados | 4 estados originales | Workflow completo de taller: Draft→Received→Diagnosing→Quoted→InProgress→Completed→Delivered→Cancelled |
| 2026-06-03 | VehicleTypeEnum + ServiceCatalog | vehicle_type en metadata JSONB | Enumerar tipos mejora filtros. ServiceCatalog como tabla separada permite precios por taller con RLS |
| 2026-06-03 | ServiceCatalog integrado en TenantTemplateSeeder vía industry-defaults.php — patrón extensible a otros verticales | Seeder separado `MechanicShopTemplateSeeder` | Reutiliza el mismo patrón firstOrCreate que categories/items/assets. Mecanic obtiene 5 servicios de catálogo al registrarse |
| 2026-06-04 | TenantResource crea admin user transaccionalmente + modal inline Contact/Asset en WorkOrderResource | Crear admin manualmente después del tenant | Reducir fricción: admin_user creado en la misma transacción; Contact/Asset creados inline sin salir del formulario WorkOrder |
| 2026-06-04 | `admin_password` NO se dehydrata en el form | Marcar como dehydrated=false | El valor debe estar disponible en handleRecordCreation para hashear; solo el confirmation field se dehydrata |
| 2026-06-04 | BelongsToTenant fallback a Auth::user()->tenant_id | RuntimeException cuando no hay contexto | Livewire requests de Repeater no pasan por SetTenantContext; fallback permite crear WorkOrderItem sin explotar |
| 2026-06-04 | Spatie cache clear antes de RolePermissionSeeder | findOrCreate reusaba permisos del tenant demo | PermissionRegistrar cache global (array) en panel superadmin sin contexto → findOrCreate no creaba permisos nuevos → role_has_permissions vacío → 403 + sidebar vacío |
| 2026-06-04 | admin Section visibleOn('create') + ignoreRecord:true en admin_email unique | unique('users','email') sin ignoreRecord | Edit page validaba admin_email contra tenants.id (joins cross-table) → SQL error 42P01 |
| 2026-06-04 | RegisterTenantAction: tenant_id explícito en User::create | auto-fill via BelongsToTenant creating event | Consistencia con CreateTenant::handleRecordCreation; evita depender del orden de contexto |
| 2026-06-06 | item_id nullable en work_order_items | mantener NOT NULL + item_id forzado para service | type='service' usa service_catalog_id, no item_id. Migración DROP NOT NULL + validación condicional en Repeater |
| 2026-06-06 | WorkOrderCodeGenerator unificado con DB lock | mantener 3 copias del algoritmo de generación | DRY: 1 fuente única vs 3 duplicadas. lockForUpdate + transacción previene race conditions |
| 2026-06-06 | Inspection fields migrados de metadata JSONB a columnas reales | mantener en JSONB | kilometraje, nivel_bateria, notas_esteticas necesitan consultas directas e índices. UNIQUE INDEX (tenant_id, code) previene duplicados |
| 2026-06-06 | Contacto inline usa firstOrCreate por teléfono | Contact::create() sin validación | Evita duplicación de clientes desde el flujo de creación de OT |
| 2026-06-06 | WorkOrderObserver + WorkOrderWebhookService | Log manual + polling externo | Captura automática status_change en actividades + notificación fire-and-forget a n8n. Sin TALLERES_WEBHOOK_URL no se dispara nada. Fire-and-forget: fallo de n8n no rompe el flujo |
| 2026-06-07 | DocumentTypeEnum con 5 cases (CC/NIT/CE/PAS/TI) | string sin enum en BD | Filament HasColor+HasLabel permite Select badge y colores. Cast en Contact.model garantiza tipo seguro |
| 2026-06-07 | Índice parcial `(tenant_id, document_number) WHERE document_number IS NOT NULL AND deleted_at IS NULL` | índice simple en document_number | Consultas de búsqueda por documento son siempre por tenant. Partial evita indexar NULLs masivos + soft-delete |
| 2026-06-07 | InvoiceItem extiende Model (no TenantModel) | TenantModel con SoftDeletes | La migración no tiene deleted_at. Extender TenantModel generaría queries con `AND deleted_at IS NULL` en columna inexistente |
| 2026-06-07 | Índice UNIQUE `(tenant_id, document_number)` WHERE deleted_at IS NULL en invoices | Sin índice unique | Evita duplicados de documento contable por tenant, respetando soft-delete |
| 2026-06-07 | InvoiceCodeGenerator usa `orderBy('sequence', 'desc')->first()` en vez de `max()` | `max()` con `lockForUpdate()` | PostgreSQL rechaza FOR UPDATE con funciones agregadas. `first()` selecciona la fila real y aplica lock correctamente |
| 2026-06-07 | InvoiceResource form con cálculos live en Repeater items | Cálculos en backend al guardar | UX mejorada: subtotal/tax/total se recalculan al hacer blur en quantity/unit_price/discount/tax_rate |
| 2026-06-07 | clearTenantContext eliminado del finally en SetTenantContext | Mantener finally block | El contexto PostgreSQL se limpiaba antes de que Livewire procesara getSearchResultsUsing → RLS bloqueaba queries. Cada request entrante setea su propio contexto fresco |
| 2026-06-07 | ContactPolicy creado con create() → allow() | Sin policy (Laravel 11+ deny por defecto) | Gate::inspect('create', Contact::class) retornaba deny → 403 en cualquier createOptionForm de Contact |
| 2026-06-07 | PreventRequestForgery eliminado del middleware stack del panel | Excepción livewire* en bootstrap/app.php | Filament aplica su propio middleware stack; la excepción global no lo alcanza. Livewire 4 maneja su propio sistema de tokens CSRF |

---

## Fase 1 — Talleres Mecánicos (Core)

Implementada con éxito. Validada con 174 tests (497 assertions).
Estructura de activos y órdenes de servicio operativa con aislamiento multi-tenant total. Bug 403 en Livewire Selects resuelto — 3 root causes eliminadas.

## Fase 2 — UX/UI Neon Garage (Diseño)

Implementada con éxito. Sistema de diseño Dark/Neon Premium para el módulo Talleres.

| Componente | Archivos | Estado |
|---|---|---|
| UI_UX_SPEC.md | `UI_UX_SPEC.md` | ✅ Creado |
| Theme CSS | `app/Modules/Talleres/Resources/css/talleres-theme.css` | ✅ Creado |
| 9 Blade Components | `app/Modules/Talleres/Resources/Views/Components/*.blade.php` | ✅ Creados |
| Namespace Blade | `TalleresServiceProvider::boot()` → `Blade::componentNamespace('talleres')` | ✅ Registrado |
| View namespace | `TalleresServiceProvider::boot()` → `loadViewsFrom()` | ✅ Registrado |
| @source Tailwind | `resources/css/app.css` → `@source '../../app/Modules/**/*.blade.php'` | ✅ Agregado |
| Wizard Onboarding | `app/Modules/Talleres/Http/Pages/TallerOnboarding.php` | ✅ Creado (3 pasos) |
| Wizard View | `app/Modules/Talleres/Resources/Views/pages/taller-onboarding.blade.php` | ✅ Creada |
| Ruta en panel | `AdminPanelProvider` → `TallerOnboarding::class` | ✅ Registrada |

### Próximos pasos UX/UI
- Probar wizard de onboarding en navegador
- Aplicar componentes Neon Garage a vistas de AssetResource y WorkOrderResource
- Probar glassmorphism y glow en producción

---

*Si abres este proyecto por primera vez, lee `docs/LEARNING_GUIDE.md` para contexto completo y `docs/WORKFLOW.md` para entender cómo operar.*
