# PocketCat Enterprise Hybrid Editor Suite

## Architecture decision

PocketCat uses a **zero-dependency Vanilla JS editor engine** for the core suite, with optional CDN integrations treated as adapters rather than requirements.

### Why Vanilla JS for PocketCat
- No framework bundle or npm build step on InfinityFree.
- Full control over the custom `site_content` storage model and CSRF API.
- Native `contenteditable`, Selection/Range and DOM APIs cover the required WYSIWYG surface.
- The frontend customizer can mount on arbitrary existing DOM nodes without replacing the page renderer.
- The media layer is an abstract `onMediaUpload(file)` hook.

### TipTap / TinyMCE trade-off
- **TinyMCE:** fastest route to mature rich-text behavior, plugins, accessibility and table tooling, but introduces a third-party runtime, licensing/plugin considerations and a more opinionated DOM model.
- **TipTap:** stronger modern document model and extension architecture, but normally needs a larger JavaScript application/build pipeline and ProseMirror concepts.
- **Vanilla:** more engineering effort for edge-case rich-text behavior, but lowest operational complexity for the current PHP/InfinityFree deployment.

For PocketCat, Vanilla is the safest deployment choice. A future adapter can mount TinyMCE or TipTap inside the same Backend/Frontend controller without changing the persistence API.

## Modes

1. **Backend mode** — `/admin/site-editor/` mounts the docked editor with menubars, formatting toolbar, Visual/Code tabs, quicktags, media upload, table tools, source modal, autosave and revision history.
2. **Frontend mode** — `?visual=1` for an authorized admin injects a floating command surface. Hovering an editable DOM element exposes a pencil. Clicking it activates `contenteditable`, positions a collision-aware toolbar, and exposes the style inspector.

## Persistence

- Published content remains in `site_content` and therefore continues to work with the existing public-page override mechanism.
- Draft/autosave state is stored in `editor_drafts` and is not published until the admin chooses Publish.
- The last 10 published/rollback states are stored in `editor_revisions`.
- CSRF is required for every editor API mutation.
- HTML is sanitized server-side before persistence: executable elements, event-handler attributes, dangerous URLs and inline style bloat are removed.

## Media

`window.PocketCatEditorConfig.onMediaUpload(file)` is the integration seam. The default implementation calls `/api/admin/editor-suite/`, but a production deployment can replace it with S3/R2, the existing PocketCat file service, or another upload provider.

## Frontend publish boundary

The frontend editor serializes the public content area while excluding navigation, footer, AI widget, scripts and the editor overlay. This matches the current `public_header()` / `public_footer()` architecture where `site_content` is the page-body override.

## Security notes

- Custom JavaScript remains disabled; this suite does not execute user-entered JS.
- Do not relax the sanitizer to allow arbitrary `style`, `on*`, `javascript:` or iframe content without a dedicated sandbox/CSP design.
- For larger media, route the hook to the existing S3/R2 direct-upload architecture rather than increasing PHP upload limits.
- For a future multi-tenant deployment, add tenant ownership to `editor_drafts`, `editor_revisions` and `site_content`.
