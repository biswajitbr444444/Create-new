# PocketCat subscription catalogue

## Canonical storage

Subscription metadata and allocations are stored together in the `system_settings` row whose `setting_key` is `policy_engine`. This keeps the public name, price, billing period, description, enabled state, quota values, and boolean feature flags in the same saved policy document that the enforcement layer reads. Do not create a separate cache/table as a competing source of truth.

The document has three canonical keys: `free`, `basic`, and `premium`.

```json
{
  "plan_meta": {
    "free": {"name":"Free","price":"0","period":"month","description":"Start with the essentials.","enabled":true},
    "basic": {"name":"Basic","price":"3","period":"month","description":"More room for everyday work.","enabled":true},
    "premium": {"name":"Premium","price":"6","period":"month","description":"Advanced tools and higher limits.","enabled":true}
  },
  "plans": {
    "free": {"tasks":10,"projects":10,"notes":10,"ai_seconds":7200,"advanced_analytics":false},
    "basic": {"tasks":30,"projects":30,"notes":30,"ai_seconds":21600,"advanced_analytics":false},
    "premium": {"tasks":0,"projects":0,"notes":0,"ai_seconds":0,"advanced_analytics":true}
  }
}
```

The example above is abbreviated; all existing policy keys are retained. Numeric zero follows the existing policy semantics (unlimited for quota fields where defined); booleans are feature grants/denials.

## API

- `GET /api/plans/` returns enabled plans, public metadata, readable feature lists, and raw allocations. Responses are marked `no-store` so stale intermediary caches are not used.
- `POST /api/admin/plans/` or `PUT /api/admin/plans/` requires an administrator session and the session CSRF token (`X-CSRF-Token`). JSON may contain `plan_meta` and `plans`; it validates the supplied values and updates the same `policy_engine` setting.
- The server-rendered form at `/admin/plans/` also saves through `policy_save()` and the same canonical setting. Its sticky Save Plan Settings button remains visible while scrolling.

The public homepage, `/pricing/`, and `/billing/` render the same `includes/plan-card.php` partial using `plan_catalog()`.
