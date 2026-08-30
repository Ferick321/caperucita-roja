-- ============================================================================
--  0002 - Negocio, sucursales, catalogo de servicios y personal
-- ============================================================================
--  Todo el catalogo es editable desde el panel: categorias, servicios,
--  duraciones, precios, personal, horarios y ausencias.
-- ============================================================================

CREATE TABLE IF NOT EXISTS branches (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(120) NOT NULL,
    slug            VARCHAR(140) NOT NULL,
    address         VARCHAR(255) NOT NULL DEFAULT '',
    city            VARCHAR(100) NOT NULL DEFAULT '',
    phone           VARCHAR(30)  NOT NULL DEFAULT '',
    whatsapp        VARCHAR(30)  NOT NULL DEFAULT '',
    email           VARCHAR(190) NOT NULL DEFAULT '',
    latitude        DECIMAL(10,7) NULL DEFAULT NULL,
    longitude       DECIMAL(10,7) NULL DEFAULT NULL,
    maps_url        VARCHAR(500) NOT NULL DEFAULT '',
    photo_path      VARCHAR(255) NOT NULL DEFAULT '',
    timezone        VARCHAR(64)  NOT NULL DEFAULT 'America/Guayaquil',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    is_default      TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    deleted_at      DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_branches_slug (slug),
    KEY idx_branches_active (is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horario semanal de cada sucursal (0 = domingo ... 6 = sabado).
CREATE TABLE IF NOT EXISTS branch_hours (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id       INT UNSIGNED NOT NULL,
    weekday         TINYINT UNSIGNED NOT NULL,
    opens_at        TIME         NOT NULL DEFAULT '09:00:00',
    closes_at       TIME         NOT NULL DEFAULT '19:00:00',
    break_start     TIME         NULL DEFAULT NULL,
    break_end       TIME         NULL DEFAULT NULL,
    is_closed       TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_branch_weekday (branch_id, weekday),
    CONSTRAINT fk_hours_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feriados y cierres puntuales.
CREATE TABLE IF NOT EXISTS branch_closures (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id       INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = aplica a todas las sucursales',
    starts_on       DATE         NOT NULL,
    ends_on         DATE         NOT NULL,
    reason          VARCHAR(160) NOT NULL DEFAULT '',
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_closure_range (starts_on, ends_on),
    CONSTRAINT fk_closure_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias del catalogo: barberia, peluqueria, manicure, pedicure, estetica,
-- o cualquier otra que el administrador cree desde el panel.
CREATE TABLE IF NOT EXISTS service_categories (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(120) NOT NULL,
    description     VARCHAR(500) NOT NULL DEFAULT '',
    icon            VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'Nombre del icono en la interfaz',
    color           VARCHAR(7)   NOT NULL DEFAULT '#8b5cf6',
    image_path      VARCHAR(255) NOT NULL DEFAULT '',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    show_on_home    TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    deleted_at      DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id           INT UNSIGNED NOT NULL,
    name                  VARCHAR(140) NOT NULL,
    slug                  VARCHAR(160) NOT NULL,
    short_description     VARCHAR(255) NOT NULL DEFAULT '',
    description           TEXT         NULL,
    image_path            VARCHAR(255) NOT NULL DEFAULT '',
    duration_minutes      SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    buffer_before_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Preparacion previa',
    buffer_after_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Limpieza posterior',
    price                 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    promo_price           DECIMAL(10,2) NULL DEFAULT NULL,
    promo_starts_at       DATETIME     NULL DEFAULT NULL,
    promo_ends_at         DATETIME     NULL DEFAULT NULL,
    deposit_required      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Exige abono para reservar',
    deposit_amount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deposit_is_percentage TINYINT(1)   NOT NULL DEFAULT 0,
    requires_consultation TINYINT(1)   NOT NULL DEFAULT 0,
    max_per_day           SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = sin limite',
    loyalty_points        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active             TINYINT(1)   NOT NULL DEFAULT 1,
    is_featured           TINYINT(1)   NOT NULL DEFAULT 0,
    bookable_online       TINYINT(1)   NOT NULL DEFAULT 1,
    gender_target         ENUM('all','male','female','kids') NOT NULL DEFAULT 'all',
    sort_order            SMALLINT     NOT NULL DEFAULT 0,
    created_at            DATETIME     NOT NULL,
    updated_at            DATETIME     NOT NULL,
    deleted_at            DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_slug (slug),
    KEY idx_services_category (category_id, is_active),
    KEY idx_services_featured (is_featured, is_active),
    KEY idx_services_deleted (deleted_at),
    CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ficha profesional del barbero / peluquero / estilista / manicurista.
CREATE TABLE IF NOT EXISTS staff (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Cuenta de acceso, si la tiene',
    branch_id           INT UNSIGNED NOT NULL,
    display_name        VARCHAR(120) NOT NULL,
    slug                VARCHAR(140) NOT NULL,
    title               VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Barbero, Estilista, Manicurista...',
    bio                 TEXT         NULL,
    photo_path          VARCHAR(255) NOT NULL DEFAULT '',
    phone               VARCHAR(20)  NOT NULL DEFAULT '',
    email               VARCHAR(190) NOT NULL DEFAULT '',
    instagram           VARCHAR(120) NOT NULL DEFAULT '',
    color               VARCHAR(7)   NOT NULL DEFAULT '#0ea5e9' COMMENT 'Color en la agenda',
    commission_percent  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    accepts_online      TINYINT(1)   NOT NULL DEFAULT 1,
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    show_on_web         TINYINT(1)   NOT NULL DEFAULT 1,
    rating_average      DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    rating_count        INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order          SMALLINT     NOT NULL DEFAULT 0,
    created_at          DATETIME     NOT NULL,
    updated_at          DATETIME     NOT NULL,
    deleted_at          DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_slug (slug),
    KEY idx_staff_branch (branch_id, is_active),
    KEY idx_staff_user (user_id),
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_staff_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Que servicios presta cada profesional (y con que precio/duracion propios).
CREATE TABLE IF NOT EXISTS staff_services (
    staff_id            INT UNSIGNED NOT NULL,
    service_id          INT UNSIGNED NOT NULL,
    custom_price        DECIMAL(10,2) NULL DEFAULT NULL,
    custom_duration     SMALLINT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (staff_id, service_id),
    KEY idx_ss_service (service_id),
    CONSTRAINT fk_ss_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
    CONSTRAINT fk_ss_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jornada semanal de cada profesional.
CREATE TABLE IF NOT EXISTS staff_schedules (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_id        INT UNSIGNED NOT NULL,
    weekday         TINYINT UNSIGNED NOT NULL COMMENT '0=domingo ... 6=sabado',
    starts_at       TIME         NOT NULL,
    ends_at         TIME         NOT NULL,
    break_start     TIME         NULL DEFAULT NULL,
    break_end       TIME         NULL DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    valid_from      DATE         NULL DEFAULT NULL,
    valid_until     DATE         NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_schedule_staff_day (staff_id, weekday, is_active),
    CONSTRAINT fk_schedule_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vacaciones, permisos y bloqueos puntuales de agenda.
CREATE TABLE IF NOT EXISTS staff_time_off (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_id        INT UNSIGNED NOT NULL,
    starts_at       DATETIME     NOT NULL COMMENT 'UTC',
    ends_at         DATETIME     NOT NULL COMMENT 'UTC',
    reason          VARCHAR(160) NOT NULL DEFAULT '',
    is_full_day     TINYINT(1)   NOT NULL DEFAULT 0,
    created_by      BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_timeoff_staff_range (staff_id, starts_at, ends_at),
    CONSTRAINT fk_timeoff_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
    CONSTRAINT fk_timeoff_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estaciones/sillas: limitan cuantas citas simultaneas admite el local.
CREATE TABLE IF NOT EXISTS resources (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id       INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    type            VARCHAR(40)  NOT NULL DEFAULT 'estacion',
    capacity        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_resources_branch (branch_id, is_active),
    CONSTRAINT fk_resources_branch FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
