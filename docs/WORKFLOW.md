# WORKFLOW — Cómo trabajar con los Agentes IA

> Referencia rápida para John (desarrollador)
> Cómo pedir features, qué esperar y cómo aprobar

---

## 1. El ciclo de un feature

```
TÚ                                YO (Agente IA)
═══                               ═══════════════

"Quiero un módulo de              Analizo el código existente,
 órdenes de trabajo"               reviso migrations, propongo spec
                                   ──────────────────────────────
                                   → Produzco FEATURE_SPEC.md

Revisas el spec                   
                                   │
"APROBADO" (solo esa palabra)      
                                   ──────────────────────────────
                                   → Ejecuto en orden:
                                     1. Migración + RLS
                                     2. Modelo
                                     3. Policy (autorización)
                                     4. FormRequest (validación)
                                     5. Service class
                                     6. Controller
                                     7. Filament Resource
                                     8. Tests
                                   → Reporto cambios

Pruebas en el navegador           
```

**Regla de oro:** Sin "APROBADO" no ejecuto migraciones. Ni "ok", ni "dale", ni "procede". Solo **"APROBADO"**.

---

## 2. Cómo pedir algo (formato ideal)

### Para un feature nuevo:

```
"Necesito un módulo de [nombre].
 Cada [entidad] tiene: [campo1], [campo2], [campo3].
 Pertenece a [entidad existente].
 ¿Quién puede crearlo? [rol]."
```

**Ejemplo real:**
```
"Necesito un módulo de órdenes de trabajo.
Cada orden tiene: título, descripción, fecha límite, 
prioridad y estado. Pertenece a un Asset.
Solo admin y editor pueden crearlas."
```

### Para cambios pequeños:

```
"Agrega el campo 'phone' a la tabla contacts."
"Cambia el status de 'archived' a 'cancelled'."
"Haz que el campo email sea obligatorio en Contacts."
```

---

## 3. Qué produce el agente

Después de cada ejecución recibes:

```
## Cambios aplicados

| Archivo                          | Operación |
|----------------------------------|-----------|
| database/migrations/xxxx_...     | CREATE    |
| app/Models/WorkOrder.php         | CREATE    |
| app/Filament/Resources/...       | CREATE    |

## Verificación requerida
- [ ] php artisan migrate ejecutado
- [ ] RLS verificado
```

---

## 4. Prohibiciones que aplican a los agentes

| No permitido | Por qué |
|---|---|
| Decir "aquí te dejo el código" | El agente edita archivos, no pega código en el chat |
| Sugerir Prisma | Stack = Eloquent. Punto. |
| Ejecutar migraciones sin aprobación | Prevención de desastres |
| Usar `SELECT *` | Siempre columnas explícitas |
| Crear tablas sin `tenant_id` | No existe entidad sin tenant en este sistema |

---

## 5. Plantilla de FEATURE_SPEC.md

La plantilla está en `docs/features/FEATURE_SPEC_TEMPLATE.md`. Cuando pidas un feature nuevo, el agente genera un archivo como este en `docs/features/[nombre-feature]/`:

```markdown
## Feature: [nombre]

### Descripción
[qué hace en 2-3 líneas]

### Módulo
[Assets, Contacts, Items, o nuevo]

### Casos de Uso
- [ ] UC-01: Admin puede crear [entidad] cuando...
- [ ] UC-02: Editor puede editar [entidad] cuando...

### Tablas
- [ ] Tabla nueva: [nombre] con FK a [entidad]
- [ ] Tabla modificada: [nombre] + campo [campo]

### Frontend
- [ ] Filament Resource [nombre]

### Tests
- [ ] TenantIsolationTest para [entidad]
```

Lo lees, si estás de acuerdo dices **"APROBADO"**, y yo ejecuto todo.

---

## 6. Resumen en 3 pasos

```
1️⃣ "Necesito X"           → Yo analizo y propongo spec
2️⃣ "APROBADO"             → Yo ejecuto TODO
3️⃣ Pruebas                → Tú verificas en el navegador
```

Eso es todo. No necesitas saber Laravel, ni SQL, ni Filament. Solo dime qué necesitas que haga el sistema.
