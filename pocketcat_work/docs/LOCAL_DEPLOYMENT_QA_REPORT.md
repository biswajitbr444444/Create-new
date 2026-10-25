# PocketCat Local Deployment and QA Report

## Outcome

PocketCat was installed into an isolated local MariaDB database and served by Apache at **http://127.0.0.1:8081**. Apache was verified listening on the loopback address only. The supported installer created the active local administrator and installed-state lock. The local URL now agrees between the database setting and runtime configuration.

The application passed its configured regression checks and PHP syntax scan. This QA continuation exercised local signup/recovery email, an AI provider mock, file lifecycle operations, CMS mutations, and 73 discovered routes in Chromium, Firefox, WebKit, and a mobile-sized Chromium viewport. Several reproducible defects were fixed and retested.

The current checks were cross-referenced with the existing [codebase analysis](./PROJECT_CODEBASE_ANALYSIS_V19.73.md) and [production readiness review](./PRODUCTION_READINESS_REVIEW.md); those documents' architecture/security and deployment gates remain distinct from runtime QA. This is **not** a claim of 100% project completion. Live AI/payment/vendor acceptance and physical-device/accessibility/animation review remain unavailable or unverified. Password change, deterministic idle-session expiry, local sandbox checkout, plan-gated archives/password-protected sharing, reauthentication, and locally stubbed webhook/cloud workflows were verified. The [live test matrix](./LOCAL_LIVE_TEST_MATRIX.md) records each remaining limit explicitly.

## Environment and setup

| Component | Observed configuration |
|---|---|
| PHP | 8.3.6; CLI checks used `php8.3` |
| Web server | Apache 2.4.58, loopback-only listener `127.0.0.1:8081` |
| Database | MariaDB 10.11.14, loopback-only |
| Application URL | `http://127.0.0.1:8081` |
| Database | Isolated `pocketcat_qa_local_20260602` |
| Local administrator | `local-admin` (`admin`, active); password is kept outside the project and is not included here |
| Installer | Completed; `storage/installed.lock` present |
| AI, email, payment, cloud credentials | Restored to unconfigured defaults after local-mock testing; no external credentials used |

The deployment used Apache because the initial PHP built-in server did not apply the project's `.htaccess` rewrites. Apache was configured with the required rewrite support and bound only to loopback. Sensitive runtime configuration remained outside tracked source and was readable by the Apache worker without world-readable permissions.

## Defects fixed

1. **Website URL drift:** The installer and site-settings save paths previously wrote `app_url` only to the database, while absolute links read it from runtime configuration. Added a shared URL setter and made runtime-config write failures explicit. Installer, JSON and form settings, and reset-to-default now keep both values aligned. The current local values were verified in a fresh PHP process.
2. **Help icon insertion exception:** Help links were inserted before an input nested inside a label instead of before its direct parent child. Fixed the insertion target and retested settings screens.
3. **Menu manager search exception:** Removed a handler for the nonexistent `#pc-pages-search` selector; the page uses `#pc-page-search`. Search now returns results without a browser exception.
4. **Analytics schema collision:** The base schema declared `analytics_events` in both legacy and advanced shapes. Removed the duplicate legacy definition and added compatibility migration logic that preserves an existing legacy table as `analytics_events_legacy` before creating the advanced schema. Verified both tables in the isolated database.
5. **Control-plane role warning:** Added the `role` field to the users query because the page renders that value.
6. **Missing PHP runtime extension:** Installed the PHP 8.3 `mbstring` extension after the safe AI-unconfigured flow exposed the missing `mb_substr()` runtime dependency. The UI then returned its intended missing-provider response instead of a generic server error.
7. **Local upload permissions:** Uploaded local objects are changed to mode `0640`; permission failures are raised rather than silently ignored. A fresh upload's mode was observed through the authenticated application worker.
8. **CMS revision form nesting:** Removed a nested restore form that detached the editor submit button, added a standalone restore form, and verified edit/save and revision restoration.
9. **AI chat persistence:** Replaced an undefined `$db` reference after user-message insertion with the shared database helper. The previous code returned a generic failure after inserting only the user message; the local mock response now renders and persists.
10. **Comment approval action:** The per-comment Approve button submitted the bulk action value, so moderation did nothing. The row action now resolves as approval and was verified in the database.
11. **CMS list mutations:** Connected single-item and bulk action controls to their existing mutation endpoint for publish/trash/restore/permanent-delete. Trash, restore, and permanent deletion were retested through the UI.
12. **Local integration testability:** Added explicit CLI-only HTTP transport callbacks for cloud OAuth/upload and webhook delivery. These let tests exercise provider-specific request orchestration without bypassing production TLS or public-destination checks; HTTP requests cannot enable these callbacks.
13. **Plan enforcement and archive portability:** Protected-share creation now returns an explicit 403 for plans without password protection; archive generation now checks the owner plan for both direct and public-share downloads. ZIP archive creation falls back to the system `zip` utility when PHP `ZipArchive` is unavailable.
14. **Opt-in local billing sandbox:** Added a clearly disclosed mock checkout gated by `APP_ENV=local` and `BILLING_SANDBOX=true`. It records a sandbox transaction and updates only the local account; production mode rejects it, and no real payment is attempted.
15. **Deterministic session inactivity timeout:** Added a configurable 30-minute default idle timeout through `SESSION_IDLE_TIMEOUT`, with tests for activity refresh, expiration, cleared auth state, and anonymous sessions.
16. **Accessibility findings:** axe identified low-contrast marketing-preview text, visible brand text omitted from accessible names, and missing document language/title on not-found responses. Updated color values, preserved the visual brand mark as decoration, aligned the social-link accessible name, and added accessible 404 templates.

Historical Apache log entries from before the corrections remain in place; they include setup-time permission warnings and the errors that led to the fixes. The corresponding repaired pages and requests were retested without new matching errors.

## Verification performed

- `make PHP=php8.3 check` passed after the fixes: all 10 configured test scripts and the PHP syntax scan.
- Local public route requests returned HTTP 200 for representative home, pricing, and login pages.
- Sampled protected pages and the protected menu API redirected unauthenticated requests to login.
- Sampled config, bootstrap, installer-default, storage, schema, and includes paths returned HTTP 403.
- Invalid login was rejected; valid local administrator login reached the dashboard; logout invalidated the session.
- The route crawl exercised 73 discovered paths in Chromium, Firefox, and WebKit at 1440×900, and in Chromium at 390×844. It recorded zero uncaught page errors and zero horizontal overflow. Generic `/posts/` and `/pages/` detail URLs without a slug correctly return 404; revoked synthetic short links returned 410. Machine-readable results and screenshots are in [cross-browser QA evidence](./qa-evidence/cross-browser/).
- Playwright plus axe-core audited all 73 discovered paths for WCAG 2.0 A/AA, WCAG 2.1 A/AA, and WCAG 2.2 AA. After fixing the initial contrast, accessible-name, and error-document findings, the repeat audit recorded zero route errors and zero violations. Fifty-one protected routes redirected to login and were evaluated in that unauthenticated state; this does not claim an authenticated accessibility audit of those screens. Seven public/auth pages were checked at 390×844 with no overflow; the home page honored reduced-motion emulation with no animated elements detected. JSON results and screenshots are in [accessibility QA evidence](./qa-evidence/accessibility/).
- Task, note, and project create/read/update/delete operations were performed in the browser and checked against database persistence. The synthetic records were removed, and display counters were restored.
- File upload, direct download payload, public preview, share management/revocation, trash, restore, and permanent deletion passed. A fresh local object was observed at mode `0640`. Free-plan password-sharing and archive requests returned explicit 403 denials; after a synthetic local Basic-plan sandbox upgrade, password-protected sharing, wrong-password rejection, correct unlock, archive download, and `unzip -t` validation all passed. Synthetic records and files were removed.
- CMS page creation, taxonomy association, public rendering, revision restoration, scheduled publication, comment moderation, and trash/restore/permanent deletion passed. Synthetic CMS records were removed. The associated moderation/list-action defects are covered by regression checks.
- Signup and password recovery passed against a loopback-only STARTTLS SMTP capture mock. Token mismatch was rejected; the reset password was accepted and the old password rejected. No external email was sent. The test account, captured reset link, temporary Apache PHP CA override, and test certificates were removed.
- AI chat passed through the real workspace UI against a deterministic loopback OpenAI-compatible mock. The response rendered and persisted. No external provider call was made; temporary provider settings, conversations, and mock server were removed.
- A deterministic local transport stub exercised Google Drive, OneDrive, and Dropbox OAuth exchange/profile lookup, token refresh where supported, and upload/share flows, plus webhook delivery with HMAC validation and request/response persistence. `make PHP=php8.3 integration-mocks` passed and rolled back all database changes. No cloud provider or webhook endpoint was contacted.
- The local sandbox checkout was exercised over HTTP from signup through plan selection, mock authorization, and account-plan confirmation. A separate CLI mock verified that production mode cannot enable it, and the transaction/plan change rolled back. Sandbox UI clearly discloses that no payment is taken and no gateway is contacted.
- File tests confirmed Free-plan password-share creation and archive requests are rejected with HTTP 403. A synthetic local account was sandbox-upgraded to Basic, uploaded a test file, created a password-protected share, verified wrong-password rejection and successful unlock, and downloaded a ZIP archive whose integrity and contents passed `unzip -t`. Synthetic file, share, account, and local storage were cleaned up.
- ZIP generation passed in this environment without PHP `ZipArchive` by using the installed system `zip` binary. Deployments must provide either PHP ZipArchive or the `zip` executable for this archive feature.
- Deterministic session timeout logic passed boundary tests at a controlled clock value. Browser context termination and fresh-context reauthentication also passed; the production default is 1800 seconds and can be overridden with `SESSION_IDLE_TIMEOUT`.
- Temporary SMTP and AI settings were restored to unconfigured defaults, temporary services were stopped, and the Apache test configuration was removed and reloaded.

## Limits and outstanding coverage

- Live payment gateway behavior remains untested. The checkout flow is a local-only simulator, gated by both `APP_ENV=local` and `BILLING_SANDBOX=true`; it must remain disabled in production until a real provider integration is implemented.
- Live AI and real vendor-side OAuth/file/webhook acceptance remain untested because no approved sandbox credentials were available. Application request orchestration was verified against deterministic local callbacks; this does not establish vendor acceptance.
- Password change was tested through settings: the old password was rejected, the new password accepted, and the original local-admin password restored and verified. Idle expiry is deterministic and configurable; PHP server-side garbage collection remains environment-managed as an additional session-file cleanup mechanism.
- The route crawl covers discovered routes, not every dynamic query/identifier state. Cross-browser route and responsive checks passed; manual physical-device, assistive-technology, and animation quality review still requires human/device evaluation.
- Historical Apache log entries from before the corrections remain; test services and captured reset data were removed.
- The complete app feature inventory and per-case test status are recorded in the [live test matrix](./LOCAL_LIVE_TEST_MATRIX.md).

## Hosting archive

The workspace-root [`pocketcat_production_hosting.zip`](../../pocketcat_production_hosting.zip) is the production source bundle: it includes application code, `.htaccess`, bootstrap/configuration templates, and `.env.example`, while excluding tests, documentation/QA reports, evidence, `.git`, active secrets, runtime storage, and uploaded content. The separate [`pocketcat_full_dev_workspace.zip`](../../pocketcat_full_dev_workspace.zip) retains source, documentation, tests, changelogs, QA reports, and evidence while excluding `.git`, active secrets, and local runtime/account data. Upload-protection `.htaccess` files are retained in both. Neither archive contains a preinstalled site; configure production credentials and complete the install/hosting prerequisites before publishing.

## Visual evidence

Representative screenshots are stored in [docs/qa-evidence](./qa-evidence/):

- Home, desktop and mobile.
- Admin dashboard, desktop and mobile.
- Settings, mobile.
- Menu manager, desktop and mobile.

Cross-browser desktop and mobile screenshots and the route-by-route result JSON are stored in [docs/qa-evidence/cross-browser](./qa-evidence/cross-browser/).

## UI, uploader, and logo follow-up — 2026-10-24

Code changes in this follow-up:

1. File Storage now uses a bounded desktop upload grid, a stacked narrow-screen layout, and readable single-column cards when the saved preference selects list mode. Card preference controls no longer force the file title area into a narrow column.
2. Media Library chunk requests now expose startup/session status, have finite request timeouts, and automatically abort and retry a chunk when it makes no progress. A status probe still checks whether the server accepted the chunk before resending it.
3. Custom logos replace the separate visible site title, so text-bearing logos are not duplicated. Failed logo requests switch to the default mark and restore the site title. Logo writes verify destination writability/readability, file size, and safe permissions before reporting success.
4. Added focused regression checks and cache-busted the affected stylesheets.

`make check` passed with 11 configured PHP regression scripts and the PHP syntax scan. The inline Media Library JavaScript also parsed successfully in Node. These checks validate source-level behavior and syntax only.

### Live browser verification — 2026-10-24

The earlier blocked status was resolved by provisioning an isolated MySQL-backed local installation, PHP 8.4.27/Apache runtime, and Playwright 1.64.1 Chromium. The application responded at `http://127.0.0.1:8081`; the browser checks were run against this local instance only. MySQL 8.4.13 was used instead of SQLite because the application runtime in this workspace was configured for MySQL.

- `/files/` was inspected in Chromium at 1440×900 and 390×844. The desktop upload area measured 1032 px wide with a 680 px description column; mobile measured 328 px wide with 292 px of text space. Grid and list cards remained horizontal, their inspected descendants had no dashed vertical border, and document width matched viewport width at both sizes. Screenshots show the upload panel and file cards in both modes.
- `/media/` uploaded an 844 KB synthetic file after a single click. Seven chunk requests succeeded; the UI displayed 100% / 100%, a completed item, and the success notice without a Pause/Resume action. The asset appeared in the Media Library and File Storage.
- A synthetic SVG logo was uploaded through Site settings; both save and upload endpoints returned HTTP 200. The preview and shared navigation logo loaded with `naturalWidth=320`, and its URL returned HTTP 200. The separate visible site title is suppressed while a custom logo is active, preventing a duplicated brand; an intentionally failed logo request verified the default mark and (when enabled) exactly one title are restored on desktop and mobile. With the title option disabled, the error fallback showed only the mark.
- Desktop and mobile Chromium sessions reported no uncaught page errors. File Storage and logo pages had no horizontal overflow. Screenshots, desktop/mobile recordings, and structured browser results are in [live UI evidence](./qa-evidence/live-ui-2026-10-24/); the [live test matrix](./LOCAL_LIVE_TEST_MATRIX.md) records case-level outcomes.

`make check` passed after the final branding change, including the focused File Storage/media and site-branding regression checks and PHP syntax validation. The runtime/database used here are isolated local QA services, not production. Detailed result metrics are in [`live-ui-results.json`](./qa-evidence/live-ui-2026-10-24/live-ui-results.json); screenshots and desktop/mobile recordings, including the standalone logo-upload recording, are in that same evidence folder.

## Admin and user AI connection verification — 2026-10-25

Completed the live browser matrix for the global **Administration → Amazon Bedrock** settings (`/admin/ai/`) and per-user **My Settings → AWS Bedrock** overrides (`/settings/ai/`). The disposable local setup used a local OpenAI-compatible upstream, the exact generated Worker source executed by a local relay, and a Bedrock-compatible mock that cryptographically checked SigV4 authorization, timestamp, signed headers, payload hash, region/service scope, and session token. All credentials were synthetic; no AWS, Cloudflare, or external AI service was contacted.

- **Admin default:** Saved/tested OpenAI-compatible settings; reset and re-saved them; verified a user without an override inherited the configured Admin connection; then exercised Chat, Agent, and Text-to-Tasks. Repeated the provider test and all three modes using AWS IAM Direct and generated Worker Relay.
- **Per-user overrides:** Saved/tested each of the three connection types; verified the effective-connection card reported the override and current model/region; and exercised Chat, Agent, and Text-to-Tasks for each override.
- **Negative and dynamic cases:** Invalid relay tokens were rejected with HTTP 401; selected relay model IDs reached the upstream dynamically. Properly signed AWS requests passed strict SigV4 validation; an invalid signature was rejected with HTTP 403.
- **Browser and visual checks:** Chromium completed at desktop 1440×900 and mobile 390×844 with no uncaught JavaScript/page errors or horizontal overflow. Screenshots show each saved connection profile on both settings pages at both viewport sizes. Four WebM recordings cover Admin/User settings on desktop/mobile, with an additional full desktop E2E recording. I inspected all 12 profile screenshots; the connection fields and effective status match the active profile, and credential values are masked.

The [machine-readable run summary](./qa-evidence/ai-connections-live-qa-2026-10-25/verification-summary.json), [screenshots and recordings](./qa-evidence/ai-connections-live-qa-2026-10-25/), and [Playwright runner](./qa-evidence/ai-connections-live-qa-2026-10-25/run-browser-qa.js) are retained as evidence. These results verify application behavior against local deterministic mocks, not live AWS/Cloudflare account provisioning or production-provider acceptance.

## Regression command

```sh
make PHP=php8.3 check
```

The following additional target exercises external integration workflows through CLI-only deterministic stubs against the installed isolated QA database. It uses a transaction and rolls back all test records:

```sh
make PHP=php8.3 integration-mocks
```
