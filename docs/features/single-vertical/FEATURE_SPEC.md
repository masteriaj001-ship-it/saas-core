# FEATURE_SPEC — Single Vertical: Operaciones tipo Taller

> Estado: Aprobado — implementado | Autor: opencode | Fecha: 2026-08-20
>
> **Template único (config):** `industries.general` → label "Operaciones tipo Taller" — **4 categorías** / **4 items** / **2 activos** / **3 service_catalogs** (fusión de las antiguas `general` y `mechanic`).
>
> **Verticales eliminadas:** `restaurant`, `mechanic`, `construction`, `clinic` — como *industrias* (keys de `config/industry-defaults.php`). Las referencias a `mechanic` en el código que quedan son del **rol de empleado** (ContactRoleEnum, WorkOrder), no de industria.
>
> **Desviaciones del plan (2026-08-20):**
> - `settings['industry']` no se elimina del modelo ni del cast array: se deja como campo **legacy informativo** (0 referencias de lectura tras el cambio). Tenants legacy (`construction`, `general`, sin-industria) NO se tocan en DB.
> - `config/industry-defaults.php` conserva la clave `industries` solo para compatibilidad inversa con `RegisterService`/`TenantTemplateSeeder` transitorios; se lee únicamente `industries.general` como template único. El Select de `Onboarding.php` se elimina (no hay elección de vertical).
> - El selector público `auth/register.blade.php` pierde el `<select industry>`; `RegisterController` ya no valida `industry`.
> - Test suite: `industryProvider` (5 casos) se colapsa a 1 test del template único (`general`). `OnboardingWizardTest` pierde 2 tests (select + prepopulate) y agrega 1 (sin select).

---

## 1. Entrada (Input)

Qué datos se recolectan del usuario o del sistema antes de ejecutar la lógica del feature.

### Formularios / Componentes Filament

| Componente | Campo | Tipo | Validación |
|------------|-------|------|------------|
| ~~`Select::make('industry')`~~ (ELIMINADO) | `industry` | — | — (ya no existe en `Onboarding.php`) |
| — | `name`, `business_name`, `email`, `password` | — | Reglas existentes sin cambios |

### Datos de Contexto

- `tenant_id`: resuelto por `TenantManager` automáticamente
- `user_id`: del request autenticado
- Template de siembra único: `config('industry-defaults.industries.general')` (categorías/items/activos/servicios)

### Validaciones Clave

- `RegisterController` deja de validar `industry` (regla `in:...` fuera)
- `Onboarding` deja de requerir selección de vertical; siembra el template único al completar

---

## 2. Proceso (Processing)

Lógica de negocio, invocación a servicios externos y manejo de errores.

### Servicios / Acciones

| Paso | Clase / Método | Descripción |
|------|----------------|-------------|
| 1 | `RegisterService::register(array $data)` | Crea tenant sin industria (settings `[]`), siembra defaults del template único |
| 2 | `RegisterService::createDefaults()` | Lee `config('industry-defaults.industries.general')` fijo (sin parámetro `industry`) |
| 3 | `TenantTemplateSeeder::seed(Tenant $tenant)` | Firma sin `industry`; seedea categorías, items, activos y service_catalogs del template único |
| 4 | `Onboarding::complete()` | Ejecuta `seed($tenant)` (template único), marca `onboarding_completed`, notifica |
| 5 | `RegisterTenantAction` | Ya no escribe `settings['industry']` |
| 6 | `TallerOnboarding` | Ya no pasa `industry => mechanic` al action |

### Llamadas a IA (si aplica)

No aplica.

### Manejo de Errores

| Error | Respuesta | Notificación al usuario |
|-------|-----------|------------------------|
| Template `general` ausente en config | Fallback implícito (config devuelve array vacío) — sin crash | No aplica (config está versionada) |
| Tenants legacy con `settings.industry` distinto | No se lee; campo legacy sin impacto funcional | No aplica |

---

## 3. Estado (State)

Cómo se capturan, estructuran y persisten los datos.

### Tablas

| Tabla | Operación | RLS |
|-------|-----------|-----|
| `tenants` | NO CREATE / NO MODIFY | sin cambios (solo lectura de `settings` legacy) |

### Campos

| Campo | Tipo | Requerido | Default | Descripción |
|-------|------|-----------|---------|-------------|
| `settings.industry` (legacy) | `jsonb` | no | `null` (contexto) | Se conserva en DB; ya no se escribe en tenants nuevos |

### Índices

Sin cambios.

### Formato de Datos

- `config/industry-defaults.php` → `['industries' => ['general' => ['label', 'categories', 'items', 'assets', 'service_catalogs']]]`

---

## 4. Renderizado (Rendering)

Cómo reacciona la UI a los cambios de estado.

### Componentes Reactivos

| Componente | Directiva | Comportamiento |
|------------|-----------|----------------|
| `Onboarding` wizard | sin select | 2 pasos fijos: Bienvenida + Inicialización (sin "Perfil de tu Negocio") |

### Estados de Carga

Sin cambios (placeholder del botón submit existente).

### Estados Vacíos

- Onboarding sin selección de vertical: se elimina el paso donde se pedía.

### Componentes Condicionales

- Se elimina el branch `if ($this->detectedIndustry)` del `form()`: siempre Bienvenida; `buildWelcomeMessage()` se simplifica o se deja acoplado al welcome base.

---

## 5. Salida (Output)

Resultado final visible y acciones disponibles para el usuario.

### Visualización en UI

| Elemento | Componente Filament | Descripción |
|----------|---------------------|-------------|
| Wizard Onboarding | `Onboarding` | 2 pasos: ¡Bienvenido a Jaosoft! + Inicialización |
| Form de registro público | `auth/register.blade.php` | Sin select de industria |

### Acciones Posteriores

| Acción | Componente | Comportamiento |
|--------|------------|----------------|
| "Finalizar Configuración" | Botón submit | Seed template único + redirect dashboard |

---

## 6. Seguridad

- RLS: sin cambios (no hay tabla nueva ni modificación)
- Política Laravel: no necesaria nueva
- ¿Expone datos cross-tenant? No
- Roles: sin cambios

---

## 7. Tests Requeridos

- [x] `OnboardingWizardTest::test_completing_onboarding_marks_tenant_as_completed` (existe)
- [x] `OnboardingWizardTest::test_template_seeder_creates_defaults` (nuevo, template único — sustituye `industryProvider`)
- [x] `OnboardingWizardTest::test_onboarding_wizard_has_no_industry_select` (nuevo — sustituye `renders_with_select`)
- [x] `OnboardingWizardTest::test_onboarding_wizard_submits_successfully` (ajustado sin `data.industry`)
- [x] ELIMINADO: `test_onboarding_wizard_prepopulates_if_industry_is_detected`
- [x] suite completa: **404 tests / 984 assertions verdes** | Pint OK

---

## 8. Dependencias

- Features previos: onboarding+registro existentes
- Paquetes nuevos: ninguno
- Servicios externos: ninguno

---

## 9. Checklist de Aprobación

- [x] Nombre del feature cumple Zero Redundancy (Operaciones tipo taller — sin sesgo de industria)
- [x] No duplica lógica existente
- [x] El modelo de datos es agnóstico de industria (settings.industry queda legacy informativo)
- [x] RLS + FORCE RLS incluidos en la migración — N/A (sin migración)
- [x] Input/Processing/State/Rendering/Output cubren todos los flujos
- [x] Estados de carga, vacío y error contemplados
- [x] Este spec fue revisado por John

---

> **Para aprobar:** John escribe "APROBADO" como respuesta a este documento.
> Ninguna otra palabra activa la ejecución.