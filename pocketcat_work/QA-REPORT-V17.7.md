# PocketCat V17.7 QA Report

## Target
- Release: V17.7
- Baseline: V17.6 Advanced Upload Hardening
- Scope: File Storage upload workspace + Support Chat attachment uploader + release/version presentation

## Static QA checklist
- PHP syntax: PASS (all PHP files in release tree checked).
- File uploader JavaScript syntax: PASS (Node `--check`).
- Support uploader JavaScript syntax: PASS (Node `--check`).
- Duplicate filename server path: PASS by code inspection; existing file-object allocation is protected by a per-user MySQL advisory lock.
- Duplicate filename support path: PASS by code inspection; upload-session allocation is protected by a per-user/scope MySQL advisory lock.
- Resumable retry offset recovery: PASS by code inspection; failed local chunks probe the authoritative server offset before retransmission.
- Cancel cleanup: PASS by code inspection for local file sessions and support upload sessions.
- Bulk concurrency: PASS; maximum three active browser uploads remain enforced.
- Testing-mode quota presentation: PASS; `PHP_INT_MAX` is displayed as `Unlimited` rather than a fake multi-million-TB quota.
- Version/cache references: PASS; active upload assets and version marker use V17.7.
- Database migration: NOT REQUIRED.

## Live environment limitation
A real browser upload against the user's InfinityFree account cannot be executed from this build environment. Production smoke testing should therefore verify: duplicate filename upload, bulk 3+ files, cancel, retry after network interruption, completion at 100%, and Support Chat attachment send.
