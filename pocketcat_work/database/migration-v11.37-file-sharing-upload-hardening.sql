-- PocketCat V11.37 — upload hardening + folder paths + sharing governance
ALTER TABLE file_objects ADD COLUMN relative_path VARCHAR(600) NULL AFTER original_name;
ALTER TABLE file_objects ADD COLUMN view_only TINYINT(1) NOT NULL DEFAULT 0 AFTER burn_after_reading;
CREATE INDEX idx_file_user_relative ON file_objects(user_id,relative_path(180));
CREATE TABLE IF NOT EXISTS file_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 file_id BIGINT UNSIGNED NOT NULL,
 version_no INT UNSIGNED NOT NULL,
 object_key VARCHAR(600) NOT NULL,
 size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
 etag VARCHAR(120) NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_file_version(file_id,version_no),
 INDEX(file_id,created_at),
 CONSTRAINT fk_file_version_file FOREIGN KEY(file_id) REFERENCES file_objects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS file_share_links (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 token CHAR(48) NOT NULL UNIQUE,
 title VARCHAR(220) NOT NULL,
 password_hash VARCHAR(255) NULL,
 expires_at DATETIME NULL,
 max_downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,
 downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,
 view_only TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX(user_id), INDEX(expires_at),
 CONSTRAINT fk_file_share_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS file_share_items (
 share_id BIGINT UNSIGNED NOT NULL,
 file_id BIGINT UNSIGNED NOT NULL,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(share_id,file_id),
 CONSTRAINT fk_file_share_item_share FOREIGN KEY(share_id) REFERENCES file_share_links(id) ON DELETE CASCADE,
 CONSTRAINT fk_file_share_item_file FOREIGN KEY(file_id) REFERENCES file_objects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
