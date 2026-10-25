# PocketCat V11.41 — Installer Database Build Fix

## Fixed
- Reworked the first-run database builder to be idempotent after interrupted/partial installs.
- Existing tables, columns and index names are safely reused instead of causing the second build attempt to fail.
- Database schema now contains the current link analytics columns directly in `link_clicks` instead of requiring fresh-install ALTER statements.
- Current media tenancy indexes are part of the base schema.
- Current file sharing fields (`relative_path`, `view_only`) are part of the base `file_objects` schema.
- Non-duplicate SQL errors are logged server-side and stop the build, so real permission/schema problems are not silently ignored.
- The installer now checks for both CREATE TABLE and ALTER capability in its failure guidance.
- A successful build records how many statements were applied and how many already-existing DDL statements were safely reused.

## Why this fixes the screenshot error
The previous installer treated the entire schema as one all-or-nothing operation, but MySQL DDL is not transactional. If a hosting panel had already created part of the schema, a retry could hit duplicate columns/indexes and fall into the same generic "Database schema could not be created" message. V11.41 makes the build retry-safe and keeps the base schema aligned with the current application.
