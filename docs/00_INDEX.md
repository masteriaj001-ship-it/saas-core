# ProyectDashboard — Documentation Index
Jaosoft Engineering Standards v1.1

Stack: Laravel 13 · PHP 8.5 · PostgreSQL 18 · Filament 5 · Docker (Sail) · RLS Nativo

---

## Navegación Rápida

| Archivo | Audiencia | Propósito |
|---------|-----------|-----------|
| `01_MULTI_TENANT_FLOW.md` | Todos | Ciclo completo RLS + TenantManager |
| `02_ELOQUENT_AND_MIGRATIONS.md` | Devs | Modelos, traits, migraciones |
| `03_SDLC_FEATURE_LIFECYCLE.md` | Todos | Cómo nace y muere un feature |
| `proyect-dashboard/docs/PROJECT_STATE.md` | Todos | Estado actual del proyecto |
| `proyect-dashboard/docs/WORKFLOW.md` | Todos | Cómo operar con agentes IA |
| `AGENTS.md` | Agentes IA | Reglas mandatorias de gobernanza |

---

## Principios Fundacionales (No Negociables)

1. **La Base de Datos es el Guardián** — El aislamiento multi-tenant ocurre en PostgreSQL, no en PHP. Laravel es un puente transparente. Si RLS falla, todo falla.

2. **Zero Redundancy** — Usamos nombres agnósticos para escalar a cualquier industria:

   | ❌ Prohibido | ✅ Canónico |
   |---|---|
   | vehicles, machinery | Asset |
   | spare_parts, products | Item |
   | clients, suppliers | Contact |
   | invoices, receipts | Transaction |
   | employees, staff | Member |

3. **Prisma Está Muerto** — Prisma está explícitamente excluido del stack. Cualquier agente que lo sugiera o instale viola las reglas de gobernanza.

4. **Protocolo de Agente AI** — Ningún agente escribe código en el chat para que el humano lo copie. El agente edita los archivos directamente y reporta qué cambió.

---

## Arquitectura de Alto Nivel

```
                    REQUEST LIFECYCLE

  HTTP Request
      │
      ▼
  Laravel Middleware: SetTenantContext
      │  → Valida session / auth
      │  → Extrae tenant_id (UUID v4)
      │  → DB::statement("SET app.current_tenant_id...")
      │
      ▼
  PostgreSQL Connection
      │  → RLS activo en TODAS las tablas
      │  → current_tenant_id() lee app.current_tenant_id
      │  → Queries sin WHERE tenant_id son seguros
      │
      ▼
  Response
      │  → Datos ya filtrados por PG, no por PHP
```

---

## Convenciones Generales

### Nomenclatura de Archivos
- **Migraciones:** `YYYY_MM_DD_HHMMSS_create_{tabla}_table.php`
- **Modelos:** PascalCase singular — `Asset.php`, `Contact.php`
- **Policies:** `{Model}Policy.php`
- **Jobs:** `{Verbo}{Sustantivo}Job.php`
- **Tests:** `{Subject}Test.php` en directorio espejo

### Variables de Entorno Críticas
```
DB_CONNECTION=pgsql
DB_SSLMODE=require          # nunca disable en producción
SESSION_DRIVER=database     # NO file en multi-tenant
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Versiones Mínimas
| Componente | Versión Mínima |
|---|---|
| PHP | 8.5 |
| Laravel | 13.x |
| PostgreSQL | 18.0 |
| Filament | 5.x |
| Docker / Sail | 1.x / latest |

---

## Glosario

| Término | Definición |
|---------|------------|
| `tenant_id` | UUID v4 que identifica una organización. Nunca un integer. |
| RLS | Row Level Security — políticas de acceso a nivel de fila en PG |
| `set_config` | Función PG que escribe variables de sesión (`app.*`) |
| `current_setting` | Función PG que lee variables de sesión |
| FORCE ROW LEVEL SECURITY | Directiva que aplica RLS incluso al owner de la tabla |
| BelongsToTenant | Trait de Laravel que actúa como segunda línea de defensa |
| TenantManager | Servicio Laravel que gestiona el ciclo de vida del tenant en la sesión |

---

*Última actualización: 2026-05-28 — Jaosoft Engineering*
*No modificar sin pasar por el proceso SDLC definido en 03_SDLC_FEATURE_LIFECYCLE.md*