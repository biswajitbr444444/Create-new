# PocketCat Plan Catalogue — Data Model and API Contract

## Canonical storage model

PocketCat uses the existing `system_settings` table as the authoritative policy store; it does not duplicate plan entitlements into a second table. The canonical row is `setting_key = 'policy_engine'`. This JSON document is saved as one database value so a plan update is not partially applied across tier rows.

```json
{
  "version": 2,
  "plan_meta": {
    "free": {"name":"Free","price":"0","period":"month","enabled":true,"description":"Start with the essentials."},
    "basic": {"name":"Basic","price":"10","period":"month","enabled":true,"description":"More room for everyday work."},
    "premium": {"name":"Premium","price":"20","period":"month","enabled":true,"description":"Advanced tools and higher limits."}
  },
  "plans": {
    "free": {"tasks":10,"projects":10,"notes":10,"ai_seconds":7200,"url_api":false},
    "basic": {"tasks":30,"projects":30,"notes":30,"ai_seconds":21600,"url_api":false},
    "premium": {"tasks":0,"projects":0,"notes":0,"ai_seconds":0,"url_api":true}
  }
}
```

The values above illustrate the model shape, not a database seed or a promise about the currently configured prices. The actual live values are read from `policy_engine` and can differ. The complete `plans` objects include every supported quota, feature flag, and enumerated option defined in `policy_defaults()`.

- `plan_meta.<tier>.name`: 1–40 characters.
- `plan_meta.<tier>.price`: non-negative decimal string with up to two fractional digits.
- `plan_meta.<tier>.period`: `month`, `year`, or `one-time`.
- `plan_meta.<tier>.enabled`: boolean. Free must remain enabled for new registrations.
- `plan_meta.<tier>.description`: up to 240 characters.
- `plans.<tier>.<feature>`: booleans for feature grants, non-negative integers for numeric allocations, or enumerated strings for values such as URL redirect mode.
- `system_settings.policy_engine_updated_at`: ISO-8601 UTC timestamp written on policy save.
- `users.plan`: canonical tier key (`free`, `basic`, or `premium`); effective entitlements are resolved at runtime by `policy_effective()`.

## `GET /api/plans`

Public, read-only, JSON response with no-cache headers. It returns only enabled plans and uses the same catalogue helper as the homepage, `/pricing/`, and `/billing/`.

```json
{
  "success": true,
  "schema_version": 1,
  "updated_at": "2026-10-20T00:00:00+00:00",
  "plans": [
    {
      "key": "basic",
      "slug": "basic",
      "name": "Basic",
      "price": "10",
      "period": "month",
      "billing_interval": "month",
      "status": "active",
      "description": "More room for everyday work.",
      "features": ["tasks: 30", "projects: 30"],
      "allocations": {"tasks": 30, "projects": 30}
    }
  ]
}
```

The example is illustrative; real responses contain the complete generated `features` and `allocations` for each enabled tier.

## `POST /api/admin/plans` and `PUT /api/admin/plans`

Requires an authenticated administrator with super-admin policy permission, active admin view, and a same-origin CSRF token supplied in `X-CSRF-Token`. The payload must include all metadata fields and every configured allocation for all three canonical tiers. Boolean controls must be sent explicitly as `true` or `false` so unchecked boxes are persisted as false.

```json
{
  "plan_meta": {
    "free": {"name":"Free","price":"0","period":"month","description":"Start with the essentials.","enabled":true},
    "basic": {"name":"Basic","price":"10","period":"month","description":"More room for everyday work.","enabled":true},
    "premium": {"name":"Premium","price":"20","period":"month","description":"Advanced tools and higher limits.","enabled":true}
  },
  "plans": {
    "free": {"tasks":10,"projects":10,"notes":10,"ai_seconds":7200,"url_api":false},
    "basic": {"tasks":30,"projects":30,"notes":30,"ai_seconds":21600,"url_api":false},
    "premium": {"tasks":0,"projects":0,"notes":0,"ai_seconds":0,"url_api":true}
  }
}
```

The request example is abbreviated for readability; the actual endpoint intentionally rejects missing feature allocations. Success returns `success`, `message`, `updated_at`, `plans`, and `catalog`. Validation failures return HTTP 422 with a JSON `error`; invalid CSRF returns HTTP 419; unauthorized requests return HTTP 403; unexpected persistence failures return HTTP 500 without exposing internal database details.

## Entitlement lifecycle

The Free CTA supplies `plan=free`. Signup accepts only Free because checkout is not configured; it refuses registration if Free is disabled and inserts the canonical `users.plan = 'free'` value. Feature checks then read the latest policy matrix at runtime. No quota values are copied into a separate user record, so changes to Free allocations are reflected by policy evaluation on subsequent requests.
