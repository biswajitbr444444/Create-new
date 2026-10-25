-- PocketCat V15: global testing mode + enterprise billing profile
-- Testing mode is enforced in application policy code so plan/subscription state
-- cannot re-enable paid gating while this test build is deployed.

CREATE TABLE IF NOT EXISTS user_billing_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    billing_name VARCHAR(180) NOT NULL DEFAULT '',
    company_name VARCHAR(180) NOT NULL DEFAULT '',
    address_line_1 VARCHAR(255) NOT NULL DEFAULT '',
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(120) NOT NULL DEFAULT '',
    state_province VARCHAR(120) NOT NULL DEFAULT '',
    postal_code VARCHAR(40) NOT NULL DEFAULT '',
    country VARCHAR(2) NOT NULL DEFAULT '',
    tax_id VARCHAR(120) NULL,
    vat_gst_number VARCHAR(120) NULL,
    billing_phone VARCHAR(40) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_user_billing_profile_user (user_id),
    INDEX idx_user_billing_country (country),
    CONSTRAINT fk_user_billing_profile_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep a visible, auditable deployment marker in system settings.
INSERT INTO system_settings(setting_key,setting_value)
VALUES('platform_testing_mode','1')
ON DUPLICATE KEY UPDATE setting_value='1';
