-- ============================================================================
--  0006 - Campanas de marketing, notificaciones y mantenimiento del sistema
-- ============================================================================

-- Plantillas editables de correo / SMS / push / WhatsApp.
CREATE TABLE IF NOT EXISTS notification_templates (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key    VARCHAR(60)  NOT NULL COMMENT 'cita_confirmada, recordatorio_24h, bienvenida...',
    channel         ENUM('email','sms','push','whatsapp') NOT NULL DEFAULT 'email',
    name            VARCHAR(140) NOT NULL,
    subject         VARCHAR(200) NOT NULL DEFAULT '',
    body            TEXT         NOT NULL,
    available_vars  VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Variables admitidas, ej. {cliente},{fecha}',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_template (template_key, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campanas de publicidad dirigidas a los clientes registrados.
CREATE TABLE IF NOT EXISTS campaigns (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(160) NOT NULL,
    channel             ENUM('email','sms','push','whatsapp') NOT NULL DEFAULT 'email',
    subject             VARCHAR(200) NOT NULL DEFAULT '',
    body                TEXT         NOT NULL,
    image_path          VARCHAR(255) NOT NULL DEFAULT '',
    cta_label           VARCHAR(80)  NOT NULL DEFAULT '',
    cta_url             VARCHAR(500) NOT NULL DEFAULT '',

    -- Segmentacion del publico objetivo
    audience            ENUM('all','new_clients','inactive_clients','frequent_clients','birthday','custom')
                        NOT NULL DEFAULT 'all',
    audience_filter     TEXT         NULL COMMENT 'JSON con filtros adicionales',
    inactive_days       SMALLINT UNSIGNED NOT NULL DEFAULT 60,

    status              ENUM('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
    scheduled_at        DATETIME     NULL DEFAULT NULL,
    started_at          DATETIME     NULL DEFAULT NULL,
    finished_at         DATETIME     NULL DEFAULT NULL,

    total_recipients    INT UNSIGNED NOT NULL DEFAULT 0,
    total_sent          INT UNSIGNED NOT NULL DEFAULT 0,
    total_failed        INT UNSIGNED NOT NULL DEFAULT 0,
    total_opened        INT UNSIGNED NOT NULL DEFAULT 0,
    total_clicked       INT UNSIGNED NOT NULL DEFAULT 0,

    created_by          BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at          DATETIME     NOT NULL,
    updated_at          DATETIME     NOT NULL,
    deleted_at          DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_campaign_status (status, scheduled_at),
    CONSTRAINT fk_campaign_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_recipients (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id     INT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL DEFAULT NULL,
    destination     VARCHAR(190) NOT NULL COMMENT 'Correo, telefono o token de dispositivo',
    status          ENUM('pending','sent','failed','opened','clicked','unsubscribed') NOT NULL DEFAULT 'pending',
    error_message   VARCHAR(255) NOT NULL DEFAULT '',
    tracking_token  CHAR(32)     NOT NULL DEFAULT '',
    sent_at         DATETIME     NULL DEFAULT NULL,
    opened_at       DATETIME     NULL DEFAULT NULL,
    clicked_at      DATETIME     NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_recipient_campaign (campaign_id, status),
    KEY idx_recipient_user (user_id),
    KEY idx_recipient_token (tracking_token),
    CONSTRAINT fk_recipient_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
    CONSTRAINT fk_recipient_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cola de envios: correos transaccionales, recordatorios y campanas.
CREATE TABLE IF NOT EXISTS notification_queue (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel         ENUM('email','sms','push','whatsapp') NOT NULL DEFAULT 'email',
    destination     VARCHAR(190) NOT NULL,
    user_id         BIGINT UNSIGNED NULL DEFAULT NULL,
    subject         VARCHAR(200) NOT NULL DEFAULT '',
    body            TEXT         NOT NULL,
    payload_json    TEXT         NULL COMMENT 'Datos extra para push',
    template_key    VARCHAR(60)  NOT NULL DEFAULT '',
    related_type    VARCHAR(40)  NOT NULL DEFAULT '',
    related_id      BIGINT UNSIGNED NULL DEFAULT NULL,
    status          ENUM('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    last_error      VARCHAR(500) NOT NULL DEFAULT '',
    scheduled_at    DATETIME     NOT NULL,
    sent_at         DATETIME     NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_queue_pending (status, scheduled_at),
    KEY idx_queue_related (related_type, related_id),
    CONSTRAINT fk_queue_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispositivos moviles registrados para notificaciones push.
CREATE TABLE IF NOT EXISTS push_devices (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    token           VARCHAR(255) NOT NULL,
    platform        ENUM('android','ios','web') NOT NULL DEFAULT 'android',
    device_name     VARCHAR(120) NOT NULL DEFAULT '',
    app_version     VARCHAR(20)  NOT NULL DEFAULT '',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    last_seen_at    DATETIME     NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_token (token),
    KEY idx_push_user (user_id, is_active),
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suscriptores del boletin que aun no son clientes registrados.
CREATE TABLE IF NOT EXISTS subscribers (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email               VARCHAR(190) NOT NULL,
    name                VARCHAR(120) NOT NULL DEFAULT '',
    phone               VARCHAR(20)  NOT NULL DEFAULT '',
    source              VARCHAR(40)  NOT NULL DEFAULT 'web',
    is_confirmed        TINYINT(1)   NOT NULL DEFAULT 0,
    confirmed_at        DATETIME     NULL DEFAULT NULL,
    unsubscribed_at     DATETIME     NULL DEFAULT NULL,
    unsubscribe_token   CHAR(32)     NOT NULL,
    consent_ip          VARCHAR(45)  NOT NULL DEFAULT '',
    created_at          DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscriber_email (email),
    UNIQUE KEY uq_subscriber_token (unsubscribe_token),
    KEY idx_subscriber_active (is_confirmed, unsubscribed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mensajes recibidos por el formulario de contacto de la web.
CREATE TABLE IF NOT EXISTS contact_messages (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL DEFAULT '',
    phone           VARCHAR(20)  NOT NULL DEFAULT '',
    subject         VARCHAR(200) NOT NULL DEFAULT '',
    message         TEXT         NOT NULL,
    ip_address      VARCHAR(45)  NOT NULL DEFAULT '',
    is_read         TINYINT(1)   NOT NULL DEFAULT 0,
    replied_at      DATETIME     NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    deleted_at      DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_contact_unread (is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  MANTENIMIENTO Y RETENCION DE DATOS
-- ---------------------------------------------------------------------------
--  Politicas configurables: cuanto tiempo se conserva cada tipo de dato antes
--  de eliminarlo definitivamente para liberar espacio.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS retention_policies (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    policy_key          VARCHAR(60)  NOT NULL,
    label               VARCHAR(160) NOT NULL,
    description         VARCHAR(500) NOT NULL DEFAULT '',
    target_table        VARCHAR(64)  NOT NULL,
    date_column         VARCHAR(64)  NOT NULL DEFAULT 'created_at',
    retention_days      INT UNSIGNED NOT NULL DEFAULT 365,
    condition_sql       VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Filtro adicional fijado por el sistema',
    deletes_files       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Borra tambien los archivos asociados',
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    last_run_at         DATETIME     NULL DEFAULT NULL,
    last_deleted_count  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at          DATETIME     NOT NULL,
    updated_at          DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_retention_key (policy_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de las tareas de mantenimiento ejecutadas.
CREATE TABLE IF NOT EXISTS maintenance_runs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task            VARCHAR(60)  NOT NULL,
    rows_affected   INT UNSIGNED NOT NULL DEFAULT 0,
    files_removed   INT UNSIGNED NOT NULL DEFAULT 0,
    bytes_freed     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms     INT UNSIGNED NOT NULL DEFAULT 0,
    detail          TEXT         NULL,
    triggered_by    BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = tarea programada',
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_maint_task (task, created_at),
    CONSTRAINT fk_maint_user FOREIGN KEY (triggered_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Metricas diarias precalculadas para que el panel cargue rapido.
CREATE TABLE IF NOT EXISTS daily_stats (
    stat_date           DATE         NOT NULL,
    branch_id           INT UNSIGNED NOT NULL DEFAULT 0,
    appointments_total  INT UNSIGNED NOT NULL DEFAULT 0,
    appointments_done   INT UNSIGNED NOT NULL DEFAULT 0,
    appointments_cancel INT UNSIGNED NOT NULL DEFAULT 0,
    appointments_noshow INT UNSIGNED NOT NULL DEFAULT 0,
    new_clients         INT UNSIGNED NOT NULL DEFAULT 0,
    revenue             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    updated_at          DATETIME     NOT NULL,
    PRIMARY KEY (stat_date, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
