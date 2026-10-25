# PocketCat V11 — Enterprise Cloud-Integrated Editor & Distribution Platform

## Security boundary
PocketCat is the control plane. In BYOS mode, plaintext workspace data, attachments, encryption keys and master passphrases remain in the browser. Storage providers receive ciphertext. The platform API must never be used as an implicit fallback for BYOS reads/writes.

## Components
- Monaco/CodeMirror-compatible editor abstraction
- multi-file workspace and tab state
- Worker-based syntax/AST/lint pipeline
- isolated runtime iframe with hard iframe termination timeout
- virtual terminal/event capture
- universal StorageProvider interface
- Google Drive/Dropbox/OneDrive OAuth+PKCE adapters
- S3/R2/GCS/B2 adapters using direct or presigned uploads
- Firebase RTDB/Firestore/Storage adapters
- WebCrypto AES-256-GCM envelope encryption
- PBKDF2-SHA256 >=310,000 iterations baseline
- IndexedDB local vault with non-exportable device key
- encrypted snapshots and restore
- selective/project downloads
- Super Admin policy/RBAC/audit plane

## Non-negotiable rules
1. No BYOS plaintext to PHP.
2. No encryption key or master passphrase to PHP.
3. No silent BYOS -> managed-storage fallback.
4. BYOS metadata should be encrypted where privacy requires it.
5. Admin moderation applies to managed storage only; ZK BYOS content is intentionally opaque.
6. Browser-only background scheduling is best-effort; guaranteed scheduling requires a user-controlled agent or explicit online session.
7. Provider quota and browser limits still apply even when SaaS storage limits are bypassed.
8. The runtime uses iframe destruction/recreation as the authoritative CPU/crash containment mechanism; AST loop detection is advisory.

## V11 implementation note
The V10.4 Firebase BYOD prototype is retained as a compatibility layer. V11 introduces a provider abstraction and workspace data-plane boundary so save/load/history/backup/attachments can be migrated together instead of routing only save() through BYOD.
