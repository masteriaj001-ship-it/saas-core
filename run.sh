#!/bin/bash
# Pipeline de agentes headless (port a OpenCode): 8 pasos stateless comunicados por artefactos.
# Uso:
#   ./run.sh                 -> corre pasos 1-7 (hasta release + qa-notes)
#   ./run.sh address-review  -> corre solo address-review (después de comentarios en la PR)
#   ./run.sh plan            -> corre desde un paso intermedo (ej: plan)
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
  local start=$1
  local steps=("enrich" "plan" "implement" "code-review" "qa-code" "release" "qa-notes" "address-review")
  local models=("$STRONG" "$STRONG" "$STRONG" "$STRONG" "$STRONG" "$FAST" "$STRONG" "$STRONG")
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
    fi
  done
}

mkdir -p "$A"
case "${1:-all}" in
  address-review)
    step address-review "$STRONG"
    require verdict.json
    ;;
  *) run_from "${1:-all}" ;;
esac
echo "PIPELINE COMPLETO"
