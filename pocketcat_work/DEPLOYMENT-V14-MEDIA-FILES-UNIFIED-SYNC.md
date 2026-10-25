# PocketCat V14 — Media Library ↔ File Storage Unified Sync

## Goal

`file_objects` is the canonical record for every uploaded file. `media_assets` remains the Media Library presentation/catalog table and points to the canonical file through `canonical_file_id`.

New Media Library uploads create both records in one database transaction. The bytes remain in the same PocketCat file-access layer and are downloadable/previewable from `/files/`.

## Database

Run, once, after V13:

`database/migration-v14-media-files-unified-sync.sql`

It adds `media_assets.canonical_file_id`, backfills existing File Storage ↔ Media rows, and creates canonical `file_objects` records for legacy local `/uploads/media/...` uploads.

## Exact application paths changed

### Controllers / services

- `includes/files.php`
  - canonical media-file creation
  - unified local path resolver for `media/...` object keys
  - catalog synchronization
- `includes/upload-engine.php`
  - Media upload finalization now calls canonical file creation
- `api/upload-chunk.php`
  - existing Media chunk route automatically reaches the new canonical persist path
- `api/media.php`
  - multipart fallback also creates canonical file records
- `api/files.php`
  - deleting a canonical file removes its linked Media Library catalog record
- `includes/asset-catalog.php`
  - canonical `file_objects` ↔ `media_assets` registration support

### Views

- `media/index.php`
  - joins `media_assets` to `file_objects`
  - uses canonical file URLs
  - adds `Open in Files`
  - adds `/files/?highlight={file_id}` deep links
  - synchronized delete
- `files/index.php`
  - every file row/card receives `id="file-{file_id}"`

### Frontend

- `assets/js/file-sharing-v12.js`
  - reads `?highlight={file_id}`
  - smooth-scrolls the matching file into view
  - applies temporary blue highlight
- `assets/css/file-sharing-v12.css`
  - deep-link scroll offset and blue glow animation

## URL contract

Media Library → File Storage:

`/files/?highlight=123`

Stable DOM anchor:

`#file-123`

The query parameter is preferred because it works even when the browser has no existing hash state.

## Deep-link JavaScript

The implementation appended to `assets/js/file-sharing-v12.js` is:

```js
function highlightRequestedFile(){
  const params=new URLSearchParams(window.location.search),
        raw=params.get('highlight')||'';
  if(!/^\d+$/.test(raw)) return;

  const card=document.getElementById('file-'+raw);
  if(!card) return;

  card.classList.add('file-deep-highlight');
  setTimeout(()=>card.scrollIntoView({behavior:'smooth',block:'center'}),80);
  setTimeout(()=>card.classList.remove('file-deep-highlight'),6500);
}

if(document.readyState==='loading')
  document.addEventListener('DOMContentLoaded',highlightRequestedFile);
else
  highlightRequestedFile();
```

## Deep-link CSS

The implementation appended to `assets/css/file-sharing-v12.css` is:

```css
.file-card{
  scroll-margin-top:96px;
  transition:box-shadow .25s ease,border-color .25s ease,transform .25s ease
}

.file-card.file-deep-highlight{
  border-color:#2563eb;
  box-shadow:0 0 0 3px rgba(37,99,235,.22),0 18px 44px rgba(37,99,235,.18);
  transform:translateY(-2px);
  animation:fileDeepPulse 1.15s ease-in-out 2
}

@keyframes fileDeepPulse{
  0%,100%{
    box-shadow:0 0 0 3px rgba(37,99,235,.18),0 18px 44px rgba(37,99,235,.14)
  }
  50%{
    box-shadow:0 0 0 6px rgba(37,99,235,.28),0 20px 52px rgba(37,99,235,.24)
  }
}
```

## Media card button

The Media Library renders:

```php
<?php if(!empty($m['canonical_file_id'])): ?>
  <a class="edit-btn primary"
     href="/files/?highlight=<?=e((string)$m['canonical_file_id'])?>">
     Open in Files
  </a>
<?php endif; ?>
```

The same target is available from the asset action menu.

## Synchronization behavior

### Media → Files

1. User selects one or more files in `/media/`.
2. `/api/upload-chunk.php` creates an upload session with `scope=media`.
3. Chunks are finalized by `upload_session_finalize()`.
4. `upload_persist_result()` calls `file_create_from_media_upload()`.
5. A canonical `file_objects` row is inserted with:
   - `source_type=media`
   - `source_category=Media Assets`
   - `storage_driver=local`
   - `status=clean`
6. `media_assets.canonical_file_id` points at the new file row.
7. `/files/` automatically finds the same record.

### Files → Media

The existing V13 `file_catalog_sync()` continues to register normal File Storage uploads in `media_assets` with `source_category=Shared Files`. V14 also stores `canonical_file_id`, so the same file remains addressable from both interfaces.

### Delete synchronization

- Deleting a File Storage record marks `file_objects.status='deleted'` and removes its linked `media_assets` row.
- Deleting a Media Library item with a canonical file marks that `file_objects` row deleted and removes the Media Library row.
- Legacy non-canonical Media URL assets remain independent external assets.

## Testing checklist

1. Run the migration.
2. Upload one image from `/media/`.
3. Confirm it appears in `/media/`.
4. Open `/files/` and confirm the same filename exists.
5. Click **Open in Files** from `/media/`.
6. Confirm the browser lands on `/files/?highlight=ID`.
7. Confirm the file scrolls to the center and receives the blue glow.
8. From the highlighted file, test Preview, Download, Create share link, QR and Analytics.
9. Delete the file from `/files/`; refresh `/media/`; confirm the asset disappears.
10. Upload a normal file from `/files/`; confirm it appears in `/media/` under **Shared Files**.
11. Repeat with multiple files and a folder upload.

## Important legacy note

The V14 migration does not physically move legacy files. Existing `/uploads/media/...` objects are represented by canonical `file_objects` rows using `object_key=media/<user>/<relative-path>`. `file_local_path()` resolves those keys back to the existing Media storage directory, so downloads and analytics use the same file engine without a risky bulk filesystem move.
