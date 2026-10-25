# PocketCat V12 — File Storage & Sharing Deployment

## 1. Deploy
Upload the ZIP contents to the existing PocketCat application root, preserving the directory structure. Do not replace runtime `uploads/`, `storage/`, or user-generated content from the deployment archive.

## 2. Database migration
For an existing V11 installation, run:

`database/migration-v12-file-sharing-link-integration.sql`

Fresh installations use the updated `database/schema.sql`.

## 3. Verify upload limits
The module uses chunked browser uploads for local hosting fallback and multipart signed uploads for S3-compatible storage. Check the File Storage page diagnostics if PHP hosting limits are restrictive.

## 4. Verify link integration
1. Upload one or more clean files.
2. Select files and choose **Create share link**.
3. Confirm the returned URL is `/r/{code}`.
4. Open **Customize QR** and **Analytics** from the file action menu.
5. Open the public link in an incognito window and verify preview/download/password/expiry behavior.

## 5. Social / OG
Configure OG title/description/image when creating a share. The existing `/r/{code}` redirect serves OG metadata to crawler user agents before redirecting normal visitors to the file share page.

## 6. Security
Keep `uploads/files` non-executable. The application performs extension blocking, server-side MIME detection, normalized relative paths, CSRF checks, prepared SQL statements, plan/policy validation and atomic download-limit checks.
