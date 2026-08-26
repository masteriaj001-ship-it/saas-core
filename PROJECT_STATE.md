# ProyectDashboard - SaaS Multitenant (Operaciones tipo Taller)

> **Version:** 1.1.89 | **Status:** active_development | **Updated:** 2026-08-26

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
  - App\Filament\Resources\ClientVehicleResource (navigationSort: 2)
  - App\Filament\Resources\WorkOrderResource (navigationSort: 4)
  - App\Filament\Resources\ContactResource
  - App\Filament\Resources\TransactionResource
  - App\Filament\Resources\ItemResource
  - App\Filament\Resources\LocationResource
  - App\Filament\Resources\ServiceCatalogResource
  - App\Filament\Resources\InvoiceResource
  - App\Filament\Resources\BudgetResource
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
- **Notes:** Custom Role/Permission models con BelongsToTenant + HasUuids. RBAC/ABAC definido por entidad. Vector C Spatie cache fix: forgetCachedPermissions() post clearTenantContext() en RegisterTenantAction + CreateTenant::afterCreate().
- **Roles:** Superadmin, Admin Tenant, Member, Cliente
- **Checklist:** `checklists/taller_permissions.yaml`

### facturacion

- **Status:** implemented
- **Last check:** 2026-08-20
- **Features:**
  - InvoiceResource con List/Create/Edit
  - InvoiceCodeGenerator con DB lock (FV-000001)
  - GenerateInvoiceFromWorkOrderAction (botón en EditWorkOrder)
  - IVA configurable por tenant (settings.es_responsable_iva)
  - PDF condicional (oculta IVA si tax_total=0)
  - Pagos por invoice: migration invoice_payments (UUID+RLS), InvoicePayment model + factory
  - PaymentMethodEnum cash/card/transfer/check/credit
  - Invoice::payments()/amountPaid()/balanceDue()/isPaid()
  - InvoiceCreationService::registerPayment() con bloqueo de sobrepago y cambio (PaymentExceedsBalanceException)
  - POS con pago -> status=paid al instante (pos_sequence)
  - Ticket térmico 80mm (ticket-pos.blade.php, window.print) + ruta invoices.ticket (protección cross-tenant 403)
  - PosPage Filament kiosko full-screen: 3 paneles (categorías/catálogo/ticket), dark mode amber
  - PosPage: búsqueda, filtro por item_type, carrito, pago modal (efectivo/tarjeta/transferencia), cálculo de cambio, atajos F2/F4/F5/F10, historial de ventas
  - PosPage: servicios se muestran sin importar stock (item_type=service sin restricción de stock)
  - Hardware POS parametrizable en TenantResource (Section Impresión): driver esc_pos/window_print, host, port, canal cajón, abrir cajón tras cobro — persistido en tenants.settings->pos_hardware
  - Modal post-pago en PosPage ('Venta registrada'): botones IMPRIMIR / VER TICKET (invoices.ticket) / Cerrar (Esc) — commit de3ce46
  - 30 tests POS+UI: PosCheckoutTest (8) + PosPageTest (20) + TenantPosSettingsTest (2)
- **Notes:** M3 POS Kiosko completado y mergeado a main (commits 3efbe70, de3ce46, f196dd2). Validado en navegador: superadmin crea/edita Section Impresión (labels, host/port/canal dinámicos por driver), POS modal post-pago con IMPRIMIR/VER TICKET/Cerrar Esc. Fixes Filament v5 al voletear: Section en Schemas (BudgetResource) y KeyValue import (ItemResource). Suite completa 404 verdes (tras single-vertical).

### work_order_reception

- **Status:** implemented
- **Last check:** 2026-08-22
- **Features:**
  - CreateWorkOrderReceptionAction con normalización Contact/ClientVehicle/WorkOrder
  - createOptionForm en Select de Cliente y Vehículo (modal de creación rápida)
  - createOptionUsing incluye contact_type=client (asset_type eliminado, reemplazado por client_vehicle_id)
  - Campos de inspección: kilometraje, batería, notas estéticas
  - Redirect a lista después de crear WO (getRedirectUrl → index)
  - Notificación en español: 'Orden de trabajo creada'
  - 5 tests PHPUnit (creación, reuso, aislamiento, ID existente)
- **Notes:** Hybrid (C) — operador ve campos planos, Action normaliza en background. client_vehicle_id FK en work_orders. asset_id nullable para activos propios del taller. asset_type removido del createOptionUsing (2026-08-22).

### client_vehicles

- **Status:** implemented
- **Last check:** 2026-08-26
- **Features:**
  - Tabla client_vehicles con RLS (owner_contact_id, plate, brand, model, year, vin, etc.)
  - Tabla vehicle_mileage_logs para historial de kilometraje
  - client_vehicle_id FK nullable en work_orders
  - Migración de datos de assets vehicle → client_vehicles
  - Eliminación de columnas de vehículo de assets (plate, vin, brand, model, etc.)
  - ClientVehicleResource con CRUD completo (Filament)
  - WorkOrderResource: selector unificado contacto + vehículo filtrado por contacto
  - WorkOrderResource: creación inline asigna owner_contact_id automáticamente
  - ClientVehicleFactory con estados sedan/suv/pickup/motorcycle
  - VehicleMileageLogFactory
  - Asset limpiado: eliminados isVehicle(), owner(), campos de vehículo
  - AssetFactory y AssetResource: tipo 'vehicle' eliminado
  - ClientVehicle model: recordMileage(), scopeByPlate(), scopeByOwner()
  - ClientVehicleTest: 7 tests (crear, owner, workOrders, mileageLogs, byPlate, byOwner, RLS)
  - VehicleMileageLogTest: 4 tests (crear, work_order link, ordering, tenant isolation)
  - ClientVehicleWorkOrderTest: 5 tests (link, hasMany, tenant isolation, nullable, forceDelete cascade)
- **Notes:** Separación completa de activos del taller (assets) de vehículos de clientes (client_vehicles). Commits f71154e, 37a1a57. asset_id se mantiene nullable en work_orders para activos propios del taller. Suite 517/517.

### caja_turnos

- **Status:** implemented
- **Last check:** 2026-08-25
- **Features:**
  - CashShift model: open/close/canOpen/addExpectedCash/subtractExpectedCash
  - CashMovement model: type enum (sale/expense/income/refund), payment_method enum
  - CashMovementService: recordSale, recordRefund, openShift, closeShift
  - CashShiftResource: lista de turnos con filtros y vista detallada
  - CajaPage: dashboard interactivo con cards de resumen, registrar gasto, cerrar turno
  - Turno abierto = indicador visual + tiempo transcurrido
  - Desglose por método de pago: efectivo, tarjeta, transferencia
  - Cálculo automático de diferencia (sobrante/faltante) al cerrar
  - Movimientos automáticos: sale al confirmar factura, refund al cancelar
  - TurnoCerradoException para validaciones
  - 13 tests PHPUnit (CashShiftTest + CajaIntegrationTest)
  - $guarded compliant con R-02 (id, tenant_id, created_at, updated_at)
- **Notes:** Módulo de caja con turnos para gestión de efectivo. Un turno abierto por tenant. Ventas se registran automáticamente desde facturas. Gastos se registran manualmente desde el dashboard.

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
  - Fase 2: columna stage (before/after) en work_order_media + enum WorkOrderMediaStageEnum + CHECK constraint
  - Fase 2: WorkOrderClosureService con transiciones validadas (gate de checklist final + fotos antes/después + firma) y audit trail
  - Fase 2: WorkOrder::hasCompleteFinalChecklist() + hasBeforeAfterPhotos()
  - 7 tests WorkOrderClosurePhase2Test (checklist, fotos, happy path, legacy, stage, firma, RLS media)
  - 17 tests PHPUnit (transiciones, SMS, legacy, RLS, fase 2)
- **Notes:** FEATURE_SPEC.md en features/work-order-closure/. 7 estados del flujo de cierre. Fase 2 completada: gate de cierre (checklist completa + fotos before/after + firma) enforcement vía WorkOrderClosureService con audit trail en activities. RLS en sms_codes. 395 tests total.

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
  - SuperadminPanelProvider: multiFactorAuthentication(isRequired: false)
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

### presupuestos

- **Status:** implemented
- **Last check:** 2026-06-18
- **Features:**
  - Budget + BudgetItem models con BelongsToTenant, HasUuids, SoftDeletes
  - BudgetStatusEnum: Draft/Sent/Approved/Rejected con transiciones estrictas
  - BudgetConversionService: Budget aprobado -> Contact + Asset + WorkOrder
  - BudgetResource Filament CRUD con Send/Approve/Reject actions
  - BudgetFactory con state 'sent'
  - BudgetCodeGenerator (via WorkOrderCodeGenerator post-conversion)
  - 8 tests (5 AppScope + 3 RLS)
- **Notes:** El fix de TenantManager singleton es retroactivo — afecta a TODOS los modelos con BelongsToTenant desde el inicio del proyecto.

### quote_approval

- **Status:** implemented
- **Last check:** 2026-06-19
- **Features:**
  - WorkOrderStatusEnum extendido: Approved/Rejected con labels y colores
  - RequestQuoteApprovalAction: status→WaitingApproval, retorna signed URL (7 días)
  - QuoteApprovalController: show/approve/reject/approved/rejected
  - 3 vistas Blade mobile-first (show, approved, rejected) sin Filament
  - WorkOrderItemObserver: revertir a quoted si items cambian en WaitingApproval
  - Filament Action 'Solicitar Aprobación' en EditWorkOrder (modal con URL copiable)
  - CSRF exceptions para rutas públicas signed
  - 8 tests TDD (envío, aprobación, rechazo, doble-click, firma inválida, expirada, reversion de items)
- **Notes:** MVP sin migración nueva. approval_at y approval_channel ya existían en work_orders. rejection_reason en metadata JSONB.

### notifications

- **Status:** implemented
- **Last check:** 2026-06-19
- **Features:**
  - notifications table migration (uuidMorphs para User con UUID)
  - Filament database notifications bell con polling 30s en AdminPanel
  - WorkOrderApprovedNotification: canal database, check-circle icon, link a WO edit
  - WorkOrderRejectedNotification: incluye rejection_reason en body
  - WorkOrderObserver: detecta waiting_approval→approved/rejected, notifica a owner/editor del tenant
  - 7 tests (envío, razón, cross-tenant isolation, formato, sin elegibles, persistencia)
- **Notes:** Cierra el loop operativo: cliente aprueba online → asesor ve campanita en Filament. 433 tests total.

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

### rate_limiting

- **Status:** implemented
- **Last check:** 2026-06-20
- **Features:**
  - 3 named RateLimiters: forgot-password (3/hora/IP), reset-password (5/hora/IP), login (5/min/email+IP)
  - throttle middleware en POST /forgot-password y POST /reset-password
  - throttle:5,1 en POST /api/sanctum/token
  - Login rate limiting nativo Filament (5/60s por IP, Livewire trait)
  - 3 tests (forgot throttle, reset throttle, IP isolation)
- **Notes:** Cierra vector email bombing + token brute force. Login ya estaba protegido por Filament nativo.

### inventario

- **Status:** implemented
- **Last check:** 2026-06-22
- **Features:**
  - Warehouse model + RLS + partial unique index (1 default per tenant)
  - StockMovement model con RLS, HasUuids, MovementTypeEnum (entry/exit/transfer/adjustment/initial)
  - AdjustItemStockAction (único punto de creación de movimientos, wrapped en DB::transaction)
  - StockSyncService (recalcular cache item.stock desde movimientos)
  - Item: defaultWarehouse(), isLowStock(), hasStock(), stockMovements() HasMany
  - TransactionService::issue() descuenta stock vía AdjustItemStockAction
  - TransactionService::cancel() repone stock
  - RolePermissionSeeder actualizado con warehouses, stock_movements (8 permisos)
  - WarehouseFactory con estados default(), inactive()
  - WarehouseResource Filament CRUD (List/Create/Edit)
  - StockMovementsRelationManager read-only en ItemResource
  - Ajuste Rápido Action en ItemResource (modal con bodega, tipo, cantidad, motivo)
  - Artisan command inventory:migrate-legacy-stock
  - 22 tests (7 WarehouseAppScope, 9 StockMovementAppScope, 4 WarehouseRls, 2 StockMovementRls, 4 TransactionStockIntegration)
- **Notes:** Fase 1 Foundation + Fase 2 Transferencias completa. TransferStockAction con TransferOut/TransferIn atómico + transfer_group_id. WarehouseResource con Transferir Stock + Ajustar Stock actions. 39 tests Inventario.

### financial_dashboard

- **Status:** implemented
- **Last check:** 2026-08-08
- **Features:**
  - FinancialStatsOverview: KPI cards (ventas hoy, ingresos mes, ticket promedio, ingresos semana)
  - DailySalesChart: bar chart Chart.js ingresos últimos 7 días
  - TopItemsWidget: top 5 artículos más vendidos del mes
  - PaymentMethodsBreakdown: desglose por método de pago (efectivo/tarjeta/transferencia/cheque/crédito) con porcentajes
  - 14 tests FinancialDashboardTest (stats, chart, top items, payment methods, tenant isolation)
- **Notes:** Dashboard financiero tenant-scoped. 4 widgets auto-discovered en Dashboard. TopItems usa StatsOverviewWidget (no TableWidget) para evitar GROUP BY con UUID en PostgreSQL.

### superadmin_panel_plans

- **Status:** implemented
- **Last check:** 2026-08-26
- **Features:**
  - 6 migrations: plans, subscriptions, subscription_logs, impersonation_logs, seed_default_plans_and_subscriptions, drop_plan_column, alter_subscription_logs_changed_by_nullable
  - PlanSeeder: free/pro/enterprise
  - 4 models: Plan, Subscription, SubscriptionLog, ImpersonationLog
  - Tenant: removed plan from fillable, subscription() HasOne, getPlanNameAttribute accessor
  - SubscriptionService: changePlan, getActivePlan, isExpired, isSuspended, isActive
  - ChangePlanAction: transaccional con validación de plan existente
  - PlanLimitExceededException, TenantSuspendedException
  - WorkOrderLimitObserver, UserLimitObserver: enforce plan limits (expired → free plan limits)
  - CheckExpiredSubscriptions artisan command: downgrades expired to free + SubscriptionLog audit
  - ImpersonationService: database + session flag
  - StartImpersonationAction, StopImpersonationAction
  - ImpersonationBanner middleware
  - SuperAdminDashboard: compact stats (no icons), doughnut chart (half width, 250px, maintainAspectRatio false), recent activity + churn risk tables (maxHeight 200px)
  - PlanResource CRUD, UserResource list, ViewTenant page
  - TenantResource: impersonate action, planName accessor, subscription filter (custom key)
  - 26 tests (SubscriptionTest, PlanManagementTest, PlanLimitsTest, ImpersonationTest, SuperAdminDashboardTest, CheckExpiredSubscriptionsTest)
  - SuperadminPanelProvider: FilamentInfoWidget removed, MFA optional
- **Notes:** FASE 1-6 completadas + expiry downgrade. CheckExpiredSubscriptions downgrades expired subscriptions to free plan (Option A). Observers enforce free plan limits during hourly window. 7 migrations total. Suite 505/505.

## Test Suite

- **Total tests:** 517
- **Passing:** 517
- **Assertions:** 1174
- **Status:** green
- **Last run:** 2026-08-26

## Deployment

- **Platform:** Railway
- **URL:** https://saas-core-production-7165.up.railway.app
- **Custom domain:** pending (decidir nombre de proyecto antes del dominio)
- **Database:** PostgreSQL Railway (host postgres.railway.internal, user sin BYPASSRLS = RLS activo)
- **Fixes applied:**
  - Multi-stage Dockerfile: vendor + assets (node:22) + runtime (php:8.5-fpm-alpine + nginx)
  - APP_URL=https://... en Railway Variables (https, no http) — resolvio Mixed Content de Livewire
  - bootstrap/app.php: $middleware->trustProxies(at: '*') — Railway termina TLS en proxy, X-Forwarded-Proto
  - nginx-default.conf: try_files $uri /index.php en bloque de estáticos — /livewire-*/livewire.min.js se sirve via PHP (antes 404 nginx)
  - entrypoint.sh: livewire:publish --assets sin --force (flag no existe en v5), removido route:cache (Livewire registra rutas de assets dinámicamente)
  - User model implementa FilamentUser (canAccessPanel) — sin eso el panel admin da 403 en production
  - Railway no soporta --build-arg; todas las env via dashboard
- **Open items:**
  - [ ] Obtener dominio propio y agregar como Custom Domain en Railway (Networking)
  - [ ] MFA no configurada para admin@demo.com aún (opcional)
  - [ ] Fallback DB config en container: verificar DB_HOST desde DATABASE_URL en cada boot (entrypoint ya parsea)

## Architecture Rules

- **naming_zero_redundancy:** ✅
- **tenant_isolation_double_layer:** ✅
- **spatie_teams_disabled:** ✅
- **superadmin_tenant_id_null:** ✅
- **uuid_pks:** ✅
- **soft_deletes_exceptions:** ✅
- **module_activation_system:** ✅
- **db_destructive_guard:** ✅

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
- Superadmin panel plans and impersonation
- Auto-downgrade expired subscriptions to free plan (Option A)
- SelectFilter custom key to avoid Filament v5 auto-JOIN on relationship dot notation
- Compact superadmin dashboard: stats without icons, doughnut half-width 250px, table widgets 200px maxHeight
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
- Budget/Presupuestos module (Budget + BudgetItem + Conversion a WorkOrder)
- Fix: AppServiceProvider en bootstrap/providers.php (TenantManager singleton)
- Quote Approval con signed URLs (WorkOrderStatusEnum: Approved/Rejected, public approval page)
- Notifications automáticas (Filament database bell, approved/rejected notifications a owner/editor)
- Inventory Fixes: AdjustmentIn/AdjustmentOut enum, SELECT FOR UPDATE race condition fix, InsufficientStockException
- Inventario Fase 2: TransferStockAction atómico con transfer_group_id, Transferir Stock y Ajustar Stock en WarehouseResource
- Inventario Fase 1: Warehouses + StockMovements + RLS
- AdjustItemStockAction único punto de creación de movimientos de stock
- StockSyncService recalcula cache item.stock desde movimientos
- TransactionService::issue() descuenta stock, cancel() repone stock
- WarehouseResource Filament CRUD + StockMovementsRelationManager
- Ajuste Rápido Action en ItemResource
- 39 tests Inventario pasando (AppScope + RLS + StockSyncService + negative stock + inactive warehouse + purchase cancel + TransferStockAction)
- Spatie cache poisoning Vector C fix: forgetCachedPermissions() post clearTenantContext() en RegisterTenantAction + CreateTenant
- Raw SQL injection fix: PDO quote + str_replace en migration create_app_user_role. Nueva migration update_app_user_password_escaped aplicada.
- POS Kiosko full-screen (PosPage con Livewire: categorías, catálogo, ticket, pago modal, historial, atajos teclado)
- Work Order Closure Fase 2: gate de cierre (checklist final + fotos antes/después + firma) vía WorkOrderClosureService con audit trail
- work_order_media.stage (before/after) con WorkOrderMediaStageEnum + CHECK constraint + factory states asBefore
- POS kiosko fix (Livewire v4): single root div en pos.blade.php + Alpine posKiosk registrado via FilamentView::registerRenderHook(SCRIPTS_AFTER). Commit 9493ade 2026-08-16. Verificado en navegador (root DIV, filtro 2->0, fullscreen, sin errores JS)
- Hardware POS parametrizable: PrinterSettingsResolver + EscPosService (TCP 9100, GS @ init, ESC p pulses cajon, corte) + TicketRenderer + PosPrintController endpoint POST /pos/print con proteccion cross-tenant 403. Drivers esc_pos (A) y window_print (B). Settings via tenants.settings->pos_hardware. Commit ca79997 en feature/pos-hardware. 10 tests nuevos.
- UI POS hardware (commit de3ce46): TenantResource Section Impresión (driver/host/port/canal/cajón, dinámico por driver) + modal post-pago en PosPage (IMPRIMIR/VER TICKET/Cerrar Esc). Validado en navegador via Selenium grid.
- Fix POS mobile (2026-08-22): improved mobile responsiveness - grid minmax 140px (antes 200px), compact padding en items/categorías, styles consistentes con Tailwind. 21/21 tests passing.
- Fix WorkOrder createOptionUsing (2026-08-22): contact_type=client added to Contact inline create. asset_type removed (replaced by client_vehicle_id).
- Fix WorkOrder redirect (2026-08-22): CreateWorkOrder redirects to list page after creation.
- Spanish UI (2026-08-22): Login, sidebar, buttons all in Spanish via setLocale('es') + 51+ published translation files.
- Collapsible sidebar (2026-08-22): AdminPanelProvider uses sidebarCollapsibleOnDesktop() + maxContentWidth(Width::Full).
- FilamentInfoWidget removed (2026-08-22): Public repo/docs widget eliminated from AdminPanel.
- QA test suite (2026-08-22): 61 automated tests + 80+ manual test steps in QA_CHECKLIST.md.
- Fix Filament v5 (2026-08-20): import KeyValue en ItemResource (Class App\Filament\Resources\KeyValue not found) y Section en Schemas\Components para BudgetResource (Class Filament\Forms\Components\Section not found — en v5 Section vive en Filament\Schemas\Components\Section).
- feature/pos-hardware-ui ff-mergeado a main (f196dd2): 5 commits (3efbe70 M3 kiosko + pagos + ticket, de3ce46 UI hardware + modal, 0fc848a taller closure fase 2, d8b708b infra guard+seeder+down fix, f196dd2 pint). Suite 409/409 verde.
- Single vertical (commit 5a272cb): consolida el modelo a UN template de 'Operaciones tipo Taller' (4 categorías/4 items/2 activos/3 catálogos). Eliminadas las industrias restaurant/mechanic/construction/clinic de config/industry-defaults.php. Fuera el select de industria de auth/register.blade.php y el Select industry de Onboarding.php. seed($tenant) reemplaza seed($tenant, 'mechanic') en todos los callers. settings.industry queda como campo legacy informativo (0 lecturas). Referencias 'mechanic' conservadas solo donde es rol de empleado. Suite 404/984 verde.
- Client Vehicles Separation (commits f71154e, 37a1a57): separación completa de vehículos de clientes (client_vehicles) de activos del taller (assets). Tablas client_vehicles + vehicle_mileage_logs con RLS, ClientVehicleResource Filament CRUD, WorkOrderResource selector unificado contacto + vehículo, creación inline con owner_contact_id automático. ClientVehicle model: recordMileage(), scopeByPlate(), scopeByOwner(). Migración de datos assets→client_vehicles, eliminación columnas vehicle de assets. AssetFactory y AssetResource sin tipo 'vehicle'. 7+4+5=16 tests nuevos. Suite 517/517.
- Fix POS services without stock (commit 4bf2f4c): PosPage now shows services (item_type=service) regardless of stock level. addItem() and updateQuantity() skip stock check for services. Categories filter also includes services with stock=0.
- Fix WorkOrder inline create (commit 8f3bc26): createOptionUsing now includes contact_type=client in Contact select. asset_type removed (replaced by client_vehicle_id).
- Fix WorkOrder redirect (commit 8660d77): CreateWorkOrder now redirects to list page (index) instead of edit page after creation.
- Spanish translations (commits 00cbe63-522d345): AppServiceProvider forces setLocale('es'), loads 6 Filament translation namespaces. 51+ translation files published in lang/vendor/filament-panels/es/. Login, sidebar, buttons all in Spanish.
- Collapsible sidebar (commit b17b86f): AdminPanelProvider uses sidebarCollapsibleOnDesktop() + maxContentWidth(Width::Full) for full-width content when sidebar collapsed.
- FilamentInfoWidget removed (commit 6d84776): Public repo/docs widget eliminated from AdminPanel (kept only in Superadmin).
- Duplicate items cleanup (2026-08-22): 8 items with same name but different SKUs cleaned from database via ROW_NUMBER window function in Railway console.
- QA test suite (commit 7491100): 61 automated tests across 6 files (PosFlowTest, WorkOrderFlowTest, ServiceCatalogTest, ContactFlowTest, ItemStockTest, TenantIsolationTest) + QA_CHECKLIST.md with 80+ manual test steps.

## Security Status

- **mfa_superadmin:** implemented
- **rls_enabled:** verified_5of5_fixed
- **rls_audit_date:** 2026-06-17
- **rls_gaps:** 
- **rls_fixed:** GAP-001, GAP-002, GAP-003, GAP-004, GAP-005
- **fix_priority:** none
- **audit_logs:** implemented
- **rate_limiting:** layered (route + livewire + named limiters)
- **rate_limiting_endpoints:** forgot-password (3/hora/IP), reset-password (5/hora/IP), login (5/60s/IP), sanctum/token (5/min/IP), register (10/hora/IP)
- **raw_sql_injection_fixed:** 2026-06-22 — migration 2026_06_17_151704_create_app_user_role.php fixeada con PDO quote + str_replace. Nueva migration 2026_06_23_025404_update_app_user_password_escaped.php aplicada en DB.
- **connections:** app logic tests (BYPASSRLS=true, default connection), security integration tests (NOBYPASSRLS, app_user)

## Next Actions

- [ ] Work Order Closure Phase 3: automatismos de vencimiento (breach a las 72h, alertas de recogida 48h)
- [ ] Restaurar demo-taller en saas_core SOLO si John lo decide explicitamente (requiere DB_DESTRUCTIVE_ALLOWLIST=saas_core) o navegar POS contra dusk_testing
- [ ] Decidir si sketches/pos-kiosk.html entra al repo o se ignora (boceto HTML, quedó fuera de los commits)
- [ ] Notificaciones push / SMS con SmsCode (post-MVP notifications)

---

> **Source:** `engram.json` | **Generated by:** `php artisan jaosoft:project-state`
