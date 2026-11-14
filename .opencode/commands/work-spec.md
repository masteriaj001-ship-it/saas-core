---
description: Retoma la siguiente tarea autorizada desde los specs compartidos del repositorio.
---

Lee `AGENTS.md`, `specs/README.md`, `specs/QUEUE.md` y `specs/HANDOFF.md`. Luego abre el spec elegible de mayor prioridad indicado en la cola y sigue exactamente su alcance. Puedes retomar una tarea `in_progress` solo si el handoff confirma que el agente anterior se detuvo y no hay otro agente trabajando en el mismo checkout. Nunca edites en paralelo con otro agente.

No necesitas un prompt de tarea en el chat: usa el objetivo, los criterios y los límites del spec. Comprueba `git status` antes de editar. Solo ejecuta trabajo `ready` de lectura/diagnóstico, retoma trabajo `in_progress` según las reglas anteriores, o implementa código cuando el spec esté `approved`; nunca te autoapruebes. No realices despliegues, cambios de producción, rotación de secretos, acciones destructivas ni transmisión de datos sin autorización explícita.

Al acabar, actualiza el spec, la cola y el handoff con evidencia real. Si falta acceso, marca la tarea `blocked` y deja una instrucción concreta para desbloquearla. No copies logs crudos ni secretos a archivos.
