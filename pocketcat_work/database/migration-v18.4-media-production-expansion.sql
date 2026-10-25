-- PocketCat V18.4 media production expansion. Non-destructive; apply once.
ALTER TABLE media_assets
  ADD COLUMN deleted_at DATETIME NULL,
  ADD COLUMN deleted_by BIGINT UNSIGNED NULL,
  ADD COLUMN content_sha256 CHAR(64) NULL,
  ADD COLUMN last_accessed_at DATETIME NULL,
  ADD INDEX idx_media_trash_user_deleted(user_id,deleted_at),
  ADD INDEX idx_media_hash_user(user_id,content_sha256),
  ADD INDEX idx_media_recent(user_id,last_accessed_at);
-- Reuse existing SHA-256 ETags where available; this does not re-read stored bytes.
UPDATE media_assets m JOIN file_objects f ON f.id=m.canonical_file_id AND f.user_id=m.user_id SET m.content_sha256=f.etag WHERE m.content_sha256 IS NULL AND f.etag REGEXP '^[a-fA-F0-9]{64}$';

CREATE TABLE IF NOT EXISTS media_audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(40) NOT NULL,
 resource_type VARCHAR(30) NOT NULL DEFAULT 'media',
 resource_id BIGINT UNSIGNED NULL,
 metadata_json TEXT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_media_audit_user_time(user_id,created_at),
 INDEX idx_media_audit_resource(resource_type,resource_id),
 CONSTRAINT fk_media_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media_collections (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(180) NOT NULL,
 description VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_media_collection_name(user_id,name),
 INDEX idx_media_collections_user(user_id,updated_at),
 CONSTRAINT fk_media_collection_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media_collection_items (
 collection_id BIGINT UNSIGNED NOT NULL,
 media_id BIGINT UNSIGNED NOT NULL,
 added_at DATETIME NOT NULL,
 PRIMARY KEY(collection_id,media_id),
 INDEX idx_media_collection_item_media(media_id),
 CONSTRAINT fk_media_collection_item_collection FOREIGN KEY(collection_id) REFERENCES media_collections(id) ON DELETE CASCADE,
 CONSTRAINT fk_media_collection_item_asset FOREIGN KEY(media_id) REFERENCES media_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
