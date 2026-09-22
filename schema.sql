-- Waymark schema
-- Import this once via phpMyAdmin after creating your database in cPanel.
-- Requires MySQL 5.6+ / MariaDB 10.0.5+ (InnoDB FULLTEXT support).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email              VARCHAR(255) NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,
    bookmarklet_token  VARCHAR(64)  NOT NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email (email),
    UNIQUE KEY uniq_bookmarklet_token (bookmarklet_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collections (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    name         VARCHAR(255) NOT NULL,
    share_token  VARCHAR(64)  NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_collections_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_collection_name (user_id, name),
    UNIQUE KEY uniq_share_token (share_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS links (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    collection_id    INT UNSIGNED NULL,
    url              VARCHAR(2048) NOT NULL,
    title            VARCHAR(512)  NOT NULL DEFAULT '',
    description      TEXT NULL,
    image_url        VARCHAR(2048) NULL,
    tags_cache       TEXT NULL,
    is_broken        TINYINT(1)    NOT NULL DEFAULT 0,
    last_checked_at  DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_links_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_links_collection FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE SET NULL,
    INDEX idx_links_user (user_id),
    INDEX idx_links_collection (collection_id),
    INDEX idx_links_last_checked (last_checked_at),
    INDEX idx_links_url (url(191)),
    FULLTEXT KEY ft_links_search (title, description, url, tags_cache)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tags (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id  INT UNSIGNED NOT NULL,
    name     VARCHAR(100) NOT NULL,
    CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_tag (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS link_tags (
    link_id  INT UNSIGNED NOT NULL,
    tag_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (link_id, tag_id),
    CONSTRAINT fk_link_tags_link FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
    CONSTRAINT fk_link_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_link_tags_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
