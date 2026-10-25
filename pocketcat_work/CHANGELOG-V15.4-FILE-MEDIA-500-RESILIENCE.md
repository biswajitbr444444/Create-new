# PocketCat V15.4 — File Storage & Media Library 500 Resilience

- Added an idempotent compatibility bootstrap for V12-V14 File Storage/Media bridge columns and sharing tables.
- Prevents `/files/` and `/media/` from failing with HTTP 500 when an older database has not yet applied every bridge migration.
- Preserves the V15/V15.1/V15.2/V15.3 features and Admin/User View behavior.
- PHP/JS syntax and ZIP integrity validated in the build environment.
