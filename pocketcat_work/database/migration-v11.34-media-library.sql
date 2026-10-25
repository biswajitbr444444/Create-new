-- PocketCat V11.34 Media Library
ALTER TABLE media_assets ADD COLUMN alt_text VARCHAR(500) NULL AFTER name;
ALTER TABLE media_assets ADD COLUMN description TEXT NULL AFTER alt_text;
ALTER TABLE media_assets ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_fit;
ALTER TABLE media_assets ADD COLUMN updated_at DATETIME NULL AFTER created_at;
CREATE INDEX idx_media_folder_kind ON media_assets(folder_name,kind);
CREATE INDEX idx_media_favorite ON media_assets(is_favorite,created_at);
CREATE INDEX idx_media_name ON media_assets(name);
