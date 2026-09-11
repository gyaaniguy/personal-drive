#!/usr/bin/env bash
# Local CI: run every check before code leaves the machine.
# Stops at the first failure. Run manually (./check.sh) or via the pre-push hook.
set -euo pipefail
cd "$(dirname "$0")"

step() { printf '\n\033[1;34m▶ %s\033[0m\n' "$1"; }

step "PHP lint (phpcs, PSR12)"
vendor/bin/phpcs -n --standard=PSR12 app/

step "PHP static analysis (phpstan)"
vendor/bin/phpstan analyse --no-progress

step "PHP tests (pest)"
php artisan test

step "JS lint (eslint)"
npx eslint resources/js

step "JS tests"
npm run test:js

step "Install smoke test (setup.sh in a container)"
./tests/install/smoke.sh

printf '\n\033[1;32m✓ all checks passed\033[0m\n'
