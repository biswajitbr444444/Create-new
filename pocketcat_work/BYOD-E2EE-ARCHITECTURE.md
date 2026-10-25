# PocketCat Personal Firebase BYOD / E2EE

## Trust boundary
When Storage Engine is `Personal Firebase (E2EE)`, snippet plaintext, master passphrase, derived AES key, and Firebase credentials remain in the browser. PocketCat's PHP API is not used to persist snippet contents.

## Encryption
- AES-256-GCM encrypts title, body, tags, language/syntax, category and editor metadata before Firebase writes.
- PBKDF2-SHA-256 with 310,000 iterations derives the AES key from a user-entered master passphrase.
- Random 16-byte salt and 12-byte IV are stored with the ciphertext.
- Firebase config is encrypted at rest in IndexedDB with a locally generated AES device vault key.
- The master passphrase is never stored or transmitted to PocketCat.

## Firebase handshake
The editor uses the Firebase Web SDK and anonymous authentication, then performs an encrypted probe write. Realtime Database probes are deleted after a successful write. Firestore probes are written as ciphertext documents.

## Important limitation
This is application-level zero-knowledge relative to PocketCat. Firebase itself remains the storage provider and can enforce its own rules/logging. Users must configure restrictive Firebase Security Rules. Browser local vault protection is not equivalent to hardware-backed secure storage. For cross-device recovery, the user must retain their master passphrase and re-enter Firebase configuration on the new device.

## BYOD policy
The Super Admin can disable `allow_user_byod`. The client policy endpoint exposes this state, while the platform-managed tier matrix remains unchanged for hosted storage.
