# PocketCat V15 — Global Testing Mode & Billing Profile

- Enabled global Testing Mode in the policy layer. Registered users receive unlimited task/project/note, AI active-time, URL, file/storage, code and advanced analytics access regardless of the stored plan.
- Preserved non-plan safety behavior such as account status and maintenance mode.
- Disabled the authenticated Billing checkout/request workflow while retaining Basic/Premium cards as non-actionable previews.
- Added a global authenticated Testing Mode banner and updated billing/settings UI.
- Added `user_billing_profiles` for enterprise billing identity/address data.
- Added `/settings/` billing profile form with ISO country selection, validation, CSRF protection and persistence.
- Added SQL migration: `database/migration-v15-testing-mode-billing-profile.sql`.
