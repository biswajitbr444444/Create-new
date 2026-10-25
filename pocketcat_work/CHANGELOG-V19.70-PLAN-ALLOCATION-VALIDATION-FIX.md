# PocketCat V19.70 — Plan Allocation Validation Fix

- **RELEASED:** 2026-10-20
- **SCOPE:** V19.69 → V19.70
- **STATUS:** Source-level fix implemented; automated tests and PHP lint are run before packaging. Production database/browser verification remains environment-dependent.

## Changes

- **Plan save API (`api/admin/plans.php`)** → Saving the existing Premium storage quota of 1 TiB (`1,099,511,627,776` bytes) failed because the generic validator capped every numeric allocation at `1,000,000,000,000` → added shared per-feature allocation validation and raised the byte-quota maximum to 2 TiB, matching the existing policy/control-plane range.
- **Native admin form (`admin/plans.php`)** → The non-JavaScript form path used the same too-low generic cap and could reject valid byte quotas independently of the API → routed numeric form values through the same shared validator used by the API.
- **Numeric validation (`includes/policy.php`)** → API values were checked with generic numeric conversion and then cast to integers, which could silently truncate fractional values → introduced `policy_allocation_max()` and `policy_validate_numeric_allocation()` to require non-negative integers, enforce the feature-specific maximum, reject fractional values, and produce a clear validation error.
- **Regression coverage (`tests/check-plan-catalog.php`)** → Existing tests did not cover realistic TiB-sized allocations or invalid fractional/oversized values → added checks for 1 TiB storage, 20 GiB file size, the 2 TiB maximum, fractional rejection, and over-limit rejection.
- **Mobile allocation editor (`admin/plans.php`, `assets/css/app.css`)** → The three-tier matrix forced a wide table on phones, hiding Basic and Premium values off-screen → changed the feature rows to labelled stacked controls below 700px so Free, Basic, and Premium allocations are all visible without horizontal scrolling.
- **Asset cache invalidation (`includes/functions.php`, `admin/plans.php`)** → Browsers could retain older CSS or admin-save script assets after deployment → bumped CSS and plan-settings JavaScript cache versions to V19.70.
- **Version Details (`VERSION`, `admin/version-details.php`, `admin/index.php`)** → Release metadata still pointed at V19.69 → incremented the release to V19.70, changed scope to V19.69 → V19.70, and updated the Version Details summary and release-note path.

## Operational notes

- The plan document remains stored atomically in the existing `system_settings.policy_engine` record; no database migration is required.
- Both the asynchronous API submission and native PHP form submission now share the same numeric allocation rules.
- Byte quota fields (`file_storage_bytes`, `file_max_bytes`) accept non-negative integer values up to 2 TiB (`2,199,023,255,552` bytes). Other numeric plan allocations retain the 1,000,000,000,000 maximum. Fractional, negative, missing, and over-limit values are rejected.
- Automated checks cover valid TiB/GiB values, invalid fractional values, values above the maximum, existing catalogue behavior, and PHP syntax. These checks do not prove live production database connectivity or browser rendering.

## Staging smoke tests

1. Open `/admin/plans/` and save without changing the current 1 TiB Premium storage allocation; confirm a success message.
2. Change Basic/Premium prices and one feature quota, save, reload, and confirm the values persist.
3. Verify `/api/plans`, `/pricing/`, the homepage, and `/billing/` reflect the saved catalogue.
4. Confirm fractional byte values and values above 2 TiB are rejected with a clear message and do not mutate saved policy.
5. Test the admin form with JavaScript disabled to verify the native submission path accepts the valid 1 TiB value.
