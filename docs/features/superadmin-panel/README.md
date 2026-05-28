# FEATURE_SPEC — Panel Superadmin (SaaS Owner)

> Estado: Borrador | Autor: opencode | Fecha: 2026-05-28

---

## 1. Entrada (Input)

### Generación
- `php artisan filament:panel superadmin` crea `app/Providers/Filament/SuperadminPanelProvider.php`

### Configuración
- **ID**: `superadmin`
- **Path**: `/superadmin`
- Sin `->tenant()` — NO multi-tenant, datos globales
- Sin RLS — el usuario superadmin puede ver TODOS los tenants

### Restricción de Acceso
- Middleware personalizado que verifica `auth()->user()->is_superadmin === true`
- O usa Spatie Permission: `$user->hasRole('superadmin')`
- Usuarios sin esa bandera reciben 403

---

## 2. Proceso (Processing)

| Paso | Descripción |
|------|-------------|
| 1 | Artisan genera SuperadminPanelProvider con panel vacío |
| 2 | Se personaliza path → `/superadmin` |
| 3 | Se agrega columna/flag `is_superadmin` a `users` (booleano, default false) |
| 4 | Se registra middleware de autenticación superadmin |
| 5 | Se agrega al middleware stack de Filament |

### Migración Requerida
- Agregar columna `is_superadmin` (boolean, default false) a `users`

---

## 3. Estado (State)

### Tablas
- `users` — MODIFY: agregar `is_superadmin boolean NOT NULL DEFAULT false`

### Sin RLS en el panel superadmin
- El panel superadmin NO hereda el middleware `SetTenantContext`
- Las queries muestran datos de todos los tenants
- RLS sigue activo en BD para el usuario postgres (dev) pero el panel superadmin no inyecta tenant_id

---

## 4. Renderizado (Rendering)

- Login y dashboard nativos de Filament en `/superadmin`
- Sin widgets custom inicialmente
- Sin sidebar resources propios (fase futura)

---

## 5. Salida (Output)

- Ruta `/superadmin/login` funciona
- Solo usuarios con `is_superadmin = true` pueden acceder
- Dashboard muestra datos globales sin filtro de tenant

---

## 6. Seguridad

| Riesgo | Mitigación |
|--------|------------|
| Superadmin ve datos cross-tenant | Requiere flag `is_superadmin` + middleware dedicado. Solo usuarios explícitamente marcados |
| Superadmin bypassea RLS | Intencional — es el dueño del SaaS. No expone a usuarios regulares |
| Usuario regular accede a `/superadmin` | Middleware retorna 403 si `is_superadmin !== true` |

### Migración
- Nueva columna `is_superadmin` con default `false`
- Usuarios existentes: `false`
- Ejecutar migración requiere aprobación

---

## 7. Tests

- [ ] Ruta `/superadmin/login` responde 200
- [ ] Usuario regular recibe 403 o redirect al intentar acceder
- [ ] Panel no interfiere con panel admin existente

---

## 8. Dependencias

- Filament 5 panel nativo
- Comando `php artisan filament:panel superadmin`
- Migración para columna `is_superadmin`

---

## 9. Checklist de Aprobación

- [x] No rompe multi-tenancy existente (panel independiente)
- [x] Solo usuarios marcados acceden
- [x] Se requiere migración (columna nueva)
- [ ] Este spec fue revisado por John

---

> **Para aprobar:** John escribe "APROBADO" como respuesta a este documento.
