# FEATURE_SPEC — [Nombre del Feature]

> Estado: Borrador | Autor: [Agente] | Fecha: YYYY-MM-DD

---

## 1. Entrada (Input)

Qué datos se recolectan del usuario o del sistema antes de ejecutar la lógica del feature.

### Formularios / Componentes Filament

| Componente | Campo | Tipo | Validación |
|------------|-------|------|------------|
| `TextInput::make('title')` | `title` | `string:max:255` | required |
| `Select::make('status')` | `status` | `in:draft,active,archived` | required |
| ... | | | |

### Datos de Contexto

- `tenant_id`: resuelto por `TenantManager` automáticamente
- `user_id`: del request autenticado
- [Otros: ej. ID del registro padre, parámetros de ruta]

### Validaciones Clave

- [Regla de negocio validada en FormRequest]
- [Formato esperado de archivos subidos]
- [Permisos requeridos antes del submit]

---

## 2. Proceso (Processing)

Lógica de negocio, invocación a servicios externos y manejo de errores.

### Servicios / Acciones

| Paso | Clase / Método | Descripción |
|------|----------------|-------------|
| 1 | `[Service]::[method]()` | [qué hace] |
| 2 | `[Service]::[method]()` | [qué hace] |
| ... | | |

### Llamadas a IA (si aplica)

- **Prompt template**: `resources/prompts/[name].stub`
- **Servicio**: `App\Services\Ai\[Provider]Service`
- **Timeout**: [X] segundos
- **Fallback**: [qué pasa si falla]

### Manejo de Errores

| Error | Respuesta | Notificación al usuario |
|-------|-----------|------------------------|
| Timeout IA | Reintentar 1 vez | `Notification::make()->danger()` |
| Validación falla | No persiste | Errores en el formulario |
| ... | | |

---

## 3. Estado (State)

Cómo se capturan, estructuran y persisten los datos.

### Tablas

| Tabla | Operación | RLS |
|-------|-----------|-----|
| `[nombre_plural]` | CREATE / MODIFY | ✅ 4 políticas (SELECT/INSERT/UPDATE/DELETE) |

### Campos

| Campo | Tipo | Requerido | Default | Descripción |
|-------|------|-----------|---------|-------------|
| `id` | `uuid PK` | sí | `gen_random_uuid()` | |
| `tenant_id` | `uuid FK` | sí | — | inyectado por RLS |
| `[campo]` | `[tipo]` | sí/no | `[valor]` | [descripción] |

### Índices

- `(tenant_id)` — obligatorio
- `(tenant_id, [campo_frecuente])`
- `UNIQUE (tenant_id, [campo])` — si aplica

### Propiedades Livewire (si aplica)

| Propiedad | Tipo | Propósito |
|-----------|------|-----------|
| `$isProcessing` | `bool` | Controla spinner/bloqueo |
| `$aiResponse` | `?array` | Almacena respuesta de IA |

### Formato de Datos

- [Estructura de la respuesta esperada: JSON, array, texto plano]
- [Ejemplo concreto del payload]

---

## 4. Renderizado (Rendering)

Cómo reacciona la UI a los cambios de estado.

### Componentes Reactivos

| Componente | Directiva | Comportamiento |
|------------|-----------|----------------|
| `Select::make(...)` | `->live()` | Dispara evento onChange |
| `[campo]` | `->reactive()` | Se actualiza cuando cambia dependencia |

### Estados de Carga

```blade
<div wire:loading>
    <x-filament::loading-indicator />
</div>
```

### Estados Vacíos

- [Mensaje o componente a mostrar cuando no hay datos]
- [Ej: `->emptyStateHeading('Sin registros')`]

### Componentes Condicionales

- [Mostrar/ocultar secciones según estado]
- [Ej: panel de resultados solo si `$aiResponse` no es null]

---

## 5. Salida (Output)

Resultado final visible y acciones disponibles para el usuario.

### Visualización en UI

| Elemento | Componente Filament | Descripción |
|----------|---------------------|-------------|
| Campo resultado | `TextInput::make(...)->disabled()` | Muestra output de solo lectura |
| Panel detalle | `Section::make(...)` | Agrupa datos de salida |
| ... | | |

### Acciones Posteriores

| Acción | Componente | Comportamiento |
|--------|------------|----------------|
| "Aceptar sugerencia" | `Action::make('accept')` | Persiste el resultado y cierra |
| "Regenerar" | `Action::make('regenerate')` | Re-ejecuta el procesamiento |
| "Exportar PDF" | `Action::make('export')` | Descarga PDF |
| ... | | |

---

## 6. Seguridad

- RLS en tabla nueva: sí (hereda tenant automáticamente vía `current_tenant_id()`)
- Política Laravel: [Policy requerida / no necesaria]
- ¿Expone datos cross-tenant? [sí/no + justificación]
- Roles y permisos involucrados:

| Rol | Alcance |
|-----|---------|
| Admin | Crear, editar, eliminar |
| Editor | Crear, editar (no eliminar) |
| Viewer | Solo ver |

---

## 7. Tests Requeridos

- [ ] TenantIsolationTest — verifica que tenant A no ve datos de tenant B
- [ ] Policy test — prueba permisos por rol
- [ ] Feature test CRUD — crear, listar, editar, eliminar
- [ ] [Feature] test de IA — mock del servicio, verifica prompt y respuesta

---

## 8. Dependencias

- Features previos: [ninguno / listar]
- Paquetes nuevos: [ninguno / listar]
- Servicios externos: [ninguno / listar]

---

## 9. Checklist de Aprobación

- [ ] Nombre del feature cumple Zero Redundancy (sin sesgo de industria)
- [ ] No duplica lógica existente
- [ ] El modelo de datos es agnóstico de industria
- [ ] RLS + FORCE RLS incluidos en la migración
- [ ] Input/Processing/State/Rendering/Output cubren todos los flujos
- [ ] Los estados de carga, vacío y error están contemplados
- [ ] Este spec fue revisado por John

---

> **Para aprobar:** John escribe "APROBADO" como respuesta a este documento.
> Ninguna otra palabra activa la ejecución.
