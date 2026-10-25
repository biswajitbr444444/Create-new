# PocketCat V11.58 — Support Chat + Plan Selection Fix

## Fixed
- Fixed plan selection checkout: the generated waiting token is now correctly inserted into `subscription_requests`.
- Fixed the broken `Continue to Admin Support` flow that could fail before the support thread was created.
- Support Chat is now available to every logged-in user without requiring a paid plan first.
- `/support/` automatically creates a private general-support thread when the user has no existing thread.
- Billing plan requests still create a dedicated support thread linked to the subscription request.
- Added a visible `Open Support Chat` action on the Plans & Billing page.
- Added a direct Support Chat action on the support page.
- User support polling now refreshes the displayed thread status.
- General support threads show a clear General Support panel instead of an empty plan/request panel.
- Existing admin Support Chat continues to list user threads and receive/reply to messages.
- Multi-file support attachments continue to use the resumable upload flow.

## Validation
- PHP syntax checks passed for the updated files.
- ZIP structure remains deployable at the archive root.
- No destructive database migration was introduced.
