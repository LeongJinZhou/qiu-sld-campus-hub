# Changelog

Releases are tagged `vX.Y.Z` on GitHub. `v1.0.0` (commit `f3d991d`) is the frozen Version 1 baseline.

## 1.0.1 (unreleased)

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
