# Plan de decisiones operativas — por fases

> Estado: PLAN (sin ejecutar) | Fecha: 2026-10-08 | Requiere luz verde por fase antes de tocar nada.
> Regla: cada fase cierra con QA + docs antes de proponer la siguiente. Sin migraciones destructivas.

---

## Fase A — Worker de colas + cron scheduler (requiere aprobación de facturación)

**Objetivo:** revivir notificaciones `ShouldQueue` (stock/cartera) y el scheduler huérfano (`subscriptions:check-expired`).

**Pre-requisito:** aceptar costo Render (worker sin plan free; cron mínimo ~$1/mes).

**Pasos:**
1. Dashboard Render → New → Background Worker → mismo repo/rama → `plan: starter` → startCommand: `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` → mismas env vars que `web` (bd, APP_KEY, mail).
2. Dashboard Render → New → Cron Job → `schedule: 0 * * * *` → comando `php artisan subscriptions:check-expired` (directo, no `schedule:run` por minuto: más barato).
3. Verificación: disparar 1 notificación de prueba (stock bajo en staging o tenant de prueba) y confirmar llegada del correo; forzar 1 suscripción expirada y confirmar downgrade a free + `SubscriptionLog`.
4. Rollback: suspender/eliminar ambos servicios en dashboard (cero cambios de código involucrados).

**QA:** colas procesadas (`jobs` vacía tras prueba), log del worker sin errores, suite Auth verde (sin cambios de código, solo humo).

---

## Fase B — Base de datos Starter + backups

**Objetivo:** salir de Postgres free (sin retención) antes de datos reales de clientes.

**Pre-requisito:** aceptar costo del plan Starter.

**Pasos:**
1. Dashboard Render → database `saas-core-db` → Upgrade a Starter (con ventana de mantenimiento avisada; hay reinicio).
2. Backup diario: comando `db:backup` (nuevo, `pg_dump --format=custom`) + subida al bucket Tigris ya configurado como `FILESYSTEM_DISK` + cron diario `0 2 * * *` que lo ejecute.
3. Verificación: restore de prueba en BD efímera local (`pg_restore`) + `CHECKSUM` del dump; alerta si el cron falla 2 días seguidos (revisar manualmente hasta tener Sentry).
4. Rollback: los dumps son archivos independientes; downgrade del plan solo cuando Render lo permita sin pérdida (verificar doc vigente antes).

**QA:** test del comando con disco `fake` (dump simulado), Pint, suite de comandos afectada en verde.

---

## Fase C — Sentry (requiere aprobación REGLA ABSOLUTA #3: paquete nuevo)

**Objetivo:** errores con contexto y alertas (hoy solo `stderr` + log streams).

**Pre-requisito:** OK explícito de John para instalar `sentry/sentry-laravel` + DSN del proyecto Sentry.

**Pasos:**
1. `composer require sentry/sentry-laravel` + `php artisan sentry:publish` (config con `traces_sample_rate` bajo en free).
2. `SENTRY_DSN` como variable sync:false en `blueprint.yaml` + dashboard (nunca en git).
3. Verificación: forzar excepción de prueba en endpoint no productivo (`/up` no; ruta temporal removida tras probar) y confirmar evento en dashboard Sentry con tenant en contexto.
4. Rollback: quitar DSN (el SDK sin DSN es no-op) o revertir commit.

**QA:** suite Auth verde (el SDK no toca lógica), Pint.

---

## Fase D — Redis como store (ventana con rollback a mano)

**Objetivo:** aliviar contención en Postgres free (sesión + caché + cola hoy van a BD).

**Pre-requisito:** ventana de bajo tráfico + acceso a dashboard para rollback de env vars. NO hacer junto a otras fases.

**Pasos:**
1. Cablear `REDIS_URL` en `blueprint.yaml` (`fromService: saas-core-redis, property: connectionString`) — solo expone la var, sin usarla. Deploy + verificar que boot no cambia.
2. En fase separada del mismo deploy o siguiente: `CACHE_STORE=redis`, probar login + navegación + jobs en staging/producción vigilada.
3. Sesiones a redis solo al final, con `SESSION_SECURE_COOKIE` ya en `true` y plan de reversión: volver vars a `database` + redeploy (< 15 min).
4. Verificación: latencia p95 de login y listados antes/después (logs Render), cero errores `Connection refused` a redis.
5. Rollback: revertir las 3 vars a `database` y redeploy.

**QA:** suites Auth + Caja en verde tras el cambio (sesiones Redis también en testing vía `sail` si hay servicio local; si no, documentar divergencia).

---

## Fase E — Sintonia php-fpm (observar primero, sin fecha)

**Objetivo:** decidir si `pm.max_children=5` alcanza (un aviso visto en ráfaga post-deploy 2026-10-07).

**Pre-requisito:** evidencia de recurrencia fuera de ventanas de deploy (revisar logs Render 1 semana).

**Pasos:**
1. Medir memoria real por hijo fpm en producción (pico / 5).
2. Solo si hay saturación repetida: `docker/php-fpm-pool.conf` con `pm.max_children` calculado (RAM libre / pico por hijo, margen 30%) + `COPY` en Dockerfile.
3. Verificación: 48h sin warnings + latencia estable.
4. Rollback: revertir el .conf (1 commit).

---

## Orden sugerido y dependencias

Fase A → Fase B → Fase C → Fase D → Fase E. A y B son independientes entre sí (pueden ir en el orden que prefieras); C requiere tu OK de paquete; D requiere su propia ventana; E solo si los logs lo piden.
