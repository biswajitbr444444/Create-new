# PocketCat CMS Overhaul — Phase 3

Built on Phase 2.

## Implemented
- Advanced Quick Edit fields: date/time, password update, pingbacks, categories and tags.
- Advanced Bulk Edit: status, author, comments, pings, categories and tags.
- Revision Restore: the current state is snapshotted before restore, then selected revision content/meta is restored.
- Scheduled publishing helper promotes due scheduled drafts to published state.
- Editor taxonomy controls remain persisted through the existing CMS term relation tables.
- Fixed malformed hidden-input/filter markup in the CMS content listing.
- Added mobile-friendly taxonomy pickers and revision restore actions.

## Validation
- Full PHP lint and JavaScript syntax checks run before packaging.
- ZIP integrity verified.

## Deployment
Deploy over the existing V11.12/V11.13/V11.14 installation and preserve storage/app-config.php, storage/app-secret.php and storage/installed.lock. Test against a staging database first.
- Added `cron/cms_schedule.php` for optional CLI cron execution; due scheduled drafts are also promoted during normal installed requests.
