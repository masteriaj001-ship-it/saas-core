# Feature: {{ titulo }}

## 🧩 Resumen
<!-- Descripción breve del feature alineada al vertical talleres y naming canónico (Asset, WorkOrder, Transaction, Contact, Member, Item). -->

## 🔧 Cambios realizados
<!-- Lista de cambios concretos, no abstractos. -->
- 
- 

## 🏗️ Arquitectura y reglas aplicadas
- **Naming:** Se usó nomenclatura canónica (sin términos específicos de industria).
- **Tenant isolation:** Doble capa (RLS PostgreSQL + trait BelongsToTenant).
- **Filament 5:** Schema API, actions desde `Filament\Actions\`, navigation group como método.
- **Spatie Permission:** teams=false, cache=array, modelos custom con BelongsToTenant + HasUuids.

## 🧪 Ciclo SDD — Evidencias
| Gate | Estado | Nota |
|------|--------|------|
| GATE 0 — Contexto | ✅ | Checklist + engram.json consultados |
| GATE 1 — Plan + OK | ✅ | Plan presentado y aprobado |
| GATE 2 — Schema + Migración | ✅ | Migración aprobada y ejecutada |
| Desarrollo (TDD) | ✅ | Tests primero → Docs → Code → Report |
| GATE 3 — Post-ejecución | ✅ | Pint + suite verde + OpCache reset |

**Suite de tests:**
- Total: {{ tests.total }}
- Pasando: {{ tests.passing }}
- Assertions: {{ tests.assertions }}
- Estado: {{ tests.status }}

## 🔐 Seguridad (si aplica)
- [ ] RLS verificado en PostgreSQL 16
- [ ] MFA Superadmin activo
- [ ] Auditoría de acciones registrada
- [ ] Rate limiting aplicado

## 📋 Checklist de merge
- [ ] Código formateado con `pint --format agent`
- [ ] Suite completa en verde (`artisan test --compact`)
- [ ] Sin desviaciones del FEATURE_SPEC sin documentar
- [ ] `engram.json` actualizado

## 📝 Notas adicionales
<!-- Breaking changes, dependencias nuevas (requieren aprobación), deuda técnica, etc. -->
