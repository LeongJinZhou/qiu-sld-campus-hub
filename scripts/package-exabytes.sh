#!/usr/bin/env bash
# Builds a ready-to-upload ZIP for Exabytes (cPanel or VPS): compiled assets, production Composer packages,
# no .env, no tests, no node_modules, no local data. Usage: scripts/package-exabytes.sh [output.zip]
set -euo pipefail
cd "$(dirname "$0")/.."
OUT="${1:-sld-campus-hub-exabytes.zip}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

npm ci --no-audit --no-fund
npm run build
git archive --format=tar HEAD | tar -x -C "$STAGE"
cp -R public/build "$STAGE/public/build"
(cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress)
rm -rf "$STAGE/tests" "$STAGE/node_modules" "$STAGE/.github" "$STAGE/docs/screenshots" "$STAGE/database/database.sqlite" "$STAGE/database/camsys.sqlite"
mkdir -p "$STAGE/storage/app/private" "$STAGE/storage/app/public" "$STAGE/storage/framework/"{cache/data,sessions,views} "$STAGE/storage/logs" "$STAGE/bootstrap/cache"
(cd "$STAGE" && zip -qr - .) > "$OUT"
echo "Built $OUT ($(du -h "$OUT" | cut -f1)). Upload it and follow docs/DEPLOY_EXABYTES.md."
