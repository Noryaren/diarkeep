-- DiarKeep database schema
-- Works on MariaDB 10.6+ and MySQL 8.0+
-- Usage: mariadb -u diarkeep -p diarkeep < database/schema.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(80)     NOT NULL,
    email         VARCHAR(190)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notes (
    id         BIGINT UNSIGNED                NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED                NOT NULL,
    type       ENUM ('text', 'list')          NOT NULL DEFAULT 'text',
    title      VARCHAR(255)                   NOT NULL DEFAULT '',
    body       MEDIUMTEXT                     NULL,
    color      VARCHAR(16)                    NOT NULL DEFAULT 'default',
    pinned     TINYINT(1)                     NOT NULL DEFAULT 0,
    archived   TINYINT(1)                     NOT NULL DEFAULT 0,
    trashed_at DATETIME                       NULL,
    created_at DATETIME                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notes_user_state (user_id, trashed_at, archived, pinned, updated_at),
    KEY idx_notes_updated (user_id, updated_at),
    CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS note_items (
    id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    note_id  BIGINT UNSIGNED NOT NULL,
    content  VARCHAR(1000)   NOT NULL DEFAULT '',
    checked  TINYINT(1)      NOT NULL DEFAULT 0,
    position INT             NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_note_items_note (note_id, position, id),
    CONSTRAINT fk_note_items_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS labels (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    name       VARCHAR(80)     NOT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_labels_user_name (user_id, name),
    CONSTRAINT fk_labels_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS note_labels (
    note_id  BIGINT UNSIGNED NOT NULL,
    label_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (note_id, label_id),
    KEY idx_note_labels_label (label_id),
    CONSTRAINT fk_note_labels_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE,
    CONSTRAINT fk_note_labels_label FOREIGN KEY (label_id) REFERENCES labels (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    note_id    BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    filename   VARCHAR(255)    NOT NULL,
    thumb      VARCHAR(255)    NOT NULL,
    mime       VARCHAR(100)    NOT NULL,
    size       INT UNSIGNED    NOT NULL DEFAULT 0,
    width      INT UNSIGNED    NOT NULL DEFAULT 0,
    height     INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attachments_note (note_id, id),
    CONSTRAINT fk_attachments_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
