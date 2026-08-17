# FEATURE_SPEC — Hardware POS parametrizable

> Estado: Aprobado — implementado | Autor: opencode | Fecha: 2026-08-16
>
> **Desviaciones del plan (2026-08-16):**
> - Patrón de settings confirmado: `tenants.settings->pos_hardware` vía cast `array` existente + `PrinterSettingsResolver` (no JSONB FK dedicado)
> - `esc_pos` driver implementado en `App\Modules\Shared\Services\Print\EscPosService` (inicio TS 9100, `GS @`, `ESC p` cajón, corte `GS V`)
> - Endpoint `POST /pos/print` (`PosPrintController`) con verificación `invoice->tenant_id === auth()->user()->tenant_id` (patrón `InvoiceTicketController`)
> - Driver `window_print` resuelto en el controlador (no requiere servicio separado; usa la vista existente `facturacion.ticket-pos`)
> - 10 tests nuevos + suite completa 405 tests verdes. Commits: `ca79997` (feature), `ba58974` (engram)

---

## 1. Entrada (Input)

Qué datos se recolectan del usuario o del sistema antes de ejecutar la lógica del feature.

### Formularios / Componentes Filament

El feature NO agrega campos al formulario de cobro del POS existente. Se parametriza a nivel de tenant mediante configuración almacenada (Settings JSONB por tenant, siguiendo el patrón de `iva_configurable`).

| Componente | Campo | Tipo | Validación |
|------------|-------|------|------------|
| `Select::make('printer_driver')` | `printer_driver` | `in:esc_pos,window_print` | required |
| `TextInput::make('printer_host')` | `printer_host` | `ip` | `required_if:printer_driver,esc_pos` |
| `TextInput::make('printer_port')` | `printer_port` | `integer:min:1,max:65535` | `required_if:printer_driver,esc_pos` default `9100` |
| `Toggle::make('cash_drawer_after_payment')` | `cash_drawer_after_payment` | `bool` | default `true` |
| `TextInput::make('cash_drawer_channel')` | `cash_drawer_channel` | `integer:min:0,max:255` | default `2` (ESC/POS pulses) |

### Datos de Contexto

- `tenant_id`: resuelto por `TenantManager` automáticamente
- `user_id`: del request autenticado
- `invoice`: resultado de `InvoiceCreationService::create($tenant, InvoiceDocumentTypeEnum::Pos, [...])` — ya existe en `PosPage::checkout()`

### Validaciones Clave

- Solo `Admin Tenant` edita la configuración de impresión del tenant (Policy)
- `printer_host` acepta IP o hostname local; se valida con `ip` o `url`
- En modo `esc_pos` el ticket no se puede imprimir por ráfaga: máximo 1 sola ventana de impresión por cobro
- En modo `window_print` se usa `window.print()` con `@media print` del ticket térmico (80mm) — sin diálogo del navegador adicional (se imprime directo)

---

## 2. Proceso (Processing)

Lógica de negocio, invocación a servicios externos y manejo de errores.

### Servicios / Acciones

| Paso | Clase / Método | Descripción |
|------|----------------|-------------|
| 1 | `PosPage::checkout()` | Flujo actual: persiste invoice + payment, vacía carrito, resetea modal |
| 2 | `PrintService::dispatch(invoice)` | Devuelve payload con driver según settings del tenant |
| 3 | `EscPosService::render(invoice, settings)` | Genera datos ESC/POS (GS `@`, codificados) y envía por TCP 9100 al host/port |
| 4 | `WindowPrintService::render(invoice)` | Devuelve HTML del ticket 80mm para `window.print()` |
| 5 | `CashDrawerDriver` | Tras pago en efectivo con `cash_drawer_after_payment=true`, envía ESC/POS `ESC p <channel>` para abrir cajón |

### Llamadas a IA (si aplica)

No aplica — sin servicios de IA involucrados.

### Manejo de Errores

| Error | Respuesta | Notificación al usuario |
|-------|-----------|------------------------|
| Impresora no alcanzable (TCP 9100) | Reintentar 1 vez (timeout 3s) | `Notification::make()->warning()` — la venta YA está persistida, se puede reintentar imprimir desde la page del Invoice |
| ESPACIO EN MAQUINA ESC/POS (garbage / no inicializado) | Se envía `GS @` (initialize) antes de cada trabajo | No aplica — previene corruptions en impresoras térmicas |
| `window.print()` bloqueado en kiosko (iframes/embedded) | Fallback a abrir `/invoices/{id}/ticket` en pestaña nueva | Enlace visible tras el cobro |
| Settings corruptos por tenant | Se usa driver default `window_print` | Log en `Log::warning`, sin romper el checkout |

---

## 3. Estado (State)

Cómo se capturan, estructuran y persisten los datos.

### Tablas

No se crea tabla nueva. Se reutiliza el patrón Settings JSONB del tenant (mismo mecanismo que el IVA configurable).

| Tabla | Operación | RLS |
|-------|-----------|-----|
| `tenants.settings->'pos_hardware'` | MODIFY (JSONB) | ✅ heredada — RLS en tabla tenants ya activa |

### Campos

| Campo | Tipo | Requerido | Default | Descripción |
|-------|------|-----------|---------|-------------|
| `printer_driver` | `string` | sí | `window_print` | `esc_pos` (TCP 9100) o `window_print` (navegador) |
| `printer_host` | `string` | no | `127.0.0.1` | Solo si driver `esc_pos` |
| `printer_port` | `int` | no | `9100` | Puerto RAW de la impresora |
| `cash_drawer_after_payment` | `bool` | no | `true` | Abrir cajón tras pago efectivo |
| `cash_drawer_channel` | `int` | no | `2` | Canal ESC/POS de pulses (0-255) |

### Índices

- Ninguno nuevo — se consulta el JSONB del tenant por PK (no requiere índice adicional para este volumen)

### Propiedades Livewire (si aplica)

| Propiedad | Tipo | Propósito |
|-----------|------|-----------|
| `$showTicketModal` | `bool` | Nuevo — muestra ticket final + opciones de impresión (imprimir / ver en nueva pestaña) |
| `$lastInvoiceId` | `?string` | Ya existe — usado para hacer `route('invoices.ticket')` |

### Formato de Datos

- Settings del tenant:
  ```json
  { "pos_hardware": { "printer_driver": "esc_pos", "printer_host": "192.168.1.50", "printer_port": 9100, "cash_drawer_after_payment": true, "cash_drawer_channel": 2 } }
  ```
- Payload de ticket compartido para ambos drivers (misma info del invoice):
  `document_number`, `issued_at`, `items[]`, `total`, `tax`, `change`, `method`

---

## 4. Renderizado (Rendering)

Cómo reacciona la UI a los cambios de estado.

### Componentes Reactivos

| Componente | Directiva | Comportamiento |
|------------|-----------|----------------|
| Modal final del POS | `x-show="showTicketModal"` | Se muestra tras `checkout()` exitoso cuando hay ticket |

### Estados de Carga

```blade
<div wire:loading wire:target="checkout">
    <x-filament::loading-indicator />
</div>
```

### Estados Vacíos

- No aplica campos condicionales de datos (solo configuración del tenant).

### Componentes Condicionales

- En el modal final: botón **Imprimir** solo cuando `printer_driver === 'esc_pos'`; botón **Ticket en nueva pestaña** siempre (fallback universal)
- En settings del tenant: `printer_host`/`printer_port` visibles solo si `printer_driver === 'esc_pos'` (patrón `->live()` de Select)

---

## 5. Salida (Output)

Resultado final visible y acciones disponibles para el usuario.

### Visualización en UI

| Elemento | Componente Filament | Descripción |
|----------|---------------------|-------------|
| Modal post-pago | `Action::make('openTicket')` (existente en PosPage) | Ticket resumen + acciones de impresión |
| Settings impresión | `Section::make('Impresión')` dentro del Settings de tenant | Config del driver POS |
| Ticket térmico | Vista `resources/views/facturacion/ticket-pos.blade.php` (80mm) | Usado por `InvoiceTicketController` y por `window_print` |

### Acciones Posteriores

| Acción | Componente | Comportamiento |
|--------|------------|----------------|
| "Imprimir" (ESC/POS) | botón en modal | Llama al endpoint POST `/pos/print` con `invoice_id` |
| "Ver ticket" | `Infolist` / link | Abre `route('invoices.ticket', ['invoice' => $id])` en pestaña nueva |
| "Cerrar" | botón | Oculta modal, POS listo para siguiente venta |

---

## 6. Seguridad

- RLS en tabla nueva: no aplica (no hay tabla nueva; settings en `tenants` protegida por RLS existente)
- Política Laravel: `TenantSettingsPolicy` (o reutilizar el patrón del IVA configurable) — solo `Admin Tenant`
- ¿Expone datos cross-tenant? No — la configuración se lee dentro del contexto del tenant del request
- Roles y permisos involucrados:

| Rol | Alcance |
|-----|---------|
| Admin | Configurar impresión del tenant |
| Editor | Ver configuración (read-only) |
| Viewer | Ver configuración (read-only) |

- El endpoint de impresión (`/pos/print`) debe estar protegido por middleware de auth + tenant, y recibir `invoice_id` de un invoice perteneciente al tenant (verificación contra ID — nunca confiar en credenciales implícitas)

---

## 7. Tests Requeridos

- [x] PosPageTest — flujo checkout existente permanece verde (18/18)
- [ ] EscPosServiceTest — bytecode ESC/POS correcto: `GS @` init, `ESC p` pulses para cajón, secuencia de corte
- [ ] EscPosServiceTest — timeout/red no alcanzable no rompe el checkout (la venta ya persiste)
- [ ] WindowPrintServiceTest — HTML del ticket contiene `document_number`, items y total
- [ ] TenantIsolationTest — tenant A no puede leer configuración de impresión de tenant B
- [ ] Policy test — solo Admin Tenant puede guardar settings de impresión
- [ ] Feature test de ruta `/pos/print` — requiere invoice del tenant, 404/403 en invoice de otro tenant

---

## 8. Dependencias

- Features previos: Punto de Venta (PosPage + facturación) — commit `9493ade` (fix kiosko), InvoiceTicketController (`/invoices/{invoice}/ticket`)
- Paquetes nuevos: ninguno si el ESC/POS se implementa con sockets raw de PHP (`stream_socket_client`). Si se prefiere una lib, requeriría aprobación de John (REGLA ABSOLUTA #3)
- Servicios externos: ninguna impresora externa — solo TCP 9100 dentro de la red del taller

---

## 9. Checklist de Aprobación

- [x] Nombre del feature cumple Zero Redundancy (sin sesgo de industria)
- [x] No duplica lógica existente — reutiliza `InvoiceTicketController` y el patrón `iva_configurable` para settings
- [x] El modelo de datos es agnóstico de industria
- [x] RLS + FORCE RLS: no aplica tabla nueva; settings heredan protección de `tenants`
- [x] Input/Processing/State/Rendering/Output cubren todos los flujos
- [x] Los estados de carga, vacío y error están contemplados
- [ ] Este spec fue revisado por John

---

> **Para aprobar:** John escribe "APROBADO" como respuesta a este documento.
> Ninguna otra palabra activa la ejecución.