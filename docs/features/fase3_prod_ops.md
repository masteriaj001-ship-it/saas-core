# Fase 3 — Producción/Ops 2026-10-01

> Estado: Parcial (cambios seguros aplicados; pendientes con costo requieren aprobación)

## Aplicado (cero costo, cero riesgo facturación)

**`Dockerfile` + `docker/opcache.ini` (nuevo):**
- `docker-php-ext-install opcache` + ini conservador (`memory 256`, `max_files 20000`, `validate_timestamps=0`, `save_comments=1`, sin JIT).
- Los contenedores reinician en cada deploy, así que no validar timestamps es seguro.

**`docker/entrypoint.sh`:**
- Agregado `php artisan route:cache` (verificado en local: boot + login 200 con rutas cacheadas).
- NO `event:cache`: `AppServiceProvider.php:81` registra listener con closure → `event:cache` haría fatal. No tocar hasta refactorizar ese listener a clase.

**`blueprint.yaml`:**
- `healthCheckPath: /up` (antes `/`, que creaba sesión DB en cada ping).
- `SESSION_SECURE_COOKIE: "true"` (prod es 100% https tras Cloudflare).
- `LOG_CHANNEL: stderr` (antes `stack`→`single` en filesystem efímero que se pierde en cada deploy; ahora va a los log streams de Render).

## NO aplicado (requieren decisión explícita)

**Worker de colas (costo):** Render no tiene plan free para `type: worker`/`cron` (mínimo ~$1/mes cron, worker starter de pago). Sintaxis verificada contra la spec oficial:
```yaml
- type: worker
  name: saas-core-worker
  runtime: docker
  dockerfilePath: ./Dockerfile
  plan: starter
  startCommand: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```
Sin worker, las notificaciones `ShouldQueue` quedan en `jobs` sin procesarse. Pasos dashboard: New → Background Worker → mismo repo → startCommand de arriba. Requiere aprobación de facturación.

**Cron scheduler (costo mínimo):** `Schedule::command('subscriptions:check-expired')->hourly()` está huérfano (nada corre `schedule:run`). En vez de cron cada minuto, un cron `0 * * * *` corriendo `php artisan subscriptions:check-expired` directo (más barato). Pasos dashboard: New → Cron Job → `0 * * * *`. Requiere aprobación de facturación.

**DB Starter + backups (costo):** Postgres free sin retención garantizada. Pasos: dashboard → database → Upgrade a Starter + programar `pg_dump` diario (destino: Tigris S3 ya configurado como FILESYSTEM_DISK).

**Sentry (paquete nuevo):** requiere aprobación explícita por REGLA ABSOLUTA #3 (ningún paquete sin OK de John). Mientras tanto, `stderr` ya preserva logs en Render.

**Redis como store (riesgo runtime):** el servicio `saas-core-redis` existe pero la app no lo usa (sesión/caché/cola en `database`). Cambiar `CACHE_STORE`/`QUEUE_CONNECTION`/sesiones a `redis` exige cablear `REDIS_URL` (`fromService: saas-core-redis, property: connectionString`) + validarlo; si el cableado falla, las sesiones mueren (lockout). Dejar para ventana con rollback a mano. Snippet preparado (NO aplicado):
```yaml
- key: REDIS_URL
  fromService:
    name: saas-core-redis
    type: keyvalue
    property: connectionString
```

**CORS:** `routes/api.php` existe (Sanctum + v1 invoices) pero sin frontend externo; no se publica `cors.php` para no cambiar comportamiento de la API sin necesidad.

## QA de la fase

- `blueprint.yaml`: YAML válido; `entrypoint.sh`: `sh -n` OK.
- Boot local con `route:cache`: login 200; `route:clear` posterior, env dev limpio.
- Suite Auth: **32/32** (el resto no lo tocan cambios de config/deploy).
- Dockerfile no validable en local (usa Sail, no esta imagen): revisar el log del próximo deploy; ante fallo, Render mantiene la versión anterior viva.
