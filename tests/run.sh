#!/usr/bin/env bash
# Run the snippet and helper tests.
#
#   bash tests/run.sh
#   bash tests/run.sh --list
#   bash tests/run.sh --filter=cron
#
# Uses $PHP if set, otherwise whatever "php" is on PATH.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

PHP_BIN="${PHP:-}"
if [ -z "$PHP_BIN" ]; then
  PHP_BIN="$(command -v php || true)"
fi

if [ -z "$PHP_BIN" ]; then
  echo "No PHP binary found. Set PHP=/path/to/php and try again." >&2
  exit 2
fi

exec "$PHP_BIN" "$ROOT_DIR/tests/run.php" "$@"
