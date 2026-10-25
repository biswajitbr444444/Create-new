# PocketCat V11.48 — Local GeoIP Lookup Fix

- Fixed the IPv4-in-IPv6 MaxMind tree start-node logic to match the official MaxMind PHP reader behavior.
- Hardened local MaxMind metadata validation so the admin page reports a database as Ready only when the MMDB can actually be parsed.
- Added a visible reader-validation error on the Local GeoIP admin page when the existing 62.3 MB file is present but invalid/unreadable.
- Local lookup test results are preserved after POST so the returned GeoIP record can be inspected instead of being lost on redirect.
- GeoLite2 installer now validates the downloaded MMDB with the local reader before reporting installation success.
- Kept runtime GeoIP fully local; no ipapi.co, FreeIPAPI, or other visitor lookup API is used.
