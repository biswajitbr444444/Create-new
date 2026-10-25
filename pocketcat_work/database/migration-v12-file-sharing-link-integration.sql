-- PocketCat V12 — File Storage & Sharing ↔ Link & Analytics integration
-- Safe for existing installations. Run once after V11.58.
SET @db=DATABASE();

-- New share presentation / link bridge fields.
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE file_share_links ADD COLUMN link_id BIGINT UNSIGNED NULL AFTER id','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='file_share_links' AND COLUMN_NAME='link_id'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE file_share_links ADD COLUMN theme_json TEXT NULL AFTER view_only','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='file_share_links' AND COLUMN_NAME='theme_json'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE file_share_links ADD COLUMN og_title VARCHAR(220) NULL AFTER theme_json','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='file_share_links' AND COLUMN_NAME='og_title'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE file_share_links ADD COLUMN og_description TEXT NULL AFTER og_title','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='file_share_links' AND COLUMN_NAME='og_description'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE file_share_links ADD COLUMN og_image_url TEXT NULL AFTER og_description','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='file_share_links' AND COLUMN_NAME='og_image_url'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE file_share_links ADD INDEX idx_file_share_link_id(link_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='file_share_links' AND INDEX_NAME='idx_file_share_link_id'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS file_share_events(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 share_id BIGINT UNSIGNED NOT NULL,
 file_id BIGINT UNSIGNED NULL,
 event_type VARCHAR(40) NOT NULL,
 visitor_hash CHAR(64) NULL,
 ip_hash CHAR(64) NULL,
 referrer VARCHAR(500) NULL,
 user_agent VARCHAR(1000) NULL,
 occurred_at DATETIME NOT NULL,
 INDEX idx_share_event(share_id,occurred_at),
 INDEX idx_share_event_type(share_id,event_type,occurred_at),
 CONSTRAINT fk_file_share_event_share FOREIGN KEY(share_id) REFERENCES file_share_links(id) ON DELETE CASCADE,
 CONSTRAINT fk_file_share_event_file FOREIGN KEY(file_id) REFERENCES file_objects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing share rows remain valid. New shares receive link_id automatically.
