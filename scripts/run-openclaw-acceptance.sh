#!/usr/bin/env bash
set -euo pipefail

RTADV_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OPENCLAW_ROOT="/Users/ss/Documents/GitHub/OpenClaw"
BUILD_TASK_SCRIPT="${OPENCLAW_ROOT}/scripts/build-blog-acceptance-task.sh"
RUN_LOOP_SCRIPT="${OPENCLAW_ROOT}/scripts/run-single-worker-loop.sh"
PHASE=""
REPORT_PATH=""
TASK_PATH=""
RESULT_PATH=""

usage() {
  cat <<'EOF'
Usage:
  run-openclaw-acceptance.sh --phase <phase> [--report /abs/path/report.json]
                             [--task-output /abs/path/task.md]
                             [--result-output /abs/path/result.json]

Supported phases:
  structure-fix
  inline-alt-fix
  h2-fix
  inline-image-gap-fix

If --report is omitted, the script uses the latest matching report under /tmp.
EOF
}

while (($# > 0)); do
  case "$1" in
    --phase|-p)
      PHASE="${2:-}"
      shift 2
      ;;
    --report|-r)
      REPORT_PATH="${2:-}"
      shift 2
      ;;
    --task-output)
      TASK_PATH="${2:-}"
      shift 2
      ;;
    --result-output)
      RESULT_PATH="${2:-}"
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

if [[ -z "${PHASE}" ]]; then
  echo "--phase is required." >&2
  exit 2
fi

case "${PHASE}" in
  structure-fix|inline-alt-fix|h2-fix|inline-image-gap-fix)
    ;;
  *)
    echo "Unsupported phase: ${PHASE}" >&2
    exit 2
    ;;
esac

if [[ ! -x "${BUILD_TASK_SCRIPT}" ]]; then
  echo "Missing build script: ${BUILD_TASK_SCRIPT}" >&2
  exit 2
fi

if [[ ! -x "${RUN_LOOP_SCRIPT}" ]]; then
  echo "Missing loop script: ${RUN_LOOP_SCRIPT}" >&2
  exit 2
fi

if [[ -z "${REPORT_PATH}" ]]; then
  REPORT_PATH="$(ls -1t /tmp/rtadv-blog-report-${PHASE}-*.json 2>/dev/null | head -n 1 || true)"
fi

if [[ -z "${REPORT_PATH}" || ! -f "${REPORT_PATH}" ]]; then
  echo "A readable report is required. None found for phase ${PHASE}." >&2
  exit 2
fi

STAMP="$(date -u +"%Y-%m-%dT%H-%M-%SZ")"
TASK_PATH="${TASK_PATH:-${OPENCLAW_ROOT}/tmp/rtadv-${PHASE}-acceptance-task-${STAMP}.md}"
RESULT_PATH="${RESULT_PATH:-${OPENCLAW_ROOT}/tmp/rtadv-${PHASE}-acceptance-result-${STAMP}.json}"

openclaw gateway probe >/dev/null 2>&1 || true

"${BUILD_TASK_SCRIPT}" \
  --kind "${PHASE}" \
  --report "${REPORT_PATH}" \
  --output "${TASK_PATH}" >/dev/null

if ! "${RUN_LOOP_SCRIPT}" \
  --task-file "${TASK_PATH}" \
  --output "${RESULT_PATH}"; then
  cat >&2 <<EOF
OpenClaw acceptance failed.
Likely causes on this machine:
- gateway closed (1006 abnormal closure)
- embedded fallback model timeout

report_path=${REPORT_PATH}
task_path=${TASK_PATH}
result_path=${RESULT_PATH}
EOF
  exit 5
fi

cat <<EOF
report_path=${REPORT_PATH}
task_path=${TASK_PATH}
result_path=${RESULT_PATH}
EOF
