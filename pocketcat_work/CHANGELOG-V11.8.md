# PocketCat V11.9 — Links & Bulk CSV UX Fixes

## Bulk CSV
- Added a clear/remove X control for the selected CSV file.
- Added client-side `.csv` validation before import.
- Added server-side upload-error, extension, size and empty-row validation.
- Import no longer silently submits when no file is attached.

## Link list
- Added one-click Copy controls for Short URL and Destination URL.
- Added visible `Copied!` feedback with clipboard fallback.
- Added fixed-width, horizontally scrollable URL cells for mobile alignment.
- Kept the action column intact with stable minimum width.

## Edit
- Replaced fragile query-string edit state with a proper modal editor.
- Edit modal is pre-populated from the selected row.
- Save uses a same-origin session-protected PATCH API with CSRF validation.
- Successful edits update the row in-place without breaking the table.
- Existing POST update path remains as a no-JavaScript fallback.
