-- PocketCat V19.1 incremental production expansion. Back up before applying.
-- MySQL 8 / MariaDB compatible when applied once to a V19.0 database.
ALTER TABLE file_share_links
  ADD COLUMN revoked_at DATETIME NULL AFTER expires_at,
  ADD INDEX idx_file_share_revoked(user_id, revoked_at, expires_at);

-- Soft-delete lifecycle for canonical File Storage objects.
ALTER TABLE file_objects
  ADD COLUMN trashed_at DATETIME NULL AFTER status,
  ADD COLUMN trashed_by BIGINT UNSIGNED NULL AFTER trashed_at,
  ADD INDEX idx_file_trash_user_time(user_id,trashed_at);

-- Search and maintenance indexes; existing columns and rows are preserved.
ALTER TABLE media_assets
  ADD INDEX idx_media_user_updated(user_id, updated_at),
  ADD INDEX idx_media_user_kind_deleted(user_id, kind, deleted_at),
  ADD INDEX idx_media_user_favorite(user_id, is_favorite, deleted_at);

-- File-level activity records are intentionally metadata-only; no tokens or paths are stored.
CREATE TABLE IF NOT EXISTS storage_audit_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  resource_type VARCHAR(30) NOT NULL,
  resource_id BIGINT UNSIGNED NULL,
  request_id VARCHAR(64) NULL,
  metadata_json TEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_storage_audit_user_time(user_id,created_at),
  INDEX idx_storage_audit_resource(resource_type,resource_id,created_at),
  CONSTRAINT fk_storage_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
