-- VN BioLink Hub — MySQL 8.0 schema
-- Toàn bộ bảng dùng utf8mb4 để hỗ trợ đầy đủ tiếng Việt có dấu + emoji.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email          VARCHAR(190)     NOT NULL,
    password_hash  VARCHAR(255)     NOT NULL,
    display_name   VARCHAR(150)     NOT NULL DEFAULT 'Chủ trang',
    job_title      VARCHAR(150)     NULL,
    bio_text       TEXT             NULL,
    avatar_path    VARCHAR(255)     NULL,
    theme_color    VARCHAR(7)       NOT NULL DEFAULT '#6C4EF6',
    zalo_phone     VARCHAR(20)      NULL,
    hotline_phone  VARCHAR(20)      NULL,
    created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lưu ý: cột job_title đã có sẵn trong CREATE TABLE trên (áp dụng cho lần cài mới).
-- Với DB đã deploy trước khi có cột này, database/init.php tự kiểm tra qua
-- information_schema rồi ALTER TABLE thêm cột — MySQL không hỗ trợ cú pháp
-- "ADD COLUMN IF NOT EXISTS" (chỉ MariaDB có) nên không thể làm thẳng trong file .sql này.

CREATE TABLE IF NOT EXISTS links (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    type         ENUM('tiktok','shopee','website','facebook','instagram','youtube','custom')
                     NOT NULL DEFAULT 'custom',
    label        VARCHAR(150)     NOT NULL,
    url          VARCHAR(500)     NOT NULL,
    color        VARCHAR(7)       NULL,
    open_new_tab TINYINT(1)       NOT NULL DEFAULT 1,
    position     INT              NOT NULL DEFAULT 0,
    clicks       INT UNSIGNED     NOT NULL DEFAULT 0,
    is_active    TINYINT(1)       NOT NULL DEFAULT 1,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_links_user_id (user_id),
    CONSTRAINT fk_links_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log từng lượt click (khác với links.clicks chỉ là bộ đếm cộng dồn) — cần có
-- mốc thời gian thật để tính "lượt click hôm nay" + biểu đồ 7 ngày trên dashboard.
CREATE TABLE IF NOT EXISTS link_clicks (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    link_id     INT UNSIGNED NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_link_clicks_link_id (link_id),
    KEY idx_link_clicks_created_at (created_at),
    CONSTRAINT fk_link_clicks_link FOREIGN KEY (link_id) REFERENCES links (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(150)     NOT NULL,
    phone        VARCHAR(20)      NOT NULL,
    note         TEXT             NULL,
    status       ENUM('new','contacted','resolved') NOT NULL DEFAULT 'new',
    ip_address   VARCHAR(45)      NULL,
    user_agent   VARCHAR(255)     NULL,
    source_path  VARCHAR(255)     NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_leads_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lưu ý: các cột status/ip_address/user_agent/source_path đã có sẵn trong
-- CREATE TABLE trên (áp dụng cho lần cài mới). DB cũ được database/init.php
-- tự kiểm tra qua information_schema rồi ALTER TABLE thêm cột còn thiếu.
