# PocketCat V11.27 — API Access & Analytics

## API access
- One-time full-token copy box immediately after token creation.
- Active/disabled token state controls.
- Permanent token deletion.
- Copy-prefix helper for existing tokens.
- Token creation and state changes protected by CSRF.

## API analytics dashboard
- 24h / 7d / 30d timeframe selector.
- Total requests, success rate, average latency, active tokens and active endpoints.
- Request volume timeline.
- 2xx / 4xx / 5xx status breakdown with donut visualization.
- Top endpoint ranking.
- Token-level request usage.
- Per-minute rate-limit usage and remaining capacity.
- Privacy-safe hashed IP/origin table.

## Developer logs
- Request logging for authenticated link API calls.
- Filters for HTTP method, status code, API token and date range.
- Live Tail panel with 3-second polling.

## Webhooks
- Dashboard endpoint registration.
- Endpoint activate/disable/delete.
- One-time webhook signing-secret copy box.
- Delivery logs with request/response headers and bodies.
- Payload Inspector modal.
- Manual Redeliver action.
- Correct event names are stored instead of always recording `click`.
