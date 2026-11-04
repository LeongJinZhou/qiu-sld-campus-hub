# Changelog

Every release is cut with `scripts/release.sh X.Y.Z` from an up-to-date `main`: it runs the tests and build, creates
the annotated tag `vX.Y.Z`, the branch `release/vX.Y.Z` and a GitHub Release, and writes a private local backup
(Git bundle, working-folder snapshot, Plesk package, manifest, checksums). GitHub rulesets freeze `v*` tags and
`release/**` branches: they cannot be moved, merged into or deleted. Work continues on `main` through pull requests.

## 1.0.1 (2026-11-04)

### Security
- **Student directory (`GET /people/lookup`) requires current authority.** Previously any role assignment ever held,
  including former committees, gave access. Now only: SLD Officer / Super Admin; the acting club committee or advisor
  for the current session (incoming committee after handover included; pending and handover-training excluded;
  deactivated staff advisors excluded); or an event committee member for an event in the current session.
  Denied users get `403` and no student data. No schema or data change.
  - Affected: `app/Http/Controllers/PeopleLookupController.php`; pickers in proposal committee, event helpers,
    AGM candidates, SLD club edit.
  - Rollback: revert the commit, or `git checkout v1.0.0 -- app/Http/Controllers/PeopleLookupController.php`,
    then `php artisan optimize:clear`. Nothing else to restore.

### Deployment
- **PHP 8.4.1+ (64-bit) is required everywhere.** The bundled Symfony 8.1 packages refuse to run on PHP 8.3, but the
  Plesk guide allowed it. `composer.json` now states `^8.4.1`. `docs/DEPLOY_PLESK.md` now sets PHP 8.4 for the
  website handler, SSH commands and the scheduled task, lists every required extension, says `proc_open` must stay
  enabled, warns never to run `key:generate` on updates, and adds checks after deploying for each interpreter.
- **Upload limits match the app.** Event documents (video) may be 50 MB, but PHP allowed only 20 MB. The guide and
  `Dockerfile` now use `upload_max_filesize=55M` and `post_max_size=60M` (plus nginx `client_max_body_size 60m`).
  No app validation rule changed.
  - Rollback: revert the commit. On Plesk, put the previous PHP limits back if wanted (PHP 8.4 itself must stay).

## 1.0.0 (2026-11-04)

Version 1 baseline: commit `f3d991d`, tag `v1.0.0`, branches `release/v1` and `release/v1.0.0`.
