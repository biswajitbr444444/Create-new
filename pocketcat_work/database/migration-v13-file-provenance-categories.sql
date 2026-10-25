-- PocketCat V13: upload provenance + user Media Library categories.
-- Safe to run on an existing V12 database. Run once before/with deployment.
SET @db=DATABASE();

SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE media_assets ADD COLUMN source_category VARCHAR(80) NOT NULL DEFAULT ''Media Assets'' AFTER source_type','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_assets' AND COLUMN_NAME='source_category'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE media_assets ADD COLUMN source_ref_id BIGINT UNSIGNED NULL AFTER source_category','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_assets' AND COLUMN_NAME='source_ref_id'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE media_assets ADD INDEX idx_media_source_category(user_id,source_category,created_at)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_assets' AND INDEX_NAME='idx_media_source_category'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE file_objects ADD COLUMN source_type VARCHAR(40) NOT NULL DEFAULT ''storage'' AFTER kind','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='file_objects' AND COLUMN_NAME='source_type'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE file_objects ADD COLUMN source_category VARCHAR(80) NOT NULL DEFAULT ''Shared Files'' AFTER source_type','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='file_objects' AND COLUMN_NAME='source_category'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE file_objects ADD INDEX idx_file_source_category(user_id,source_category,created_at)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='file_objects' AND INDEX_NAME='idx_file_source_category'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE chat_attachments ADD COLUMN storage_path VARCHAR(900) NULL AFTER stored_path','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='chat_attachments' AND COLUMN_NAME='storage_path'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE chat_attachments ADD COLUMN source_type VARCHAR(40) NOT NULL DEFAULT ''ai'' AFTER kind','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='chat_attachments' AND COLUMN_NAME='source_type'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE chat_attachments ADD COLUMN source_category VARCHAR(80) NOT NULL DEFAULT ''AI Workspace'' AFTER source_type','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='chat_attachments' AND COLUMN_NAME='source_category'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE chat_attachments ADD INDEX idx_chat_source_category(user_id,source_category,created_at)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='chat_attachments' AND INDEX_NAME='idx_chat_source_category'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE support_messages ADD COLUMN attachment_category VARCHAR(80) NULL AFTER attachment_paths','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='support_messages' AND COLUMN_NAME='attachment_category'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE upload_sessions ADD COLUMN source_type VARCHAR(40) NOT NULL DEFAULT ''files'' AFTER scope','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='upload_sessions' AND COLUMN_NAME='source_type'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE upload_sessions ADD COLUMN source_category VARCHAR(80) NOT NULL DEFAULT ''Shared Files'' AFTER source_type','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='upload_sessions' AND COLUMN_NAME='source_category'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill native V12 records with deterministic provenance.
UPDATE file_objects SET source_type='storage',source_category='Shared Files' WHERE source_category IS NULL OR source_category='';
UPDATE chat_attachments SET source_type='ai',source_category='AI Workspace' WHERE source_category IS NULL OR source_category='';
UPDATE media_assets SET source_category='Media Assets' WHERE source_category IS NULL OR source_category='';
UPDATE support_messages SET attachment_category='Support Attachments' WHERE attachment_path IS NOT NULL OR attachment_paths IS NOT NULL;

-- Backfill file storage and AI attachments into the user's Media Library.
INSERT INTO media_assets(user_id,name,folder_name,source_type,source_category,source_ref_id,source_url,storage_path,mime_type,size_bytes,kind,auto_fit,created_at,updated_at)
SELECT f.user_id,f.original_name,'Storage','upload','Shared Files',f.id,
       CONCAT('/file/',f.public_token),
       COALESCE(f.provider_url,''),f.mime_type,f.size_bytes,
       CASE WHEN f.kind IN ('image','video','audio','archive') THEN f.kind ELSE 'file' END,1,f.created_at,f.updated_at
FROM file_objects f
LEFT JOIN media_assets m ON m.user_id=f.user_id AND m.source_category='Shared Files' AND m.source_ref_id=f.id
WHERE m.id IS NULL AND f.status<>'deleted';

INSERT INTO media_assets(user_id,name,folder_name,source_type,source_category,source_ref_id,source_url,storage_path,mime_type,size_bytes,kind,auto_fit,created_at,updated_at)
SELECT a.user_id,a.name,'AI Workspace','upload','AI Workspace',a.id,a.stored_path,a.stored_path,a.mime_type,a.size_bytes,a.kind,1,a.created_at,a.created_at
FROM chat_attachments a
LEFT JOIN media_assets m ON m.user_id=a.user_id AND m.source_category='AI Workspace' AND m.source_ref_id=a.id
WHERE m.id IS NULL;
