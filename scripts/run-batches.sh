#!/usr/bin/env bash
# Keep the caller's cwd so relative manifest paths keep their meaning.
set -euo pipefail
root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
export PYTHONPATH="$root${PYTHONPATH:+:$PYTHONPATH}"
exec python3 -m scripts.batch_runner "$@"
