SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- Lookup / Configuration tables
-- -------------------------------------------------------------------------

CREATE TABLE roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(40)  NOT NULL UNIQUE,
    title       VARCHAR(80)  NOT NULL,
    description TEXT         NULL,
    is_system   TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  SMALLINT     NOT NULL DEFAULT 100,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME     NULL                      -- ← added
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL UNIQUE,
    title       VARCHAR(120) NOT NULL,
    category    VARCHAR(50)  NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME     NULL                      -- ← added
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE ticket_statuses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(40)  NOT NULL UNIQUE,
    title       VARCHAR(80)  NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    is_closed   TINYINT(1)   NOT NULL DEFAULT 0,
    is_resolved TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  SMALLINT     NOT NULL DEFAULT 100,
    color_hex   CHAR(7)      NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME     NULL                      -- ← added
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE ticket_priorities (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(40)  NOT NULL UNIQUE,
    level       TINYINT UNSIGNED NOT NULL,
    color_hex   CHAR(7)      NULL,
    sort_order  SMALLINT     NOT NULL DEFAULT 100,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME     NULL                      -- ← added
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE ticket_types (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL UNIQUE,
    title       VARCHAR(100) NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  SMALLINT     NOT NULL DEFAULT 100,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME     NULL                      -- ← added
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE ticket_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL UNIQUE,
    title       VARCHAR(100) NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  SMALLINT     NOT NULL DEFAULT 100,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME     NULL                      -- ← added
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- Users
-- -------------------------------------------------------------------------

CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(60)     NOT NULL UNIQUE,
    email           VARCHAR(120)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    first_name      VARCHAR(120)    NOT NULL,
    last_name       VARCHAR(120)    NOT NULL,
    middle_name     VARCHAR(120)    NOT NULL,
    display_name    VARCHAR(80)     NULL,
    role_id         INT UNSIGNED    NOT NULL DEFAULT 1,
    phone           VARCHAR(30)     NULL,
    avatar_url      VARCHAR(255)    NULL,
    timezone        VARCHAR(40)     NULL DEFAULT 'Asia/Manila',
    last_login      DATETIME        NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    email_verified  TINYINT(1)      NOT NULL DEFAULT 0,
    deleted_at      DATETIME        NULL,

    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_role    (role_id),
    INDEX idx_active  (is_active),
    INDEX idx_deleted (deleted_at),

    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- Organizational structure
-- -------------------------------------------------------------------------

CREATE TABLE departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL,
    short_code  VARCHAR(20)  NULL UNIQUE,
    description TEXT         NULL,
    manager_id  BIGINT UNSIGNED NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME     NULL                      -- ← added
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE user_departments (
    user_id       BIGINT UNSIGNED NOT NULL,
    department_id INT UNSIGNED    NOT NULL,
    is_primary    TINYINT(1)      NOT NULL DEFAULT 0,
    joined_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, department_id),
    INDEX idx_dept (department_id),

    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- Tickets
-- -------------------------------------------------------------------------

CREATE TABLE tickets (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number   VARCHAR(25)     NOT NULL UNIQUE,
    subject         VARCHAR(200)    NOT NULL,
    description     MEDIUMTEXT      NOT NULL,

    status_id       INT UNSIGNED    NOT NULL,
    priority_id     INT UNSIGNED    NOT NULL,
    type_id         INT UNSIGNED    NULL,
    category_id     INT UNSIGNED    NULL,
    department_id   INT UNSIGNED    NULL,

    created_by      BIGINT UNSIGNED NOT NULL,
    assigned_to     BIGINT UNSIGNED NULL,
    resolver_id     BIGINT UNSIGNED NULL,
    closed_by       BIGINT UNSIGNED NULL,

    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
    first_response_at DATETIME      NULL,
    resolved_at     DATETIME        NULL,
    closed_at       DATETIME        NULL,
    due_at          DATETIME        NULL,
    version         INT UNSIGNED    NOT NULL DEFAULT 1,
    deleted_at      DATETIME        NULL,

    INDEX idx_status      (status_id),
    INDEX idx_priority    (priority_id),
    INDEX idx_assigned    (assigned_to),
    INDEX idx_created_by  (created_by),
    INDEX idx_department  (department_id),
    INDEX idx_resolved_at (resolved_at),
    INDEX idx_due_at      (due_at),
    INDEX idx_deleted     (deleted_at),

    FOREIGN KEY (status_id)     REFERENCES ticket_statuses(id)    ON DELETE RESTRICT,
    FOREIGN KEY (priority_id)   REFERENCES ticket_priorities(id)  ON DELETE RESTRICT,
    FOREIGN KEY (type_id)       REFERENCES ticket_types(id)       ON DELETE SET NULL,
    FOREIGN KEY (category_id)   REFERENCES ticket_categories(id)  ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id)        ON DELETE SET NULL,
    FOREIGN KEY (created_by)    REFERENCES users(id)              ON DELETE RESTRICT,
    FOREIGN KEY (assigned_to)   REFERENCES users(id)              ON DELETE SET NULL,
    FOREIGN KEY (resolver_id)   REFERENCES users(id)              ON DELETE SET NULL,
    FOREIGN KEY (closed_by)     REFERENCES users(id)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- Ticket content & history
-- -------------------------------------------------------------------------

CREATE TABLE ticket_messages (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    is_internal TINYINT(1)      NOT NULL DEFAULT 0,
    body        MEDIUMTEXT      NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME        NULL,                     -- ← added
    INDEX idx_ticket_time (ticket_id, created_at),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)   ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE ticket_attachments (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id     BIGINT UNSIGNED NOT NULL,
    message_id    BIGINT UNSIGNED NULL,
    file_name     VARCHAR(255)    NOT NULL,
    stored_name   VARCHAR(255)    NOT NULL,
    file_path     VARCHAR(500)    NOT NULL,
    file_size     BIGINT UNSIGNED NOT NULL,
    mime_type     VARCHAR(120)    NOT NULL,
    uploaded_by   BIGINT UNSIGNED NOT NULL,
    uploaded_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at    DATETIME        NULL,                     -- ← added
    INDEX idx_ticket (ticket_id),
    FOREIGN KEY (ticket_id)   REFERENCES tickets(id)         ON DELETE CASCADE,
    FOREIGN KEY (message_id)  REFERENCES ticket_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)           ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE ticket_activity_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    action      VARCHAR(80)     NOT NULL,
    old_value   VARCHAR(255)    NULL,
    new_value   VARCHAR(255)    NULL,
    details     JSON            NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME        NULL,                     -- ← added
    INDEX idx_ticket_time (ticket_id, created_at),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- SLA
-- -------------------------------------------------------------------------

CREATE TABLE sla_policies (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    priority_id     INT UNSIGNED NULL,
    department_id   INT UNSIGNED NULL,
    response_time   INT UNSIGNED NOT NULL,
    resolution_time INT UNSIGNED NOT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at      DATETIME     NULL                      -- ← added
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;