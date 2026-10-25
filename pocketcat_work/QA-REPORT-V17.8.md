# PocketCat V17.8 QA Report

## Target
- Release: V17.8
- Baseline: V17.7 Advanced Upload System
- Scope: File Storage upload workspace + Media Library uploader + active version/cache presentation

## Static QA
- PHP syntax: PASS for all changed PHP files.
- File Storage uploader JavaScript syntax: PASS (`node --check`).
- Media Library inline uploader JavaScript syntax: PASS (`node --check` on extracted script).
- Support uploader JavaScript syntax: PASS (`node --check`).
- File Storage duplicate filename allocation: PASS by code inspection; per-user MySQL advisory lock protects concurrent allocation.
- Media Library duplicate filename allocation: PASS by code inspection; per-user lock protects concurrent allocation per destination folder.
- Resumable retry offset recovery: PASS by code inspection; failed chunks probe authoritative server status before retransmission.
- File Storage cancel cleanup: PASS by code inspection; local temporary data is removed and the file object is marked deleted safely.
- Media Library cancel cleanup: PASS by code inspection; upload session abort removes the temporary session data.
- Bulk concurrency: PASS; File Storage and Media Library use a maximum of three active browser transfers.
- Folder drag/drop traversal: PASS by code inspection; directory entries are recursively resolved and relative paths are attached where supported.
- Media Library double-submit/double-upload regression: PASS by code inspection; selection only queues files and submission starts the worker pool.
- Testing-mode quota label: PASS by code inspection; active Testing Mode renders `Unlimited` explicitly.
- Active version/cache references: PASS; active release marker is V17.8.
- Database migration: NOT REQUIRED.

## Limitations
No authenticated live browser upload against InfinityFree was possible from this build environment. Production smoke testing must therefore cover the upload scenarios listed in `CHANGELOG-V17.8-ADVANCED-UPLOAD-SYSTEM.md`.
