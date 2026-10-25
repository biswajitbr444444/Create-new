# PocketCat V11.1 — Visual Command Suite

## Added
- Super Admin Visual Command Center at `/admin/visual-engine/`.
- Explicit `?visual=1` live visual canvas for authorized admins.
- Closed Shadow DOM command hub and contextual DOM node highlighting.
- Stable `data-pc-node` targeting.
- Content/style/code inspector foundation.
- Local command-pattern undo/redo with 100-step history.
- Database-backed visual versions, drafts, mutations, schedules and publish events.
- Atomic publish with optimistic base-version conflict detection.
- Immutable rollback by creating a new version.
- Scheduled publish worker at `cron/visual_publish.php`.
- Responsive device switcher foundation.
- Global visual policy flags.
- CSRF header support for JSON admin APIs.
- Visual Command Center revision ledger.

## Security
- Visual APIs require Super Admin authorization.
- Visual engine is opt-in through the `visual=1` query for admin sessions.
- Publishing validates configuration server-side.
- Visual version history is append-oriented; rollback does not delete history.
- BYOS/E2EE data remains outside the visual control plane.

## Production note
The runtime and arbitrary JavaScript paths remain intentionally guarded. For production deployment, add a dedicated sandbox origin for executable user code and a queue/worker service for guaranteed scheduled releases rather than relying on shared-host PHP cron execution alone.
