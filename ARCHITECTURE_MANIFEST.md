# ARCHITECTURE MANIFEST — ProyectDashboard

> Versión: 1.0 | Fecha: 2026-06-03
> Tipo: Regla de diseño innegociable para todo agente y desarrollador.

Este manifiesto establece las 5 reglas de oro que gobiernan la arquitectura del proyecto. Ningún código nuevo o refactorización puede violarlas. Código legacy en `app/Models/`, `app/Services/`, `app/Http/Controllers/` se migrará progresivamente a `app/Modules/`.

---

## R-01: Arquitectura por Dominios (DDD Lite)

**Texto:** Todo el código de negocio vive dentro de `app/Modules/{Modulo}/`. Prohibido crear lógica de dominio fuera de módulos.

```
app/Modules/
├── Talleres/              ← Vertical de talleres mecánicos
│   ├── Models/            ← Eloquent models del módulo
│   ├── Actions/           ← Casos de uso (una clase = una acción)
│   ├── Services/          ← Lógica de negocio orquestada
│   ├── Http/
│   │   ├── Controllers/   ← API controllers del módulo
│   │   └── Resources/     ← API Resources del módulo
│   └── Providers/         ← ServiceProvider del módulo
├── Ventas/                ← (futuro)
├── Inventario/            ← (futuro)
└── ...
```

Modelos compartidos (Tenant, User) y core del framework se quedan en `app/Models/`.

---

## R-02: Single Responsibility (SRP)

**Texto:** La lógica de negocio vive en **Actions** y **Services**. Los Controllers solo reciben el request, delegan en una Action/Service y retornan una respuesta. Prohibido escribir lógica de negocio en Controladores.

| Componente | Responsabilidad |
|---|---|
| **Action** | Un caso de uso específico (CreateAssetAction, CancelTransactionAction) |
| **Service** | Orquestación compleja que involucra múltiples Actions o modelos |
| **Controller** | Recibir request → llamar Action → retornar response |
| **Filament Resource** | Solo UI. La lógica va en Actions/Services |

**Regla práctica:** Si un Controller o Filament Resource tiene más de 20 líneas de lógica, esa lógica debe extraerse a una Action o Service.

---

## R-03: Inversión de Dependencias (DIP)

**Texto:** Depende de abstracciones, no de implementaciones concretas. Servicios externos (notificaciones, pagos, APIs) se definen como interfaces en `app/Contracts/` y se inyectan vía constructor.

```php
// app/Contracts/NotificationService.php
interface NotificationService
{
    public function send(string $to, string $message): void;
}

// app/Modules/Talleres/Services/WhatsAppNotification.php
class WhatsAppNotification implements NotificationService { ... }
```

Prohibido instanciar servicios externos con `new`. Siempre inyectar vía constructor o `app()->make()`.

---

## R-04: Resiliencia

**Texto:** Toda operación que escriba en múltiples tablas DEBE usar `DB::transaction`. Las excepciones se manejan en el borde (Controller), no en el dominio.

```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $asset = Asset::create([...]);
    WorkOrder::create(['asset_id' => $asset->id, ...]);
});
```

Prohibido capturar excepciones genéricas (`catch (\Exception $e)`) en Actions. Las excepciones de dominio deben extender `DomainException`.

---

## R-05: Mantenibilidad

**Texto:** Código autodocumentado. Nombres descriptivos, sin comentarios superfluos. Sin código muerto. Sin `dump()`/`dd()`/`ray()` en commits.

| Bueno | Malo |
|---|---|
| `$user->isRegisteredForDiscounts()` | `$user->discount()` |
| `CreateAssetAction::execute(...)` | `Asset::saveAsset(...)` |
| `if ($user->hasActiveSubscription())` | `if ($user->status === 1)` |

Los commits deben seguir conventional commits: `feat()`, `fix()`, `refactor()`, `docs()`.

---

*Este manifiesto es complementario a `AGENTS.md` (Reglas Mandatorias) y `CLAUDE.md` (Boost Guidelines). En caso de conflicto, este manifiesto prevalece para decisiones arquitectónicas.*
