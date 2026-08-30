-- ============================================================================
--  0001 - Identidad, roles, permisos y seguridad
-- ============================================================================
--  Todas las fechas se guardan en UTC. La zona horaria del negocio es un
--  ajuste editable y solo se aplica al mostrar/interpretar datos.
-- ============================================================================

CREATE TABLE IF NOT EXISTS roles (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(40)  NOT NULL,
    name            VARCHAR(80)  NOT NULL,
    description     VARCHAR(255) NOT NULL DEFAULT '',
    is_system       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Los roles de sistema no se pueden eliminar',
    priority        SMALLINT     NOT NULL DEFAULT 100,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(80)  NOT NULL COMMENT 'modulo.accion, admite comodin modulo.*',
    module          VARCHAR(40)  NOT NULL,
    name            VARCHAR(120) NOT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id         INT UNSIGNED NOT NULL,
    permission_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid                 CHAR(36)        NOT NULL,
    role                 VARCHAR(40)     NOT NULL DEFAULT 'client',
    first_name           VARCHAR(80)     NOT NULL,
    last_name            VARCHAR(80)     NOT NULL DEFAULT '',
    email                VARCHAR(190)    NOT NULL,
    email_verified_at    DATETIME        NULL DEFAULT NULL,
    phone                VARCHAR(20)     NOT NULL DEFAULT '',
    phone_verified_at    DATETIME        NULL DEFAULT NULL,
    password_hash        VARCHAR(255)    NOT NULL DEFAULT '',
    password_changed_at  DATETIME        NULL DEFAULT NULL,
    avatar_path          VARCHAR(255)    NOT NULL DEFAULT '',
    birth_date           DATE            NULL DEFAULT NULL COMMENT 'Permite campanas de cumpleanos',
    gender               VARCHAR(20)     NOT NULL DEFAULT '',
    notes                TEXT            NULL COMMENT 'Notas internas del personal sobre el cliente',
    status               ENUM('active','pending','blocked') NOT NULL DEFAULT 'active',
    locale               VARCHAR(10)     NOT NULL DEFAULT 'es',

    -- Preferencias de comunicacion (consentimiento explicito por canal)
    accepts_marketing    TINYINT(1)      NOT NULL DEFAULT 0,
    accepts_email        TINYINT(1)      NOT NULL DEFAULT 1,
    accepts_sms          TINYINT(1)      NOT NULL DEFAULT 0,
    accepts_whatsapp     TINYINT(1)      NOT NULL DEFAULT 0,
    accepts_push         TINYINT(1)      NOT NULL DEFAULT 1,
    marketing_consent_at DATETIME        NULL DEFAULT NULL,
    marketing_consent_ip VARCHAR(45)     NOT NULL DEFAULT '',

    -- Seguridad
    two_factor_enabled   TINYINT(1)      NOT NULL DEFAULT 0,
    two_factor_secret    VARCHAR(255)    NOT NULL DEFAULT '' COMMENT 'Cifrado con la clave de la aplicacion',
    two_factor_recovery  TEXT            NULL COMMENT 'Codigos de respaldo cifrados',
    failed_logins        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until         DATETIME        NULL DEFAULT NULL,
    last_login_at        DATETIME        NULL DEFAULT NULL,
    last_login_ip        VARCHAR(45)     NOT NULL DEFAULT '',
    tokens_valid_after   DATETIME        NULL DEFAULT NULL COMMENT 'Invalida tokens emitidos antes de esta fecha',

    -- Fidelidad y metricas del cliente
    loyalty_points       INT             NOT NULL DEFAULT 0,
    total_visits         INT UNSIGNED    NOT NULL DEFAULT 0,
    total_spent          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    last_visit_at        DATETIME        NULL DEFAULT NULL,
    referral_code        VARCHAR(20)     NOT NULL DEFAULT '',
    referred_by_id       BIGINT UNSIGNED NULL DEFAULT NULL,

    source               VARCHAR(40)     NOT NULL DEFAULT 'web' COMMENT 'web, app, panel, importacion',
    created_at           DATETIME        NOT NULL,
    updated_at           DATETIME        NOT NULL,
    deleted_at           DATETIME        NULL DEFAULT NULL,
    anonymized_at        DATETIME        NULL DEFAULT NULL COMMENT 'Marca de derecho al olvido aplicado',

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_uuid (uuid),
    KEY idx_users_role_status (role, status),
    KEY idx_users_phone (phone),
    KEY idx_users_deleted (deleted_at),
    KEY idx_users_marketing (accepts_marketing, status),
    KEY idx_users_referral (referral_code),
    CONSTRAINT fk_users_referrer FOREIGN KEY (referred_by_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(190) NOT NULL,
    ip_address      VARCHAR(45)  NOT NULL,
    user_agent      VARCHAR(255) NOT NULL DEFAULT '',
    successful      TINYINT(1)   NOT NULL DEFAULT 0,
    failure_reason  VARCHAR(60)  NOT NULL DEFAULT '',
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_login_email_time (email, created_at),
    KEY idx_login_ip_time (ip_address, created_at),
    KEY idx_login_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    token_hash      CHAR(64)     NOT NULL COMMENT 'SHA-256 del token; el original solo viaja por correo',
    ip_address      VARCHAR(45)  NOT NULL DEFAULT '',
    expires_at      DATETIME     NOT NULL,
    used_at         DATETIME     NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_token (token_hash),
    KEY idx_reset_user (user_id),
    KEY idx_reset_expires (expires_at),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    token_hash      CHAR(64)     NOT NULL,
    channel         ENUM('email','sms') NOT NULL DEFAULT 'email',
    expires_at      DATETIME     NOT NULL,
    used_at         DATETIME     NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_verif_token (token_hash),
    KEY idx_verif_user (user_id),
    CONSTRAINT fk_verif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens de refresco de la app movil: rotativos y ligados al dispositivo.
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    token_hash      CHAR(64)     NOT NULL,
    device_id       VARCHAR(80)  NOT NULL DEFAULT '',
    device_name     VARCHAR(120) NOT NULL DEFAULT '',
    platform        VARCHAR(20)  NOT NULL DEFAULT '',
    app_version     VARCHAR(20)  NOT NULL DEFAULT '',
    ip_address      VARCHAR(45)  NOT NULL DEFAULT '',
    parent_id       BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Cadena de rotacion, detecta reutilizacion',
    revoked_at      DATETIME     NULL DEFAULT NULL,
    expires_at      DATETIME     NOT NULL,
    last_used_at    DATETIME     NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_refresh_token (token_hash),
    KEY idx_refresh_user (user_id, revoked_at),
    KEY idx_refresh_expires (expires_at),
    CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket_key      CHAR(64)     NOT NULL,
    attempts        INT UNSIGNED NOT NULL DEFAULT 0,
    window_start    DATETIME     NOT NULL,
    expires_at      DATETIME     NOT NULL,
    PRIMARY KEY (bucket_key),
    KEY idx_rate_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NULL DEFAULT NULL,
    action          VARCHAR(100) NOT NULL,
    entity_type     VARCHAR(80)  NOT NULL DEFAULT '',
    entity_id       BIGINT UNSIGNED NULL DEFAULT NULL,
    changes_before  TEXT         NULL,
    changes_after   TEXT         NULL,
    ip_address      VARCHAR(45)  NOT NULL DEFAULT '',
    user_agent      VARCHAR(255) NOT NULL DEFAULT '',
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
