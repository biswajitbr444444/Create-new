-- PocketCat V11.42: local GeoLite2-City analytics fields.
-- Runtime GeoIP is handled by geoip/GeoLite2-City.mmdb; no external GeoIP API is used.
ALTER TABLE analytics_events ADD COLUMN country_name VARCHAR(120) NULL AFTER country_code;
ALTER TABLE analytics_events ADD COLUMN latitude DECIMAL(10,7) NULL AFTER city;
ALTER TABLE analytics_events ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;
ALTER TABLE analytics_events ADD COLUMN timezone_name VARCHAR(80) NULL AFTER longitude;
ALTER TABLE analytics_sessions ADD COLUMN country_name VARCHAR(120) NULL AFTER country_code;
ALTER TABLE analytics_sessions ADD COLUMN latitude DECIMAL(10,7) NULL AFTER country_name;
ALTER TABLE analytics_sessions ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;
ALTER TABLE analytics_sessions ADD COLUMN timezone_name VARCHAR(80) NULL AFTER longitude;
