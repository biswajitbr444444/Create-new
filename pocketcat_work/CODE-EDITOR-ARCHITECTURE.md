# PocketCat V10.2 Code Editor

## Module
Responsive HTML5/CSS3/Vanilla JS code studio with a dual-layer textarea + highlighted pre layer.

## Performance
- Prism syntax highlighting is executed in a Web Worker created from a Blob/ObjectURL.
- UI scrolling is synchronized with requestAnimationFrame and passive scroll listeners.
- Textarea remains the authoritative editable source.
- Worker failures fall back to plain text rendering.

## Execution
The preview uses a sandboxed iframe with `sandbox="allow-scripts allow-same-origin"`. A best-effort `_loopCounter` guard wraps timer APIs. This is not a hard kill switch for arbitrary synchronous infinite loops; a browser-level isolated execution service is required for hostile/untrusted code execution.

## Persistence
- `code_snippets`: current state + share token.
- `code_versions`: immutable version snapshots.
- `code_categories`: per-user category suggestions.
- `code_access_events`: view/share/execute telemetry.

## Public delivery
`/code/{token}` supports public/unlisted/private visibility and optional password verification. Passwords are stored as one-way password hashes; the content itself is not encrypted with the password.

## Exports
TXT, HTML, Python, C, C++, C#, CSS, JSON, Java, JavaScript, Lua, Markdown, PHP, Ruby, SQL, XML, Shell and Objective-C are mapped to matching extensions and MIME types.

## Security
- Public passwords use `password_hash()`/`password_verify()`; passwords are not reversibly encrypted because one-way hashing is the safer credential primitive.
- The preview sandbox follows the requested `allow-scripts allow-same-origin` flags and is intended for trusted/test code. For hostile arbitrary code, use an isolated remote execution service.

## Integration
The UI calls `/api/code/` and is deliberately provider-neutral. Replace the REST implementation with Firebase/Supabase/PostgreSQL adapters if desired.
