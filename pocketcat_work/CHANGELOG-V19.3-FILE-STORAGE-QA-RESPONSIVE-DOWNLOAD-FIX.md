# V19.3 — File Storage QA, Original Downloads & Responsive UI

- **Original files download** → bulk original mode depended on a card link selector that was not consistently present and could trigger a generic HTML fallback → added stable download-link metadata and routed each original download through the authenticated `download_start` API, preserving server-side limits, tickets, and analytics.
- **File card rendering** → cards created after upload lacked the same download selector and token metadata as server-rendered cards → normalized dynamic cards with `data-token` and `.file-download-link`.
- **Mobile layout** → file stats, upload actions, search controls, and bulk actions could overflow or become cramped → added responsive grids, wrapping, compact card layout, mobile-friendly bulk controls, and safer table-view behavior.
- **Preview/share interaction** → media preview controls also matched the share-action selector, causing unwanted share prompts → separated preview styling from share actions and delegated create-share handling for dynamically uploaded files.
- **Delete feedback** → single-file deletion updated the server but left stale file counts and empty states → refreshes the count and empty-state display immediately after success.
- **S3 attachment headers** → cross-origin object-store downloads could ignore the browser `download` attribute → signed object-store download URLs now request an attachment `Content-Disposition` with a sanitized original filename.
- **Cache invalidation** → file-storage assets were still versioned as V19.0 → bumped the page's file-storage CSS/JS references to V19.3.

## Verification

PHP syntax checks, JavaScript parse checks, archive integrity, and targeted source assertions are run as part of the V19.3 packaging workflow. A live authenticated browser/database test requires the deployed site and its configured storage/database services; it cannot be truthfully claimed from this offline source archive alone.
