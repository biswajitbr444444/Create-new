-- PocketCat V11.38 universal resumable upload sessions.
CREATE TABLE IF NOT EXISTS upload_sessions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 token CHAR(64) NOT NULL UNIQUE,
 scope VARCHAR(30) NOT NULL,
 context_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 original_name VARCHAR(220) NOT NULL,
 relative_path VARCHAR(600) NULL,
 mime_type VARCHAR(150) NOT NULL,
 size_bytes BIGINT UNSIGNED NOT NULL,
 received_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
 temp_path VARCHAR(900) NOT NULL,
 final_path VARCHAR(900) NULL,
 public_path VARCHAR(900) NULL,
 status ENUM('uploading','completed','aborted','failed') NOT NULL DEFAULT 'uploading',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX(user_id,status), INDEX(user_id,created_at),
 CONSTRAINT fk_upload_session_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
