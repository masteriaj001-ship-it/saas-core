# UI/UX SPEC — Neon Garage Design System

> Versión: 1.0 | Fecha: 2026-06-03
> Contexto: Fase 2 UX/UI del módulo Talleres Mecánicos
> Arquitectura: ARCHITECTURE_MANIFEST.md R-01 (modular, toda lógica en Actions/Services)

---

## 1. Filosofía de Diseño

Interfaz **Dark/Neon Premium** para talleres mecánicos. El fondo oscuro (Gray-950) reduce fatiga visual en jornadas largas. Los acentos Cyan/Emerald guían la atención del operador hacia lo que requiere acción inmediata. La estética "taller nocturno" refuerza la identidad de la marca.

| Principio | Aplicación |
|---|---|
| **Dark-first** | Fondo Gray-950, texto Gray-100, sin variante light |
| **Neon guidance** | Acento Cyan-400 para interactivos, Emerald-500 para confirmación |
| **Monospaced data** | Toda data técnica (placas, VIN, kilometraje) en `font-mono` |
| **Glassmorphism** | Cards con `bg-gray-900/70 backdrop-blur-sm` para profundidad |
| **Zero noise** | Sin sombras superfluas, sin bordes donde el espacio basta |

---

## 2. Paleta de Colores

Definida en Tailwind v4 via CSS variables en un archivo de tema del módulo (no global).

```css
/* resources/css/talleres-theme.css — importado solo en vistas del módulo */
@layer base {
  :root {
    --color-neon-cyan:   #22d3ee;  /* cyan-400 */
    --color-neon-green:  #34d399;  /* emerald-400 */
    --color-neon-emerald:#10b981;  /* emerald-500 */
    --color-bg-card:     rgb(17 24 39 / 0.7);  /* gray-900/70 */
    --color-bg-card-hover: rgb(31 41 55 / 0.8); /* gray-800/80 */
    --color-glass-edge:  rgb(255 255 255 / 0.05);
    --color-glow-cyan:   0 0 12px rgb(34 211 238 / 0.4);
    --color-glow-emerald:0 0 12px rgb(52 211 153 / 0.4);
  }
}
```

### Mapa de uso

| Variable Tailwind | Uso |
|---|---|
| `bg-gray-950` | Fondo de página (panel completo) |
| `bg-gray-900/70` | Fondo de cards y paneles (`backdrop-blur-sm`) |
| `bg-gray-800/40` | Fondo de inputs y selects |
| `text-gray-100` | Texto primario |
| `text-gray-400` | Texto secundario / labels |
| `text-cyan-400` | Links, iconos activos, bordes de input en focus |
| `text-emerald-500` | Estados "completado", "activo", "disponible" |
| `text-rose-500` | Estados "cancelado", "error", "crítico" |
| `border-white/5` | Borde sutil de cards |
| `shadow-cyan-400/20` | Glow hover en botones primarios |

---

## 3. Arquitectura de Componentes

Todos los componentes de UI del módulo viven en:

```
app/Modules/Talleres/Resources/Views/Components/
├── PlateBadge.blade.php          ← Placa en formato XX-000-XX con glow verde
├── StatusDot.blade.php           ← Punto de estado animado (cyan/emerald/rose/amber)
├── GlassCard.blade.php           ← Card con glassmorphism base
├── DataRow.blade.php             ← Fila label + valor monospaced
├── NeonButton.blade.php          ← Botón con glow hover
├── GlowInput.blade.php           ← Input con borde neon en focus
├── MetricTile.blade.php          ← Ficha de métrica (ej: "3 Órdenes hoy")
├── TimelineStep.blade.php        ← Paso de timeline vertical para tracking
└── indexes/
    └── ComponentIndex.php        ← Registro de componentes (opcional)
```

### Reglas de componentes

1. **Sin lógica de negocio** — los componentes reciben datos planos (`string`, `bool`, `array`). No llaman Models, no ejecutan queries.
2. **Namespaced por módulo** — se renderizan con `Talleres::componentName`.
3. **Testeables** — cada componente se prueba con datos dummy en una feature test que renderiza y verifica texto/clases.

### Ejemplo: PlateBadge

```blade
{{-- app/Modules/Talleres/Resources/Views/Components/PlateBadge.blade.php --}}
@props(['plate' => ''])

<span {{ $attributes->merge(['class' => '
    inline-flex items-center gap-1 px-2 py-0.5
    font-mono text-xs tracking-widest uppercase
    text-emerald-400 bg-emerald-950/40
    border border-emerald-500/20 rounded-md
    shadow-[0_0_6px_rgba(52,211,153,0.3)]
']) }}>
    <x-talleres::status-dot status="active" class="w-1.5 h-1.5" />
    {{ $plate }}
</span>
```

### Ejemplo: GlassCard

```blade
{{-- app/Modules/Talleres/Resources/Views/Components/GlassCard.blade.php --}}
@props(['padding' => 'p-4'])

<div {{ $attributes->merge(['class' => "
    {$padding}
    bg-gray-900/70 backdrop-blur-sm
    border border-white/5 rounded-xl
    transition-all duration-200
    hover:bg-gray-900/80 hover:border-white/10
"]) }}>
    {{ $slot }}
</div>
```

---

## 4. Estética y Efectos Visuales

### Glassmorphism
- Fondo de cards: `bg-gray-900/70 backdrop-blur-sm`
- Borde: `border border-white/5`
- Hover: incrementar opacidad a `bg-gray-900/80` + borde `white/10`

### Glow (Neon Shadow)
```css
/* Clases utilitarias registradas en talleres-theme.css */
.shadow-neon-cyan   { box-shadow: 0 0 12px rgb(34 211 238 / 0.4); }
.shadow-neon-emerald{ box-shadow: 0 0 12px rgb(52 211 153 / 0.4); }
.shadow-neon-rose   { box-shadow: 0 0 12px rgb(244 63 94 / 0.4); }

/* Aplicado en hover de botones primarios */
.hover\:shadow-neon-cyan:hover { box-shadow: 0 0 16px rgb(34 211 238 / 0.5); }
```

### Tipografía
| Contexto | Clase |
|---|---|
| Data técnica (placas, VIN, kilometraje) | `font-mono text-xs tracking-widest` |
| Encabezados de sección | `text-sm font-semibold text-gray-100 uppercase tracking-wide` |
| Cuerpo / valores | `text-sm text-gray-100` |
| Labels / secundario | `text-xs text-gray-400` |

### Animaciones
```css
/* Punto de estado animado (StatusDot) */
@keyframes pulse-neon {
  0%, 100% { opacity: 1; box-shadow: 0 0 4px currentColor; }
  50%      { opacity: 0.7; box-shadow: 0 0 8px currentColor; }
}
.status-dot-active { animation: pulse-neon 2s ease-in-out infinite; }
```

---

## 5. Flujo de Onboarding (Filament Wizard)

Tres pasos usando el Wizard nativo de Filament 5 (`Filament\Schemas\Components\Wizard`).

```
Paso 1: Identidad    →  Paso 2: Workflow  →  Paso 3: Lanzamiento
```

### Paso 1 — Identidad del Taller
- **Logo**: Upload con crop (FileUpload + ImageEditor)
- **Nombre del taller**: TextInput, required, max 120
- **Dirección**: TextInput
- **Teléfono / WhatsApp**: TextInput con mask
- **Especialidades**: CheckboxList (mecánica general, motor, transmisión, eléctrico, diagnóstico, A/C, suspensión)

```php
Wizard\Step::make('Identidad')
    ->icon('heroicon-o-building-storefront')
    ->schema([
        FileUpload::make('logo')->image()->imageEditor(),
        TextInput::make('taller_name')->required()->maxLength(120),
        TextInput::make('address'),
        TextInput::make('phone')->tel(),
        CheckboxList::make('specialties')
            ->options(SpecialtyEnum::class)
            ->columns(2),
    ]),
```

### Paso 2 — Workflow del Taller
- **Método de cobro**: Radio (Por orden fijo / Por hora / Mixto)
- **Tarifa por hora**: NumericInput (visible si método incluye "Por hora")
- **Generar orden al recibir vehículo**: Toggle (ON por defecto)
- **Notificar cliente cuando esté listo**: Toggle (ON)
- **Días hábiles**: CheckboxList (L–S, DOM por defecto off)

```php
Wizard\Step::make('Workflow')
    ->icon('heroicon-o-cog-6-tooth')
    ->schema([
        Select::make('billing_method')
            ->options(BillingMethod::class)
            ->required()
            ->live(),
        TextInput::make('hourly_rate')
            ->numeric()
            ->visible(fn (Get $get) => $get('billing_method') !== 'fixed'),
        Toggle::make('auto_create_order')->default(true),
        Toggle::make('notify_on_ready')->default(true),
        CheckboxList::make('business_days')
            ->options(DayOfWeek::class)
            ->columns(7)
            ->default(['mon','tue','wed','thu','fri']),
    ]),
```

### Paso 3 — Lanzamiento
- **Resumen**: Componente de preview con todos los datos seleccionados (usando `state()` del Wizard)
- **Checkbox de aceptación**: "Confirmo que los datos son correctos"
- **Botón "Ir al Panel"**: Dispara `->action()` que guarda settings en `tenants.metadata` y redirige al dashboard

```php
Wizard\Step::make('Lanzamiento')
    ->icon('heroicon-o-rocket-launch')
    ->schema([
        Text::make('summary')
            ->state(fn (Get $get) => $this->buildSummary($get)),
        Checkbox::make('accepted')
            ->label('Confirmo que los datos son correctos')
            ->required(),
    ])
    ->afterValidation(function (Set $set) {
        /* la acción de guardar se maneja en el controlador/page */
    }),
```

---

## 6. Implementación Técnica

> **Estado:** Implementado en Fase 2 (2026-06-03). 9/9 componentes creados, wizard 3 pasos funcional, 80 tests en verde.

### Archivos creados

### Archivos creados

```
app/Modules/Talleres/
├── Resources/
│   ├── css/
│   │   └── talleres-theme.css             ← variables neon + @source tailwind
│   └── Views/
│       ├── Components/
│       │   ├── PlateBadge.blade.php       ← placa con glow emerald
│       │   ├── StatusDot.blade.php        ← punto animado (9 estados)
│       │   ├── GlassCard.blade.php        ← card glassmorphism
│       │   ├── DataRow.blade.php          ← label + valor (variante mono)
│       │   ├── NeonButton.blade.php       ← 4 variantes + glow hover
│       │   ├── GlowInput.blade.php        ← input con neon focus
│       │   ├── MetricTile.blade.php       ← ficha con icono + trend
│       │   └── TimelineStep.blade.php     ← timeline vertical animado
│       └── pages/
│           └── taller-onboarding.blade.php ← vista del wizard
├── Http/
│   └── Pages/
│       └── TallerOnboarding.php           ← page Filament con wizard 3 pasos
└── Providers/
    └── TalleresServiceProvider.php        ← loadViewsFrom + Blade::componentNamespace
```

### Registro de componentes Blade

En `TalleresServiceProvider::boot()`:

```php
public function boot(): void
{
    Blade::componentNamespace(
        'App\\Modules\\Talleres\\Resources\\Views\\Components',
        'talleres'
    );
}
```

Uso en vistas: `<x-talleres::plate-badge :plate="$asset->plate" />`

### Carga de CSS específica del módulo

En `TalleresServiceProvider::boot()`, registrar el CSS solo cuando se renderice el módulo:

```php
if (request()->routeIs('filament.admin.resources.assets.*') ||
    request()->routeIs('filament.admin.resources.work-orders.*')) {
    FilamentView::registerScript(
        Vite::asset('resources/css/talleres-theme.css'),
        'talleres-theme'
    );
}
```

### Prohibiciones

- ❌ No modificar `resources/css/app.css` — el tema del módulo no debe contaminar el layout general
- ❌ No sobrescribir clases de Filament (`filament-forms`, `filament-tables`) — los componentes del módulo usan sus propias clases
- ❌ No agregar lógica de negocio en componentes Blade — solo reciben datos y renderizan
- ❌ No usar `@php` o `<?php` en templates — toda la preparación de datos ocurre en Actions/Controllers

---

## 7. Criterios de Aceptación

- [x] Paleta dark/neon definida en `talleres-theme.css` del módulo (no global)
- [x] `@source` agregado en `app.css` para escaneo Tailwind de módulos
- [x] Variables de color definidas en CSS del módulo (no global)
- [x] Componentes Blade creados con `@props` y `$attributes->merge()`
- [x] Componentes registrados con namespace `talleres`
- [x] Wizard de 3 pasos funcional en el onboarding
- [x] Datos del wizard persistidos en `tenants.metadata` vía `DB::transaction`
- [x] Sin regresiones: 80 tests (205 assertions) en verde
- [x] Sin estilos globales modificados (solo `@source` en app.css para build)
- [x] Sin lógica de negocio en componentes UI

---

*Este spec es complementario a FEATURE_SPEC.md y ARCHITECTURE_MANIFEST.md. La implementación seguirá el ciclo SDD: Test → Docs → Code → Report → Update.*
