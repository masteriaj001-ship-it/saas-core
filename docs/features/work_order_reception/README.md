# FEATURE_SPEC — Unificación de Recepción en Órdenes de Trabajo

> Estado: Borrador | Autor: opencode | Fecha: 2026-05-28

---

## 1. Entrada (Input)

### Formularios / Componentes Filament

| Componente | Campo | Tipo | Validación |
|---|---|---|---|
| `Select::make('contact_id')->createOptionForm(...)` | `contact_id` | `uuid FK` | nullable |
| `Select::make('asset_id')->createOptionForm(...)` | `asset_id` | `uuid FK` | nullable |
| `TextInput::make('metadata.kilometraje')` | `metadata.kilometraje` | `string` | nullable |
| `TextInput::make('metadata.nivel_bateria')` | `metadata.nivel_bateria` | `string` | nullable |
| `Textarea::make('metadata.notas_esteticas')` | `metadata.notas_esteticas` | `text` | nullable |

### Formularios de Creación Rápida (modales)

**createOptionForm para Contact (Cliente):**
- `name` (Nombre) — required, max:255
- `phone` (Teléfono) — nullable, tel
- `tax_id` (Documento/NIT) — nullable
- `contact_type` (Tipo) — default: 'client', hidden

**createOptionForm para Asset (Dispositivo/Recurso):**
- `name` (Nombre/Placa) — required, max:255
- `asset_type` (Tipo) — default: 'vehicles', options: phones/computers/vehicles
- `metadata.marca` (Marca) — nullable
- `metadata.modelo` (Modelo) — nullable

### Datos de Contexto
- `tenant_id`: resuelto por global scope BelongsToTenant automáticamente
- Ambas entidades heredan el tenant del usuario logueado vía trait

---

## 2. Proceso (Processing)

No requiere Service class nueva. `createOptionForm` delega en el `create()` del modelo Eloquent, que ya tiene:

- `BelongsToTenant::creating` event → inyecta `tenant_id`
- Global scope tenant → filtra opciones de Select existentes

| Paso | Descripción |
|------|-------------|
| 1 | Usuario abre modal "+" desde Select de Cliente o Activo |
| 2 | Llena formulario mínimo y confirma |
| 3 | Filament crea el registro via relación Eloquent |
| 4 | Select se refresca con el nuevo registro seleccionado |
| 5 | Inspección de ingreso → campos metadata mapean directamente a JSONB |

---

## 3. Estado (State)

### Tablas
- `contacts` — MODIFY (no estructural, solo nuevos registros creados desde modal)
- `assets` — MODIFY (no estructural)
- `work_orders` — MODIFY (no estructural, metadata tiene nuevos campos)

No requiere migración. `metadata` en `work_orders` ya es JSONB con `default '{}'`.

### Formato de metadata
```json
{
    "kilometraje": "45000",
    "nivel_bateria": "12.4V / OK",
    "notas_esteticas": "Rayón en puerta trasera izquierda. Tapete del conductor desgastado."
}
```

---

## 4. Renderizado (Rendering)

| Componente | Directiva | Comportamiento |
|---|---|---|
| `Select::make(...)->createOptionForm(...)` | nativa Filament | Modal con formulario al hacer clic en icono "+" |
| `TextInput::make('metadata.*')` | `->live()` opcional | Sincronización directa con campo JSONB |

---

## 5. Salida (Output)

- Modal de creación rápida se cierra automáticamente tras guardar
- Select muestra el nuevo contacto/activo creado
- Campos de inspección se persisten en `work_orders.metadata` JSONB

---

## 6. Seguridad

- RLS existente en ambas tablas (contacts, assets): 4 políticas cada una
- `createOptionForm` usa la relación Eloquent → tenant_id se inyecta vía BelongsToTenant
- No expone datos cross-tenant
- No requiere nuevas políticas ni permisos

---

## 7. Tests

- Ningún test nuevo requerido (cambios solo en UI del formulario, no en lógica de negocio ni BD)
- Tests existentes (60) deben seguir pasando

---

## 8. Dependencias

- Features previos: Flujo de recepción de WorkOrderResource
- Paquetes nuevos: ninguno
- Servicios externos: ninguno

---

## 9. Checklist de Aprobación

- [x] Nombre del feature cumple Zero Redundancy
- [x] No duplica lógica existente
- [x] El modelo de datos ya existe (metadata JSONB, contact/asset ya creados)
- [x] RLS existente cubre las tablas afectadas
- [x] Input/Processing/State/Rendering/Output cubren todos los flujos
- [ ] Este spec fue revisado por John

---

> **Para aprobar:** John escribe "APROBADO" como respuesta a este documento.
> Ninguna otra palabra activa la ejecución.
