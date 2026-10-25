# AWS Activate and Production Readiness Notes

This checklist documents implementation areas to verify before submitting PocketCat to AWS Activate Founders or presenting it as a production service. AWS Activate approval is determined by AWS and cannot be guaranteed by changes to this repository.

## Product and architecture evidence

- Describe PocketCat as an early-stage productivity workspace for tasks, notes, projects and optional AI assistance.
- Explain that Amazon Bedrock is an optional integration and identify the configured model, AWS Region, request path and expected use case for the application environment.
- Keep demo or illustrative metrics visibly labeled and do not present them as independently verified business results.
- Document the actual hosting, database, backup, monitoring and incident-response arrangements used for the deployed instance.

## Credential and access controls

- Keep AWS credentials on the server or use an appropriate workload identity/role where the hosting environment supports it. Do not put AWS secret access keys in frontend code, public repositories, screenshots or customer content.
- Prefer least-privilege IAM permissions scoped to the required Bedrock models and actions. Rotate credentials if exposure is suspected.
- PocketCat’s Bedrock settings UI is write-only for saved secrets; the secret-retrieval endpoint rejects Bedrock/AWS credential fields. Verify this behavior again when changing secret-management code.
- Verify HTTPS, CSRF protection, administrator authorization, secret encryption key management and filesystem permissions in the deployed environment.
- Confirm that production error responses do not expose credentials, environment variables, request signatures or sensitive provider responses.

## Privacy and operational evidence

- Review the Terms, Privacy, Cookie, Acceptable Use and Security pages against the behavior and providers actually deployed. These pages are product documentation, not a substitute for legal review.
- Confirm the support channel is support@pocketcat.online and that the inbox is monitored.
- Test database backups and restoration, account recovery, rate limiting, logs, alerting and a documented response process.
- Verify actual Bedrock access, model availability, quotas and billing in the target AWS account. Do not claim the integration is operational until an authenticated test request succeeds in that environment.

## Before submission

- Use screenshots and a live demo that reflect the current product.
- Clearly state what is live, what is configurable and what is demonstration-only.
- Prepare a concise product description, target user, problem statement, architecture summary and intended AWS usage.
- Check the current AWS Activate program terms and application requirements directly with AWS before submitting.
