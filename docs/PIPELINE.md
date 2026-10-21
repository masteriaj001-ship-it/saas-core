# Pipeline de agentes headless (piloto 2026-10-14)

Tooling de desarrollo por agentes en `run.sh` + `prompts/` (8 pasos stateless que se
comunican SOLO por ficheros en `artifacts/`, gitignored). Importado y adaptado a este
repo en los commits `2c41df9` → `bf998b0`.

## Protocolo de uso (gate Plan+OK — regla de AGENTS.md)

```bash
# 1. describes la feature en feature-request.md (gitignored)
# 2. plan (NO toca código) — enriquece spec + tareas TDD
./run.sh --until plan
# 3. tú lees artifacts/tasks.json y artifacts/spec.md; si OK:
./run.sh implement        # implement..qa-notes: TDD, review, qa, mrs, guion QA
# 4. address-review (tras comentarios en la PR)
./run.sh address-review
```

El pipeline NO hace commits: el resultado queda en el working tree para tu GATE 3.
Todos los comandos internos van prefijados con `vendor/bin/sail` (la DB solo resuelve
dentro de la red de Sail).

## Modelos por defecto

| Paso | Modelo |
|---|---|
| enrich, plan, implement, code-review, qa-code, qa-notes, address-review | `opencode/mimo-v2.6-flash-free` (`MODEL_STRONG`) |
| release | `google/gemini-3.5-flash-lite` (`MODEL_FAST`) |

Override: `MODEL=x/y ./run.sh ...` (uno para todo) o `MODEL_STRONG=` / `MODEL_FAST=`.

## Métricas del piloto real (feature CashShift, 15 tareas TDD, 2026-10-14)

| Dato | Valor |
|---|---|
| Suite completa (post-feature) | 615 tests, ~7.5 min (`vendor/bin/sail artisan test --compact`) |
| Paso implement (15 tareas, mimo gratis) | ~90 min |
| Corredura completa implement→qa-notes | ~2 h |
| Re-check tras fixes (qa-code+release+qa-notes) | ~6-15 min |
| **Factor de lentitud mimo gratis** | **estimar ~1.5-2× lo que costaría un sonnet/opus** |

Con suite de ~7.5 min el cuello de botella NO son los tests (los verify van con
`--filter`), son las generaciones del modelo. Para planificar: features de ~15 tareas
≈ 2 h de pipeline; recorta alcance si necesitas iterar el mismo día.

## Aprendizajes del piloto (defender estos primero si se simplifican prompts)

1. **Pares rojo/verde con verificación de "falla por la razón correcta"** — 15/15,
   rojos validados antes de implementar. Es lo primero que NO simplificar por tokens.
2. **Pint en baseline**: el repo tenía 11 ficheros con deuda de estilo — qa-code pinta
   SOLO el diff; correrlo sobre todo el repo haría fallar el veredicto por ajeno.
3. **`--parallel` prohibido**: las bases `testing-N` no existen y fallaría.
4. **Sin PHPStan ni migrate manual** en los checks (RefreshDatabase cubre migraciones
   vía tests; REGLA #0 del AGENTS.md intacta).
5. Los agentes de `opencode run` leen `AGENTS.md` automáticamente — las convenciones
   (naming canónico, `app/Modules/`, tenant) llegan gratis; los prompts solo refuerzan.
6. Dusk queda fuera: `phpunit.xml` solo registra suites Unit y Feature.
