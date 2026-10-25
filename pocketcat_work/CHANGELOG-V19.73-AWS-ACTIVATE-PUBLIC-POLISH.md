# PocketCat public copy, Bedrock and policy polish

- Rewrote About sections 01–20 with unique section-specific copy and removed the repeated generic paragraph.
- Made Amazon Bedrock's optional role and server-side credential handling clearer across About, Features, How It Works, AI Settings and Security pages.
- Made saved Bedrock credentials write-only in the settings UI: removed reveal/copy controls and blocked the secret-retrieval endpoint for Bedrock/AWS credentials; added regression coverage.
- Replaced forward-looking cookie-policy filler with concrete descriptions of the application's intended current session and security-cookie behavior.
- Confirmed live PHP contact references use support@pocketcat.online; no alternate support/admin email was found in PHP application pages.
- Added an AWS Activate and production-readiness checklist that explicitly avoids guaranteeing program approval.

Validation is limited to static checks and the repository's existing test suite. No production browser session, live database, AWS account or Bedrock request was used for this update.
