#!/bin/bash
# Pipeline de agentes headless (port a OpenCode): pasos stateless comunicados por artefactos.
# Uso:
#   ./run.sh                      -> corre pasos 1-7 (enrich..qa-notes)
#   ./run.sh --until <paso>       -> corre desde enrich hasta <paso> inclusive (GATE)
#                                    ej: ./run.sh --until plan  -> para y esperas tu OK
#   ./run.sh <paso>               -> corre desde <paso> hasta el final
#                                    ej: ./run.sh implement    -> tras tu OK de tasks.json
#   ./run.sh address-review       -> solo address-review (tras comentarios de la PR)
# Variables de entorno:
#   MODEL=x/y            -> fuerza el mismo modelo en TODOS los pasos
#   MODEL_STRONG=x/y     -> modelo de los pasos que exigen razonamiento
#   MODEL_FAST=x/y       -> modelo barato (solo el paso release)
set -euo pipefail

A=artifacts
P=prompts
STRONG="${MODEL_STRONG:-opencode/mimo-v2.6-flash-free}"
FAST="${MODEL_FAST:-google/gemini-3.5-flash-lite}"

step() {
  local name=$1 model=$2
  if [ -n "${MODEL:-}" ]; then model=$MODEL; fi
  echo "==> [$name] model=$model"
  if [ ! -f "$P/$name.md" ]; then echo "ERROR: falta $P/$name.md"; exit 1; fi
  opencode run --auto --model "$model" "$(cat "$P/$name.md")" \
    | tee "$A/logs-$name.txt"
  echo "==> [$name] OK"
}

require() {
  for f in "$@"; do
    if [ ! -s "$A/$f" ]; then echo "ERROR: artefacto vacío o inexistente: $A/$f"; exit 1; fi
  done
}

run_from() {
  local start=$1 until=${2:-}
  local steps=("enrich" "plan" "implement" "code-review" "qa-code" "release" "qa-notes")
  local models=("$STRONG" "$STRONG" "$STRONG" "$STRONG" "$STRONG" "$FAST" "$STRONG")

  if [ -n "$until" ]; then
    case " ${steps[*]} " in
      *" $until "*) ;;
      *) echo "ERROR: --until '$until' no es un paso válido (valores: ${steps[*]})"; exit 1 ;;
    esac
  fi
  if [ "$start" != "all" ]; then
    case " ${steps[*]} " in
      *" $start "*) ;;
      *) echo "ERROR: paso inicial '$start' no válido (valores: all ${steps[*]})"; exit 1 ;;
    esac
  fi

  local started=0
  for i in "${!steps[@]}"; do
    [ "$start" = "all" ] && started=1
    [ "$start" = "${steps[$i]}" ] && started=1
    if [ $started -eq 1 ]; then
      step "${steps[$i]}" "${models[$i]}"
      case "${steps[$i]}" in
        enrich)      require spec.md ;;
        plan)        require tasks.json ac-map.json ;;
        implement)   require review.json ;;
        code-review) require review.json ;;
        qa-code)     require verdict.json ;;
        release)     require mrs.json ;;
        qa-notes)    require qa-notes.md ;;
      esac
      if [ -n "$until" ] && [ "$until" = "${steps[$i]}" ]; then
        echo "==> detenido en '$until' (--until). Siguiente paso tras tu revisión/OK."
        return 0
      fi
    fi
  done
}

mkdir -p "$A"
case "${1:-all}" in
  address-review)
    step address-review "$STRONG"
    require verdict.json
    ;;
  --until)
    if [ $# -lt 2 ]; then echo "uso: ./run.sh --until <paso>"; exit 1; fi
    run_from all "$2"
    ;;
  *) run_from "${1:-all}" ;;
esac
echo "PIPELINE COMPLETO"
