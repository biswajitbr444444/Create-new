-- PocketCat V14: unify Media Library and File Storage around file_objects.
-- Run after V13. Safe to re-run.
SET @db=DATABASE();

SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE media_assets ADD COLUMN canonical_file_id BIGINT UNSIGNED NULL AFTER source_ref_id','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_assets' AND COLUMN_NAME='canonical_file_id'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE media_assets ADD INDEX idx_media_canonical_file(user_id,canonical_file_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_assets' AND INDEX_NAME='idx_media_canonical_file'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Existing File Storage records already mirrored into Media Library by V13.
UPDATE media_assets m JOIN file_objects f ON f.user_id=m.user_id AND f.source_category='Shared Files' AND f.id=m.source_ref_id
SET m.canonical_file_id=f.id
WHERE m.source_category='Shared Files' AND (m.canonical_file_id IS NULL OR m.canonical_file_id=0);

-- Backfill legacy locally-uploaded Media Library files into the canonical file table.
-- The file engine maps object_key values beginning with media/ to uploads/media/.
INSERT INTO file_objects(user_id,public_token,object_key,original_name,relative_path,mime_type,size_bytes,kind,source_type,source_category,storage_driver,status,scan_status,created_at,updated_at)
SELECT m.user_id,MD5(CONCAT('pocketcat-media-',m.id)),
       REPLACE(SUBSTRING_INDEX(m.source_url,'?',1),'/uploads/',''),
       m.name,CONCAT(m.folder_name,'/',m.name),COALESCE(m.mime_type,'application/octet-stream'),m.size_bytes,
       CASE WHEN m.kind IN ('image','audio','video','archive') THEN m.kind ELSE 'file' END,
       'media','Media Assets','local','clean','clean',m.created_at,COALESCE(m.updated_at,m.created_at)
FROM media_assets m
LEFT JOIN file_objects f ON f.object_key=REPLACE(SUBSTRING_INDEX(m.source_url,'?',1),'/uploads/','')
WHERE m.name<>'.folder-marker'
  AND m.source_type='upload'
  AND m.source_url LIKE '/uploads/media/%'
  AND f.id IS NULL;

UPDATE media_assets m JOIN file_objects f ON f.object_key=REPLACE(SUBSTRING_INDEX(m.source_url,'?',1),'/uploads/','')
SET m.canonical_file_id=f.id
WHERE m.name<>'.folder-marker' AND m.source_url LIKE '/uploads/media/%' AND (m.canonical_file_id IS NULL OR m.canonical_file_id=0);

-- Normalize all canonical Media Library file objects.
UPDATE file_objects SET source_type='media',source_category='Media Assets'
WHERE source_type='media' OR source_category='Media Assets';
