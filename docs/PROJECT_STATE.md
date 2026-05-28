# PROJECT STATE — ProyectDashboard

> Stack: Laravel 13 · PHP 8.5 · PostgreSQL 18 · Filament 5 · RLS Nativo
> Última actualización: 2026-05-28

---

## 1. Resumen Ejecutivo

SaaS multi-tenant con aislamiento por **PostgreSQL RLS nativo** (sin paquetes de tenancy). Core base con módulos anexables por industria. 18 migraciones ejecutadas, aislamiento multi-tenant vía `->tenant(Tenant::class, slugAttribute: 'slug')` en Filament + middleware `SetTenantContext` + trait `BelongsToTenant` con global scope.

**Fase actual:** Panel admin (`/admin/{slug}`) con 6 Resources + Dashboard widgets; panel superadmin (`/superadmin`) con 3 Resources globales (Tenant, GlobalAsset, GlobalWorkOrder). Módulo fiscal (Transactions), onboarding con defaults por industria, Spatie Permission, 2 comandos deploy (`jaosoft:make-superadmin`, `jaosoft:create-tenant-admin`). 60 tests pasando, 165 assertions.

---

## 2. Estado Actual

### Migraciones ejecutadas (18/18)

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

### Modelos (17 + trait)

| Modelo | Extiende | UUID PK | BelongsToTenant | SoftDeletes | Notas |
|---|---|---|---|---|---|---|---|---|
| `Tenant` | `Model` | ✅ HasUuids | ❌ (raíz) | ❌ | Slug usado como route key en Filament |
| `User` | `Authenticatable` | ✅ HasUuids + $incrementing=false + $keyType=string | ✅ trait (con excepción: salta si is_superadmin=true) | ✅ | Implementa `Filament\Models\Contracts\HasTenants` |
| `Asset` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `Item` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `Contact` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `WorkOrder` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `WorkOrderItem` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `Transaction` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `TransactionItem` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `Role` | `Spatie\Permission\Models\Role` | ✅ HasUuids + $incrementing=false + $keyType=string | ✅ trait | ❌ | |
| `Permission` | `Spatie\Permission\Models\Permission` | ✅ HasUuids + $incrementing=false + $keyType=string | ✅ trait | ❌ | |
| `Location` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `Category` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `TenantModule` | `TenantModel` | ✅ heredado | ✅ heredado | ✅ heredado | |
| `ModuleCatalog` | `Model` | ✅ HasUuids | ❌ (global) | ❌ | |
| `TenantModel` | `Model` | Abstracto base | — | — | |
| (trait) `BelongsToTenant` | — | — | Global scope + creating event | — | Modificado: salta excepción si `is_superadmin=true` |

**Nota crítica:** `User`, `Role` y `Permission` requieren `$incrementing = false`, `$keyType = 'string'` y `HasUuids` para que Spatie's eager loading funcione (usa `whereIntegerInRaw` que falla con UUIDs si $incrementing es true).

### Factories (9)

| Factory | Modelo |
|---|---|
| `TenantFactory` | Tenant |
| `UserFactory` | User |
| `AssetFactory` | Asset |
| `ContactFactory` | Contact |
| `ItemFactory` | Item |
| `WorkOrderFactory` | WorkOrder |
| `WorkOrderItemFactory` | WorkOrderItem |
| `TransactionFactory` | Transaction (states: sale/purchase/draft/issued/cancelled) |
| `TransactionItemFactory` | TransactionItem |

### Middleware + Servicios

| Componente | Ruta | Propósito |
|---|---|---|---|
| `SetTenantContext` | `app/Http/Middleware/` | Inyecta tenant_id en PG por request (registrado en web + Filament middleware stack). Retorna 403 si tenant_id está vacío (bloquea superadmins en /admin) |
| `EnsureIsSuperAdmin` | `app/Http/Middleware/` | Middleware del panel superadmin: retorna 403 si `auth()->user()->is_superadmin !== true` |
| `TenantManager` | `app/Services/` | Singleton, puente PHP ↔ PostgreSQL |
| `BelongsToTenant` | `app/Models/Concerns/` | Trait con global scope + creating event. Modificado para omitir exception si `is_superadmin=true` |
| `current_tenant_id()` | BD (función PG) | Firewall que valida UUID y lanza error si falta contexto |
| `WorkOrderService` | `app/Services/WorkOrders/` | CRUD + generación de códigos WO-XXXX |
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

### Comandos Artisan

| Comando | Propósito |
|---|---|---|
| `tenant:create` | Crea tenant + usuario admin + ejecuta RolePermissionSeeder + asigna rol `owner` |
| `jaosoft:make-superadmin` | Crea superadmin global (`tenant_id=null`, `is_superadmin=true`). Interactivo: Nombre, Email, Contraseña |
| `jaosoft:create-tenant-admin` | Transaccional: Crea Tenant → setTenantContext → User (owner) → commit. Interactivo: Empresa, Slug, Admin Nombre, Admin Email, Contraseña |

### Filament Resources (Admin Panel — `/admin/{slug}`)

| Recurso | Páginas |
|---|---|
| `AssetResource` | List / Create / Edit — name, code, asset_type, status, metadata, acquired_at |
| `ContactResource` | List / Create / Edit — contact_type, name, tax_id, email, phone, address, metadata |
| `ItemResource` | List / Create / Edit — sku, name, item_type, unit, price, cost, stock, min_stock, metadata |
| `WorkOrderResource` | List (con permisos Spatie) / Create (con auto-código) / Edit + ItemsRelationManager |
| `TransactionResource` | List / Create / Edit + ItemsRelationManager — type (sale/purchase), contact, invoice_number, CUFE, resolución DIAN, payment_method, items con IVA, totes automáticos, acciones de Emitir/Anular |
| `LocationResource` | List / Create / Edit — name, address, is_main (badge Principal), is_active |

### Filament Resources (Superadmin Panel — `/superadmin`)

| Recurso | Páginas | Query |
|---|---|---|
| `TenantResource` | List / Create / Edit / Delete | Global (sin scope) — name, slug, plan (badge), is_active (toggle), filtros plan/estado |
| `GlobalAssetResource` | List (read-only) | `withoutGlobalScope('tenant')` — todos los activos de la BD con columna tenant.name |
| `GlobalWorkOrderResource` | List (read-only) | `withoutGlobalScope('tenant')` — todas las WOs de la BD con columna tenant.name |

### Dashboard Widgets (3)

| Widget | Tipo | Datos |
|---|---|---|
| `DemoStatsOverview` | StatsOverviewWidget | 5 cards: Assets, Items (+stock bajo), Contacts, WorkOrders, Stock Bajo |
| `WorkOrderStatusChart` | BarChartWidget | WOs agrupadas por status con colores |
| `LatestWorkOrdersTable` | TableWidget | Últimas 5 WOs con código, título, asset, status, fecha |

### Tests (60 pasando, 165 assertions)

| Test Suite | Archivos | Tests | Propósito |
|---|---|---|---|---|
| WorkOrderTest | `tests/Feature/WorkOrders/` | 4 | CRUD + status transitions |
| WorkOrderTenantIsolationTest | `tests/Feature/Security/` | 1 | Aislamiento cross-tenant WorkOrders |
| SpatieTenantIsolationTest | `tests/Feature/Security/` | 4 | Roles/permissions aislados entre tenants, global scope filtra, RLS policies existen |
| SpatiePermissionBypassTest | `tests/Feature/Security/` | 5 | Scope bloquea cross-tenant, creating event evita huérfanos, SQL directo muestra todo pero scope filtra, current_tenant_id() valida UUID |
| SpatieCacheIsolationTest | `tests/Feature/Security/` | 3 | Cache aislado por tenant, forgetCachedPermissions limpia ambos niveles, permisos recargan con RLS |
| TransactionTest | `tests/Feature/Transactions/` | 9 | CRUD (sale/purchase), counter atómico (FAC-XXXXX/OC-XXXXX), status transitions (draft→issued→cancelled), tenant isolation, recalculate totals |
| RegistrationTest | `tests/Feature/Auth/` | 12 | Registro (válido, duplicados, password, slug, rol owner, tenant activo) |
| PasswordResetTest | `tests/Feature/Auth/` | 7 | Forgot password, reset, token inválido, password débil |
| ApiTokenTest | `tests/Feature/Auth/` | 9 | Crear/revocar/listar tokens Sanctum, abilities, 401 |
| RegistrationWithDefaultsTest | `tests/Feature/Onboarding/` | 6 | Defaults post-registro: location, categories, items, contacts, módulos, industria default |

### Reglas de gobernanza

| Documento | Propósito |
|---|---|
| `AGENTS.md` | Reglas mandatorias para agentes IA (no negociables) |
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

## 8. Notas Técnicas Críticas

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
- 16 migraciones, 21 tablas con RLS + FORCE RLS, 4 políticas c/u + 1 tabla global (modules_catalog)
- `users` tiene política SELECT que permite login sin contexto de tenant
- `BelongsToTenant` global scope se salta silenciosamente si no hay contexto (no lanza error)
- `sail` DB user es SUPERUSER → RLS bypasseado en desarrollo. Doble-lock: BelongsToTenant scope compensa.
- `tenants.settings` es JSONB. Almacenar como array PHP (no json_encode) para evitar doble encode. El contador de factura vive en `settings.transactions.{type}_counter`, incrementado atómicamente vía `jsonb ||` con RETURNING.
- Spatie cache configurado como `array` (per-request, no persistente entre requests cruzados)
- Permission names son genéricos y cross-tenant idénticos (`create_work_orders`). Aislamiento por `tenant_id` FK + RLS + global scope, no por unicidad de nombre.

### Spatie Permission — Opción B (implementada)
- `config/permission.php` → `teams = false` (no usar Spatie teams), `store = array`
- Modelos custom: `App\Models\Role`, `App\Models\Permission` (extienden Spatie + BelongsToTenant + HasUuids)
- `tenant_id` en todas las tablas con `DEFAULT public.current_tenant_id()` (para pivotes que Spatie inserta sin team_id)
- 21 tablas con RLS + FORCE RLS (previas + 5 Spatie + 2 Transactions + 3 nuevas: categories, locations, tenant_modules)
- 10 test suites: Spatie (3), WorkOrders (2), Transactions (1), Auth (3), Onboarding (1) — 60 tests total
- `current_tenant_id()` PG function regex actualizada: acepta cualquier versión UUID (v4 y v7) — Laravel 13 genera v7 por defecto
- **20 permisos totales**: work_orders, assets, items, contacts, transactions (× 4 acciones c/u)
- **18 migraciones**: 17 previas + `alter_users_table_make_tenant_id_nullable` para soporte superadmin

### Paneles Filament

| Panel | Path | Tenant | Middleware extra | Recursos |
|---|---|---|---|---|
| Admin | `/admin/{tenant:slug}` | ✅ `->tenant(Tenant::class, slugAttribute: 'slug')` | `SetTenantContext` (inyecta `app.current_tenant_id` para RLS) | 6 Resources (Asset, Contact, Item, WorkOrder, Transaction, Location) |
| Superadmin | `/superadmin` | ❌ Sin tenant context | `EnsureIsSuperAdmin` (403 si no superadmin) | 3 Resources (Tenant, GlobalAsset, GlobalWorkOrder) |

### Filament Multi-Tenant Architecture

- **URL con slug**: `->tenant(Tenant::class, slugAttribute: 'slug')` en AdminPanelProvider cambia las rutas de `/admin/items` a `/admin/{tenant:slug}/items`
- **HasTenants contract**: `App\Models\User` implementa `Filament\Models\Contracts\HasTenants`
  - `getTenants(Panel $panel)`: superadmin → `Tenant::all()`, normal → `collect([$this->tenant])->filter()`
  - `canAccessTenant(Model $tenant)`: superadmin → `true`, normal → `$this->tenant_id === $tenant->id`
- **SetTenantContext se mantiene**: necesario para setear `app.current_tenant_id` en PostgreSQL (para RLS). Se ejecuta después de que Filament resuelve el tenant de la URL
- **Resource::getUrl('index')**: Los widgets y enlaces deben usar `XxxResource::getUrl('index')` en vez de `route('filament.admin.resources.*')` porque `getUrl()` incluye automáticamente el `{tenant}` resuelto

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

# Ver RLS
docker exec -w /var/www/html proyect-dashboard-laravel.test-1 php artisan tinker --execute="DB::select(\"SELECT tablename, rowsecurity FROM pg_tables WHERE schemaname='public'\")"
```

---

*Si abres este proyecto por primera vez, lee `docs/LEARNING_GUIDE.md` para contexto completo y `docs/WORKFLOW.md` para entender cómo operar.*
