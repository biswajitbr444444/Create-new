-- PocketCat V11.36 — User Media Library tenancy
-- Safe for existing installs: user_id remains nullable so legacy/admin media is not broken.
ALTER TABLE media_assets ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id;
CREATE INDEX idx_media_user_folder ON media_assets(user_id,folder_name);
CREATE INDEX idx_media_user_created ON media_assets(user_id,created_at);
