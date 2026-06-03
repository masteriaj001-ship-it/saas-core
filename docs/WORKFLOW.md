# WORKFLOW — Cómo trabajar con los Agentes IA

> Referencia rápida para John (desarrollador)
> Cómo pedir features, qué esperar y cómo aprobar

---

## 1. El ciclo de un feature (SDD)

GATE 0 — /enrich_us

TÚ: "Quiero un módulo de órdenes de trabajo"
AGENTE: Analiza código existente → produce FEATURE_SPEC.md
TÚ: Revisas → escribes APROBADO

GATE 1 — /new

AGENTE: Crea rama feature/nombre-del-feature

GATE 2 — Schema

AGENTE: Propone borrador SQL completo
TÚ: Revisas → escribes APROBADO → agente genera migración
TÚ: Revisas migración → escribes APROBADO → agente ejecuta migrate

Ciclo de desarrollo (orden estricto):

  Test    → agente escribe tests que fallan primero (TDD)
  Docs    → agente actualiza docs afectadas
  Code    → agente implementa hasta que los tests pasen
  Report  → suite completa en verde + reporte de cobertura
  Update  → FEATURE_SPEC actualizado con desviaciones del plan

GATE 3 — /commit

0 tests rojos → merge permitido

**Regla de oro:** Sin "APROBADO" no ejecuto migraciones ni schemas. Ni "ok", ni "dale", ni "procede". Solo **"APROBADO"**.

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

## 3. Qué produce el agente (por cada gate)

**Después de GATE 0:**
- `docs/features/[nombre]/FEATURE_SPEC.md` con casos de uso, tablas, seguridad

**Después de GATE 2:**
- Archivo de migración generado (pendiente de tu APROBADO para ejecutar)

**Después del ciclo de desarrollo:**

| Cambios aplicados | |
|---|---|
| Archivo | Operación |
| tests/Feature/NombreTest.php | CREATE |
| database/migrations/xxxx_...php | CREATE |
| app/Models/Nombre.php | CREATE |
| app/Services/Nombre/NombreService.php | CREATE |
| app/Filament/Resources/NombreResource.php | CREATE |

**Tests**

Suite completa: X tests, Y assertions — todos en verde
Cobertura nuevos archivos: Z%

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
