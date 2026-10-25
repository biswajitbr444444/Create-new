-- PocketCat V11.39 — universal upload/storage reliability + media SQL fix
-- Run on existing installations that already have the V11.38 schema.

CREATE TABLE IF NOT EXISTS user_storage_settings(
  user_id BIGINT UNSIGNED PRIMARY KEY,
  driver VARCHAR(30) NOT NULL DEFAULT 'local',
  storage_folder VARCHAR(120) NOT NULL DEFAULT 'PocketCat',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_user_storage_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE upload_sessions ADD COLUMN target_folder VARCHAR(600) NULL AFTER relative_path;

-- New file records should use the safe local fallback unless a valid provider is selected.
ALTER TABLE file_objects MODIFY storage_driver VARCHAR(30) NOT NULL DEFAULT 'local';

INSERT IGNORE INTO system_settings(setting_key,setting_value,updated_at)
VALUES('file_storage_driver','local',NOW());
