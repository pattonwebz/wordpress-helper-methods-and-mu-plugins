#!/usr/bin/env bash
set -e
ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
mkdir -p "$TMP_DIR/repo"
cd "$TMP_DIR/repo"
git init >/dev/null
git config user.name "test"
git config user.email "test@example.com"
cat > sample.php <<'PHP'
<?php
/**
 * Example function.
 *
 * @since   x.x.x
 */
function sample_function() {}
PHP
git add sample.php
git commit -m "Initial" >/dev/null
git tag v1.0.0
cat > sample.php <<'PHP'
<?php
/**
 * Example function.
 *
 * @since   x.x.x
 */
function sample_function() {}
/**
 * Another function.
 *
 * @since\tx.x.x
 */
function another_function() {}
PHP
git add sample.php
git commit -m "Add second function" >/dev/null
php "$ROOT_DIR/tools/update-since-tags.php" --version=1.43.0 --root="$TMP_DIR/repo" --changed-since-last-tag >/tmp/since-tool-smoke.out
if ! grep -q '@since   1.43.0' sample.php; then
  echo "Expected spaces-preserving replacement not found"
  exit 1
fi
if ! grep -Eq '@since[[:space:]]+1.43.0' sample.php; then
  echo "Expected whitespace replacement not found"
  exit 1
fi
echo "Smoke test passed"
