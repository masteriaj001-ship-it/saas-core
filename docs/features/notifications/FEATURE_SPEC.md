# Notificaciones Automáticas de Aprobación/Rechazo

## Objetivo
Notificar a los usuarios del taller (owner/editor) cuando un cliente aprueba o rechaza un presupuesto desde la página pública, sin necesidad de refrescar Filament manualmente.

## Actores
- **Cliente**: ejecuta approve/reject desde la URL firmada.
- **Asesor (User con rol owner/editor)**: recibe la notificación en la campanita de Filament.
- **Sistema**: WorkOrderObserver detecta la transición y dispara notificaciones.

## Flujo
1. Cliente POST a `/presupuesto/{id}/approve` (o `/reject`).
2. `QuoteApprovalController` cambia el status a `approved`/`rejected`.
3. `WorkOrderObserver::updating()` detecta el cambio.
4. Para transiciones `waiting_approval → approved` o `waiting_approval → rejected`:
   - Obtiene todos los `User` del mismo tenant con roles `owner` o `editor`.
   - Envía `WorkOrderApprovedNotification` o `WorkOrderRejectedNotification` vía canal `database`.
   - La notificación persiste en la tabla `notifications`.
5. Filament NotificationsPlugin polling cada 30s muestra la notificación en la campanita.
6. El asesor hace clic → redirige a `EditWorkOrder` de la WO.

## Reglas de Negocio
- Solo notificar en transiciones `waiting_approval → approved` y `waiting_approval → rejected`.
- No notificar al usuario que ejecuta la acción (no aplica porque el cliente es anónimo).
- Solo notificar a usuarios del mismo tenant (`tenant_id` match).
- La notificación incluye: código WO, título, status label, y enlace directo a edición.
- Si no hay usuarios con owner/editor en el tenant, no se notifica a nadie (no es error).

## Modelos
| Entidad | Campo | Descripción |
|---------|-------|-------------|
| `WorkOrderApprovedNotification` | — | `title`: "Presupuesto aprobado", `body`: "WO-001 — Reparación de motor", `url`: link a edit |
| `WorkOrderRejectedNotification` | — | Ídem + rejection_reason en el body |

## UI (Filament)
- Campanita nativa de Filament (database notifications) habilitada en AdminPanel.
- Polling cada 30 segundos.
- Sin cambios en vistas Blade públicas.
- Sin sonido ni banner adicional.

## Seguridad
- Las notificaciones se filtran por tenant (solo ven las de su taller).
- No hay datos sensibles en el body de la notificación.

## Tests
8 tests:
- `test_sends_approved_notification_to_tenant_users` — notifica a owner/editor del tenant
- `test_sends_rejected_notification_with_reason` — el body incluye rejection_reason
- `test_only_notifies_users_from_same_tenant` — usuarios de otro tenant no reciben
- `test_ignores_other_status_transitions` — solo approved/rejected gatillan
- `test_notification_has_correct_format` — title, body, url presentes
- `test_no_notification_when_no_eligible_users` — no rompe si no hay owner/editor
- `test_notification_works_with_queue` — (opcional) si se encola
- `test_notification_appears_in_filament_bell` — smoke test del canal database
