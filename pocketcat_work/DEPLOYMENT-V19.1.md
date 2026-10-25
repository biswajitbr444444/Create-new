# V19.1 Deployment Notes

- Back up the application and database before deployment.
- Existing V19.0 installations: apply `database/migration-v19.1-production-expansion.sql` exactly once.
- Fresh installs: use the updated `database/schema.sql`; do not apply the ALTER migration on top of the fresh schema.
- Confirm the `file_share_links.revoked_at`, `file_objects.trashed_at`, `file_objects.trashed_by`, and `storage_audit_events` schema exists.
- Confirm the scheduled CLI runner invokes `cron/file_cleanup.php`.
- Optional setting `file_trash_retention_days`: `7`, `14`, `30`, `60`, `90`, or `0` (never). Default `30`.
- Staging smoke tests: upload a file, delete it, verify it disappears from active File Storage and is listed in `/files/trash/`; restore it and confirm the public URL works again; trash it again and permanently delete it; create a share, open it, revoke it, and verify both the share page and asset URL return a denied/410 response.
- Check PHP and application logs after smoke tests. Retention and physical cleanup run in the existing CLI cleanup task; the first cleanup pass may mark expired trash deleted and the same or next pass retries physical object cleanup.
