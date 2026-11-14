# Instrucciones para GitHub Copilot — saas-core

## Fuentes de verdad y selección de tarea

- Aplica ODD como enrutador proporcional: una tarea clara puede seguirse desde pedido + diff; trabajo sustancial se registra en el spec del repo. Divide features en cortes funcionales y usa TDD para comportamiento determinista, manteniendo los gates de seguridad y aprobación de este repo. No crees un tablero alternativo.
- Lee `AGENTS.md` y `ARCHITECTURE_MANIFEST.md` antes de cambiar código.
- Antes de tomar trabajo, lee `specs/README.md`, `specs/QUEUE.md`, `specs/HANDOFF.md` y el spec asignado. No inventes una tarea ni retomes una tarea activa sin handoff que confirme que el checkout está libre.
- Implementa únicamente una tarea con estado `approved` y respeta su alcance y criterios. `ready` es solo para lectura/diagnóstico/documentación según el spec.
- Si los documentos, el issue y el código contradicen entre sí, detente y reporta la contradicción; no elijas silenciosamente una fuente nueva.

## Invariantes técnicos

- Mantén ambas capas de aislamiento: PostgreSQL RLS y `BelongsToTenant`. No omitas el tenant scope ni añadas `stancl/tenancy`, `spatie/laravel-multitenancy` o Prisma.
- Mantén código de negocio dentro de `app/Modules/{Modulo}/` de acuerdo con `ARCHITECTURE_MANIFEST.md`; reutiliza patrones y dependencias existentes.
- Los tests reales de RLS son obligatorios cuando corresponda; no los reemplaces por pruebas que solo usan una conexión con `BYPASSRLS`.
- No agregues dependencias ni cambies arquitectura/stack sin aprobación explícita.

## Límites de autorización

- `approved` autoriza solo los cambios de código descritos en el spec. No autoriza despliegues, gastos, cambios de plan, cambios o borrados de datos de producción, restauraciones, rotación/exposición de secretos ni acciones externas.
- No leas ni copies `.env`, credenciales, datos personales o logs sensibles a prompts, issues o artefactos.
- Nunca ejecutes una migración de producción. `artisan migrate` sigue sujeto a la aprobación literal exigida por `AGENTS.md`.
- Revisa Git y los cambios preexistentes antes de editar; no reviertas trabajo ajeno ni trabajes simultáneamente en el mismo checkout que otra sesión.

## Entrega

Actualiza handoff/cola/spec según las reglas del repositorio. Resume archivos cambiados, verificaciones realmente ejecutadas, resultados `not_run` con motivo, limitaciones y siguiente paso. No afirmes pruebas, accesos o despliegues sin evidencia.
