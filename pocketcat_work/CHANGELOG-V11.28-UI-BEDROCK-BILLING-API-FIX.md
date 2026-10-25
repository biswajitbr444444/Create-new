# PocketCat V11.28 — UI, Bedrock, Billing & API Access Fix

## Implemented

### AWS Bedrock
- Added dynamic Connection Type handling for both Admin and My Settings.
- OpenAI-Compatible Base + API Key shows only Base URL, API Key and Model ID.
- AWS IAM Direct shows only Access Key ID, Secret Access Key, Session Token, Region and Model ID.
- Cloudflare Worker Relay shows only Relay URL, Relay Shared Token and Model ID.
- Switching connection type clears stale credentials from inactive connection paths.
- Fixed secret reveal/copy interaction so the eye control no longer opens intrusive `Not configured` alerts.
- Saved secrets can be revealed/copy-fetched without replacing them until the user edits the field.
- Added inline copy/reveal feedback.
- User Bedrock status now recognizes a configured relay connection.

### Developer API / Analytics
- New API token screen uses a mobile/desktop-friendly readonly copy box.
- Full token remains visible only immediately after creation.
- Copy actions no longer use intrusive alert dialogs.
- Existing token controls retain Activate/Disable/Delete and prefix copy actions.
- Status donut now correctly represents the 2xx share instead of whichever status row happened to be first.
- API dashboard collapses cleanly when the sidebar plus desktop-site mode leaves a narrow content area.

### Billing / Upgrade
- Added visible Current plan indicator.
- Active plan is clearly marked and its purchase action is disabled.
- Server-side protection prevents submitting the already-active plan.
- Billing cards switch from 3 columns to 2 and then 1 based on available width, including desktop-site mode on mobile.
- Payment method controls use a responsive two-column/one-column grid without horizontal clipping.

### Responsive / Asset Cache
- Added focused V11.28 responsive styles for billing, Bedrock forms and secret controls.
- Bumped frontend asset query versions to V11.28.

## Validation
- PHP syntax checked for all modified PHP files.
- JavaScript syntax checked for the updated secret-controls script.
