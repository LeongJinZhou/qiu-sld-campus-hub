#!/usr/bin/env bash
# Cuts and backs up a release from the current main. Usage: scripts/release.sh 1.0.1 [backup-root]
#   1. Checks: on main, in sync with origin, tag is new, CHANGELOG has "## X.Y.Z", tests and build pass.
#   2. GitHub: annotated tag vX.Y.Z + branch release/vX.Y.Z (both frozen by repository rulesets) + a GitHub Release.
#   3. Local backup in <backup-root>/vX.Y.Z: full Git bundle, working-folder snapshot (incl. .env and local data,
#      so keep it private), Plesk package, release manifest, restore notes and checksums.
# Never touches production, never regenerates APP_KEY, never deploys.
set -euo pipefail
cd "$(dirname "$0")/.."

VERSION="${1:?usage: scripts/release.sh X.Y.Z [backup-root]}"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Version must look like 1.0.1"; exit 1; }
TAG="v$VERSION"
BRANCH="release/$TAG"
DEST="${2:-$HOME/Desktop/QIU-Campus-Hub-backups}/$TAG"

git fetch -q origin --tags
[ "$(git branch --show-current)" = main ] || { echo "Switch to main first."; exit 1; }
[ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] || { echo "main is not in sync with origin/main."; exit 1; }
git rev-parse -q --verify "refs/tags/$TAG" >/dev/null && { echo "$TAG already exists: releases are never re-cut."; exit 1; }
[ -e "$DEST" ] && { echo "$DEST already exists."; exit 1; }
NOTES="$(awk -v v="## $VERSION" 'index($0, v) == 1 {on = 1; next} /^## / && on {exit} on' CHANGELOG.md)"
[ -n "$NOTES" ] || { echo "CHANGELOG.md has no '## $VERSION' section."; exit 1; }
[ -z "$(find database/migrations -newer database/schema/install.sql -name '*.php')" ] \
    || { echo "A migration is newer than database/schema/install.sql: regenerate it first (docs/DATABASE.md)."; exit 1; }

php artisan test --parallel --compact
npm run build

COMMIT="$(git rev-parse --short HEAD)"
git tag -a "$TAG" -m "QIU SLD Campus Hub $TAG"
git branch "$BRANCH" HEAD
git push -q origin "$TAG" "$BRANCH"
gh release create "$TAG" --title "QIU SLD Campus Hub $TAG" --notes "$NOTES" --verify-tag

mkdir -p "$DEST"
git bundle create -q "$DEST/qiu-campus-hub-$TAG-full-history.bundle" --all
git bundle verify -q "$DEST/qiu-campus-hub-$TAG-full-history.bundle"
tar -czf "$DEST/qiu-campus-hub-$TAG-working-folder.tar.gz" --exclude=node_modules --exclude=vendor -C .. "$(basename "$PWD")"
git diff --quiet HEAD || git diff HEAD > "$DEST/uncommitted-changes-not-in-$TAG.patch"
scripts/package-plesk.sh "$DEST/qiu-campus-hub-$TAG-plesk-package.zip"

{
    echo "QIU SLD Campus Hub $TAG"
    echo "Commit: $(git rev-parse HEAD)   Built: $(date '+%Y-%m-%d %H:%M %Z')"
    echo "PHP (build machine): $(php -r 'echo PHP_VERSION;')   Required: $(php -r 'echo json_decode(file_get_contents("composer.json"), true)["require"]["php"];') 64-bit"
    echo "Node: $(node -v)   npm: $(npm -v)   Composer: $(composer --version 2>/dev/null | awk '{print $3}')"
    echo "composer.lock sha256: $(shasum -a 256 composer.lock | cut -d' ' -f1)"
    echo "package-lock.json sha256: $(shasum -a 256 package-lock.json | cut -d' ' -f1)"
    echo "Built assets: $(find public/build -type f | wc -l | tr -d ' ') files, manifest sha256 $(shasum -a 256 public/build/manifest.json | cut -d' ' -f1)"
    echo
    echo "Migrations ($(ls database/migrations | wc -l | tr -d ' ')):"
    ls database/migrations
    echo
    echo "Composer packages:"
    composer show --locked --no-dev --format=text 2>/dev/null | awk '{print "  " $1 " " $2}'
} > "$DEST/RELEASE-MANIFEST.txt"

cat > "$DEST/README-RESTORE.txt" <<TXT
QIU SLD CAMPUS HUB $TAG BACKUP  (commit $COMMIT)
Keep this folder PRIVATE: the working-folder snapshot contains .env (APP_KEY, mail password).

GitHub (frozen, cannot be moved or deleted): tag $TAG, branch $BRANCH, GitHub Release $TAG.
  git fetch --tags && git checkout -b back-to-$TAG $TAG
Offline:
  git clone qiu-campus-hub-$TAG-full-history.bundle hub && cd hub && git checkout $TAG
  composer install && npm ci && npm run build      (then copy .env from the working-folder snapshot)
Hosting (Plesk): upload qiu-campus-hub-$TAG-plesk-package.zip over sld/, KEEP the server's .env and storage/,
  run migrations (docs/DEPLOY_PLESK.md "Updating"). Never run key:generate on an existing install.
  Production database, private uploads and APP_KEY live on the server: back them up there together.
Check files: shasum -a 256 -c SHA256SUMS.txt
TXT

(cd "$DEST" && shasum -a 256 -- *.bundle *.tar.gz *.zip RELEASE-MANIFEST.txt README-RESTORE.txt $(ls *.patch 2>/dev/null) > SHA256SUMS.txt && shasum -a 256 -c -q SHA256SUMS.txt)
echo "Released $TAG ($COMMIT). Backup: $DEST"
