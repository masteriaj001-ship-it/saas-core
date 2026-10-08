# FEATURE_SPEC — Fase A: Worker de colas + Cron scheduler

> Estado: EJECUTADO 2026-10-08 22:00 COT (commit 0a53627) | Fecha spec: 2026-10-08. QA de circuito real pendiente (ver §4).
> Objetivo: revivir notificaciones `ShouldQueue` (hoy quedan en `jobs` para siempre) y el scheduler huérfano (`subscriptions:check-expired` nunca corre).

---

## 1. Problema

- `QUEUE_CONNECTION=database` sin ningún worker → `LowStockNotification`, `OverdueCreditNotification` y cualquier job futuro jamás se procesan.
- `Schedule::command('subscriptions:check-expired')->hourly()` registrado en `PlataformaServiceProvider` pero nada ejecuta `schedule:run` → las suscripciones vencidas nunca bajan a free.
- Agravante: `MAIL_MAILER` no está definido en `blueprint.yaml` (default `log`). Aun con worker, los correos solo irían al log. **Se requiere proveedor SMTP antes o junto a esta fase** (ver §5).

## 2. Alcance

Dentro:
- Worker `queue:work` como servicio Render (mismo repo/imagen, modo worker vía env).
- Cron horario con `php artisan subscriptions:check-expired` directo (no `schedule:run` por minuto: más barato y es la única tarea programada).
- Soporte de modos en `entrypoint.sh` (worker / one-shot / web).
- Verificación con prueba real + rollback documentado.

Fuera (otras fases):
- switching de stores a Redis (Fase D), Sentry (Fase C), backups (Fase B).

## 3. Diseño

### 3.1 `entrypoint.sh` multimodo (único cambio de runtime)

El ENTRYPOINT de la imagen es `exec`-form, así que el `startCommand`/`dockerCommand` de Render llega como argumentos (`$@`). El script ramifica:

```sh
# Modo worker: procesa colas, sin servidores ni migraciones
# (las migraciones las corre el servicio web en cada deploy).
if [ "${WORKER_MODE}" = "1" ]; then
    exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
fi

# Modo one-shot (cron): ejecuta el comando recibido y termina.
if [ $# -gt 0 ]; then
    exec "$@"
fi

# Modo web (default, sin cambios): migrate + caches + fpm + nginx.
```

Notas:
- El worker NO migra ni cachea (el web ya lo hace en cada deploy; evita locks concurrentes y trabajo duplicado).
- `--max-time=3600`: el worker se recicla cada hora para liberar memoria y reconectar (free tier, 512MB).
- El cron reusa la misma imagen; cada ejecución es un contenedor efímero que corre la migración... NO: en modo one-shot no se migra (el web migra en deploys). Solo boot + comando.

### 3.2 `blueprint.yaml` (adiciones)

```yaml
  - type: worker
    name: saas-core-worker
    runtime: docker
    dockerfilePath: ./Dockerfile
    region: frankfurt
    plan: starter
    autoDeploy: true
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: "false"
      - key: LOG_CHANNEL
        value: stderr
      - key: APP_KEY
        sync: false
      - key: WORKER_MODE
        value: "1"
      # + DATABASE_URL / DB_* (igual que web: el entrypoint ya lo parsea)
      # + MAIL_* (ver §5)

  - type: cron
    name: saas-core-scheduler
    runtime: docker
    dockerfilePath: ./Dockerfile
    region: frankfurt
    schedule: "0 * * * *"
    startCommand: php artisan subscriptions:check-expired
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: "false"
      - key: LOG_CHANNEL
        value: stderr
      - key: APP_KEY
        sync: false
      # + DATABASE_URL / DB_* (igual que web)
```

Sintaxis `type: worker` / `type: cron` + `schedule` verificada contra la spec oficial de Render (docs/blueprint-spec). El cron corre el comando directo (una sola tarea programada; `schedule:run` por minuto costaría ~43k ejecuciones/mes).

### 3.3 Tabla `failed_jobs`

Ya existe la migración `0001_01_01_000002_create_jobs_table.php` (crea `jobs`, `job_batches`, `failed_jobs`). Sin migración nueva. Los reintentos (`--tries=3`) terminan en `failed_jobs`, consultable para diagnóstico.

## 4. Criterios de aceptación (QA de cierre)

1. Deploy verde con los 3 servicios (`web`, `worker`, `cron`) en dashboard.
2. Prueba real 1: crear item con stock bajo en tenant de prueba → correo (o log con mailer `log`) generado por el worker en < 5 min; tabla `jobs` vacía después.
3. Prueba real 2: suscripción con `expires_at` pasado → tras la hora en punto, downgrade a free + fila en `subscription_logs` con `reason=expired_downgraded_to_free`.
4. `failed_jobs` consultable y vacía tras las pruebas.
5. Suite Auth verde (sin cambios de lógica; solo humo post-deploy).

## 5. Decisiones requeridas antes de ejecutar

1. **Facturación (bloqueante):** worker y cron no tienen plan free. Confirmar presupuesto (~worker starter + cron mínimo $1/mes).
2. **Proveedor SMTP (bloqueante para valor real):** sin `MAIL_*` los correos van al log. Elegir proveedor (Resend/Mailgun/SES/SMTP propio) y entregar credenciales como variables `sync: false` (nunca en git). Alternativa temporal: aprobar envío solo-a-log para validar el circuito e integrar SMTP después.
3. **Ventana:** el deploy reconstruye la imagen (mismo Dockerfile + entrypoint multimodo). El web no cambia de comportamiento; riesgo bajo, pero avisar ventana de todos modos.

## 6. Rollback

- Worker/cron: suspender o eliminar los servicios en dashboard (cero cambios de código en `web` salvo el `entrypoint.sh`, que mantiene el modo web idéntico).
- `entrypoint.sh`: revert de commit (el modo web no se toca en ninguna rama).
- Si el worker falla en loop: Render lo reinicia solo; revisar `failed_jobs` + logs `stderr` antes de reintentar.

## 7. Documentación al cierre

- `engram.json` → entrada `phaseA_worker_cron_*` en `deployment` + regenerar `PROJECT_STATE.md`.
- Este spec pasa a Estado: Ejecutado con commits y fechas.
