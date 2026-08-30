-- ============================================================================
--  0003 - Agendamiento de citas
-- ============================================================================
--  Una cita puede incluir varios servicios (corte + barba + manicure) y
--  siempre queda registrada la fecha en UTC junto al desglose de precios.
-- ============================================================================

CREATE TABLE IF NOT EXISTS appointments (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                  VARCHAR(20)  NOT NULL COMMENT 'Codigo corto para el cliente, ej. CT-8H3K2',
    branch_id             INT UNSIGNED NOT NULL,
    client_id             BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL en reservas de invitado',
    staff_id              INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = sin preferencia',
    resource_id           INT UNSIGNED NULL DEFAULT NULL,

    -- Datos de contacto (se copian para conservar el historico aunque el
    -- cliente cambie sus datos o elimine su cuenta).
    client_name           VARCHAR(160) NOT NULL,
    client_phone          VARCHAR(20)  NOT NULL DEFAULT '',
    client_email          VARCHAR(190) NOT NULL DEFAULT '',

    starts_at             DATETIME     NOT NULL COMMENT 'UTC',
    ends_at               DATETIME     NOT NULL COMMENT 'UTC',
    duration_minutes      SMALLINT UNSIGNED NOT NULL DEFAULT 30,

    status                ENUM('pending','confirmed','in_progress','completed','cancelled','no_show')
                          NOT NULL DEFAULT 'pending',

    -- Importes
    subtotal              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total                 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid_amount           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency              VARCHAR(3)   NOT NULL DEFAULT 'USD',

    coupon_id             INT UNSIGNED NULL DEFAULT NULL,
    payment_status        ENUM('unpaid','deposit_paid','awaiting_verification','paid','refunded')
                          NOT NULL DEFAULT 'unpaid',

    -- El cliente describe lo que necesita cuando no encaja en el catalogo.
    client_notes          TEXT         NULL COMMENT 'Peticion libre del cliente',
    custom_request        VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Opcion "Otro: especifique"',
    internal_notes        TEXT         NULL COMMENT 'Solo visible para el personal',

    source                ENUM('web','app','panel','phone','walk_in') NOT NULL DEFAULT 'web',
    reminder_sent_at      DATETIME     NULL DEFAULT NULL,
    followup_sent_at      DATETIME     NULL DEFAULT NULL,
    review_request_sent_at DATETIME    NULL DEFAULT NULL,

    confirmed_at          DATETIME     NULL DEFAULT NULL,
    started_at            DATETIME     NULL DEFAULT NULL,
    completed_at          DATETIME     NULL DEFAULT NULL,
    cancelled_at          DATETIME     NULL DEFAULT NULL,
    cancelled_by          BIGINT UNSIGNED NULL DEFAULT NULL,
    cancellation_reason   VARCHAR(255) NOT NULL DEFAULT '',

    created_at            DATETIME     NOT NULL,
    updated_at            DATETIME     NOT NULL,
    deleted_at            DATETIME     NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_appointments_code (code),
    KEY idx_appt_staff_time (staff_id, starts_at, ends_at),
    KEY idx_appt_branch_time (branch_id, starts_at),
    KEY idx_appt_client (client_id, starts_at),
    KEY idx_appt_status (status, starts_at),
    KEY idx_appt_payment (payment_status),
    KEY idx_appt_deleted (deleted_at),
    KEY idx_appt_phone (client_phone),
    CONSTRAINT fk_appt_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE RESTRICT,
    CONSTRAINT fk_appt_client FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_appt_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL,
    CONSTRAINT fk_appt_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE SET NULL,
    CONSTRAINT fk_appt_canceller FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Servicios incluidos en la cita, con los datos congelados al momento de reservar.
CREATE TABLE IF NOT EXISTS appointment_services (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id    BIGINT UNSIGNED NOT NULL,
    service_id        INT UNSIGNED NULL DEFAULT NULL,
    service_name      VARCHAR(140) NOT NULL COMMENT 'Copia historica del nombre',
    duration_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    price             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    sort_order        SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_as_appointment (appointment_id),
    KEY idx_as_service (service_id),
    CONSTRAINT fk_as_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE,
    CONSTRAINT fk_as_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trazabilidad de cambios de estado de la cita.
CREATE TABLE IF NOT EXISTS appointment_status_history (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id  BIGINT UNSIGNED NOT NULL,
    from_status     VARCHAR(20)  NOT NULL DEFAULT '',
    to_status       VARCHAR(20)  NOT NULL,
    changed_by      BIGINT UNSIGNED NULL DEFAULT NULL,
    note            VARCHAR(255) NOT NULL DEFAULT '',
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ash_appointment (appointment_id, created_at),
    CONSTRAINT fk_ash_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE,
    CONSTRAINT fk_ash_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lista de espera cuando no hay hueco disponible.
CREATE TABLE IF NOT EXISTS waitlist (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id       INT UNSIGNED NOT NULL,
    client_id       BIGINT UNSIGNED NULL DEFAULT NULL,
    staff_id        INT UNSIGNED NULL DEFAULT NULL,
    service_id      INT UNSIGNED NULL DEFAULT NULL,
    client_name     VARCHAR(160) NOT NULL,
    client_phone    VARCHAR(20)  NOT NULL DEFAULT '',
    desired_date    DATE         NOT NULL,
    desired_from    TIME         NULL DEFAULT NULL,
    desired_to      TIME         NULL DEFAULT NULL,
    status          ENUM('waiting','notified','converted','expired') NOT NULL DEFAULT 'waiting',
    notified_at     DATETIME     NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_waitlist_date (desired_date, status),
    KEY idx_waitlist_client (client_id),
    CONSTRAINT fk_wl_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE,
    CONSTRAINT fk_wl_client FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_wl_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL,
    CONSTRAINT fk_wl_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resenas: solo se piden a clientes con cita completada.
CREATE TABLE IF NOT EXISTS reviews (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id  BIGINT UNSIGNED NULL DEFAULT NULL,
    client_id       BIGINT UNSIGNED NULL DEFAULT NULL,
    staff_id        INT UNSIGNED NULL DEFAULT NULL,
    author_name     VARCHAR(120) NOT NULL,
    rating          TINYINT UNSIGNED NOT NULL DEFAULT 5,
    comment         TEXT         NULL,
    reply           TEXT         NULL COMMENT 'Respuesta publica del negocio',
    replied_at      DATETIME     NULL DEFAULT NULL,
    is_approved     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Se publica solo tras moderacion',
    is_featured     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    deleted_at      DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_appointment (appointment_id),
    KEY idx_reviews_approved (is_approved, created_at),
    KEY idx_reviews_staff (staff_id, is_approved),
    CONSTRAINT fk_review_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE SET NULL,
    CONSTRAINT fk_review_client FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_review_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
