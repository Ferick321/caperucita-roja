-- ============================================================================
--  0005 - Configuracion, contenido web y publicidad
-- ============================================================================
--  Esta migracion es el corazon del "sin tocar codigo": ajustes, secciones de
--  la web, banners, pantallas de publicidad y campanas de marketing viven
--  aqui y se editan desde el panel.
-- ============================================================================

-- Ajustes clave/valor con tipo, para que el panel pinte el control adecuado.
CREATE TABLE IF NOT EXISTS settings (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_name      VARCHAR(40)  NOT NULL COMMENT 'business, theme, booking, seo, app, system...',
    setting_key     VARCHAR(120) NOT NULL COMMENT 'grupo.clave, ej. business.name',
    setting_value   TEXT         NULL,
    value_type      ENUM('string','text','html','int','float','bool','json','color','image','file','url','email','time','select')
                    NOT NULL DEFAULT 'string',
    label           VARCHAR(160) NOT NULL DEFAULT '',
    help_text       VARCHAR(500) NOT NULL DEFAULT '',
    options_json    TEXT         NULL COMMENT 'Opciones para el tipo select',
    is_public       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Se expone a la web y a la app movil',
    is_encrypted    TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    updated_by      BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key),
    KEY idx_settings_group (group_name, sort_order),
    KEY idx_settings_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Biblioteca de medios: centraliza las imagenes para poder limpiar huerfanas.
CREATE TABLE IF NOT EXISTS media (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_path       VARCHAR(255) NOT NULL,
    file_mime       VARCHAR(60)  NOT NULL DEFAULT '',
    file_size       INT UNSIGNED NOT NULL DEFAULT 0,
    file_hash       CHAR(64)     NOT NULL DEFAULT '',
    width           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    height          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    original_name   VARCHAR(160) NOT NULL DEFAULT '',
    alt_text        VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Accesibilidad y SEO',
    caption         VARCHAR(255) NOT NULL DEFAULT '',
    folder          VARCHAR(60)  NOT NULL DEFAULT 'general',
    uploaded_by     BIGINT UNSIGNED NULL DEFAULT NULL,
    usage_count     INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    deleted_at      DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_media_folder (folder, created_at),
    KEY idx_media_hash (file_hash),
    KEY idx_media_deleted (deleted_at),
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Secciones de la pagina publica: se activan, ordenan y editan sin programar.
CREATE TABLE IF NOT EXISTS content_blocks (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    block_key       VARCHAR(60)  NOT NULL COMMENT 'hero, sobre_nosotros, servicios, equipo...',
    section_type    VARCHAR(40)  NOT NULL DEFAULT 'generic',
    title           VARCHAR(200) NOT NULL DEFAULT '',
    subtitle        VARCHAR(300) NOT NULL DEFAULT '',
    body            TEXT         NULL,
    image_path      VARCHAR(255) NOT NULL DEFAULT '',
    background_path VARCHAR(255) NOT NULL DEFAULT '',
    cta_label       VARCHAR(80)  NOT NULL DEFAULT '',
    cta_url         VARCHAR(500) NOT NULL DEFAULT '',
    cta_secondary_label VARCHAR(80) NOT NULL DEFAULT '',
    cta_secondary_url   VARCHAR(500) NOT NULL DEFAULT '',
    extra_json      TEXT         NULL COMMENT 'Campos adicionales segun el tipo de seccion',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blocks_key (block_key),
    KEY idx_blocks_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Galeria de trabajos (antes/despues, cortes, unias...).
CREATE TABLE IF NOT EXISTS gallery_items (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title           VARCHAR(160) NOT NULL DEFAULT '',
    description     VARCHAR(500) NOT NULL DEFAULT '',
    image_path      VARCHAR(255) NOT NULL,
    before_path     VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Foto "antes" opcional',
    category_id     INT UNSIGNED NULL DEFAULT NULL,
    staff_id        INT UNSIGNED NULL DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    is_featured     TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    deleted_at      DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_gallery_active (is_active, sort_order),
    CONSTRAINT fk_gallery_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_gallery_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question        VARCHAR(300) NOT NULL,
    answer          TEXT         NOT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_faqs_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  PUBLICIDAD
-- ---------------------------------------------------------------------------
--  Un banner define QUE se muestra; su ubicacion define DONDE y CUANDO.
--  Ubicaciones: cabecera web, franja lateral, ventana al iniciar sesion,
--  ventana durante la navegacion, aviso al intentar salir, pantalla de
--  bienvenida de la app y tarjeta dentro del inicio de la app.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS banners (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(140) NOT NULL COMMENT 'Nombre interno para identificarlo',
    title               VARCHAR(200) NOT NULL DEFAULT '',
    subtitle            VARCHAR(300) NOT NULL DEFAULT '',
    body                TEXT         NULL,
    image_path          VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Imagen para escritorio',
    mobile_image_path   VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Imagen para movil',
    video_url           VARCHAR(500) NOT NULL DEFAULT '',
    cta_label           VARCHAR(80)  NOT NULL DEFAULT '',
    cta_url             VARCHAR(500) NOT NULL DEFAULT '',
    background_color    VARCHAR(7)   NOT NULL DEFAULT '#111827',
    text_color          VARCHAR(7)   NOT NULL DEFAULT '#ffffff',

    -- Programacion
    starts_at           DATETIME     NULL DEFAULT NULL,
    ends_at             DATETIME     NULL DEFAULT NULL,
    weekdays            VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'Ej. 1,2,3,4,5 (vacio = todos)',
    daily_from          TIME         NULL DEFAULT NULL,
    daily_to            TIME         NULL DEFAULT NULL,

    -- Segmentacion
    audience            ENUM('all','guests','clients','new_clients','inactive_clients') NOT NULL DEFAULT 'all',
    device_target       ENUM('all','desktop','mobile','app') NOT NULL DEFAULT 'all',

    -- Control de frecuencia: evita hartar al visitante
    max_views_per_user  SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = sin limite',
    cooldown_hours      SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    delay_seconds       SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Retraso antes de aparecer',
    auto_close_seconds  SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = no se cierra solo',
    is_dismissible      TINYINT(1)   NOT NULL DEFAULT 1,

    priority            SMALLINT     NOT NULL DEFAULT 0 COMMENT 'Mayor valor gana',
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    impressions         INT UNSIGNED NOT NULL DEFAULT 0,
    clicks              INT UNSIGNED NOT NULL DEFAULT 0,
    dismissals          INT UNSIGNED NOT NULL DEFAULT 0,
    created_by          BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at          DATETIME     NOT NULL,
    updated_at          DATETIME     NOT NULL,
    deleted_at          DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_banners_active (is_active, starts_at, ends_at),
    KEY idx_banners_deleted (deleted_at),
    CONSTRAINT fk_banner_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banner_placements (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    banner_id       INT UNSIGNED NOT NULL,
    placement       VARCHAR(40)  NOT NULL COMMENT 'web_hero, web_strip, web_sidebar, on_login, while_browsing, on_exit, app_splash, app_home_card, app_interstitial',
    page_pattern    VARCHAR(120) NOT NULL DEFAULT '*' COMMENT 'Rutas donde aplica, ej. /servicios*',
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_placement (banner_id, placement, page_pattern),
    KEY idx_placement_lookup (placement, sort_order),
    CONSTRAINT fk_placement_banner FOREIGN KEY (banner_id) REFERENCES banners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de impresiones/clics: alimenta el informe y el control de frecuencia.
CREATE TABLE IF NOT EXISTS banner_events (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    banner_id       INT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL DEFAULT NULL,
    visitor_key     CHAR(64)     NOT NULL DEFAULT '' COMMENT 'Identificador anonimo del visitante',
    event_type      ENUM('impression','click','dismiss') NOT NULL DEFAULT 'impression',
    placement       VARCHAR(40)  NOT NULL DEFAULT '',
    device          VARCHAR(20)  NOT NULL DEFAULT '',
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_bev_banner_time (banner_id, created_at),
    KEY idx_bev_visitor (visitor_key, banner_id, created_at),
    KEY idx_bev_user (user_id, banner_id),
    CONSTRAINT fk_bev_banner FOREIGN KEY (banner_id) REFERENCES banners (id) ON DELETE CASCADE,
    CONSTRAINT fk_bev_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
