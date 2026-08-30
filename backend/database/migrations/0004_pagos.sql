-- ============================================================================
--  0004 - Pagos, cuentas bancarias y comprobantes
-- ============================================================================
--  El cliente elige efectivo o transferencia. Si elige transferencia, la app
--  y la web le muestran los datos bancarios (editables desde el panel) y le
--  permiten subir o fotografiar el comprobante para su verificacion.
-- ============================================================================

-- Metodos de pago habilitados, totalmente configurables desde el panel.
CREATE TABLE IF NOT EXISTS payment_methods (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                  VARCHAR(40)  NOT NULL COMMENT 'efectivo, transferencia, tarjeta, ...',
    name                  VARCHAR(100) NOT NULL,
    description           VARCHAR(500) NOT NULL DEFAULT '',
    instructions          TEXT         NULL COMMENT 'Texto que ve el cliente al elegirlo',
    icon                  VARCHAR(60)  NOT NULL DEFAULT '',
    requires_proof        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Exige subir comprobante',
    shows_bank_accounts   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Muestra los datos para transferir',
    requires_verification TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'El personal debe aprobarlo',
    is_online             TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Disponible al reservar por web/app',
    is_active             TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order            SMALLINT     NOT NULL DEFAULT 0,
    created_at            DATETIME     NOT NULL,
    updated_at            DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_methods_code (code),
    KEY idx_pm_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuentas donde el cliente debe transferir. Los campos sensibles se guardan
-- cifrados con la clave de la aplicacion (columna *_enc).
CREATE TABLE IF NOT EXISTS bank_accounts (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bank_name           VARCHAR(120) NOT NULL,
    account_type        VARCHAR(60)  NOT NULL DEFAULT 'Ahorros',
    account_number_enc  VARCHAR(512) NOT NULL COMMENT 'Cifrado',
    account_last4       VARCHAR(8)   NOT NULL DEFAULT '' COMMENT 'Ultimos digitos para listados',
    holder_name         VARCHAR(160) NOT NULL,
    holder_document     VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'Cedula / RUC / NIT',
    holder_email        VARCHAR(190) NOT NULL DEFAULT '',
    holder_phone        VARCHAR(30)  NOT NULL DEFAULT '',
    instructions        TEXT         NULL COMMENT 'Aviso adicional para el cliente',
    logo_path           VARCHAR(255) NOT NULL DEFAULT '',
    currency            VARCHAR(3)   NOT NULL DEFAULT 'USD',
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order          SMALLINT     NOT NULL DEFAULT 0,
    created_at          DATETIME     NOT NULL,
    updated_at          DATETIME     NOT NULL,
    deleted_at          DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_bank_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id      BIGINT UNSIGNED NULL DEFAULT NULL,
    client_id           BIGINT UNSIGNED NULL DEFAULT NULL,
    payment_method_id   INT UNSIGNED NULL DEFAULT NULL,
    bank_account_id     INT UNSIGNED NULL DEFAULT NULL COMMENT 'Cuenta destino en transferencias',
    method_code         VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'Copia historica',
    amount              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency            VARCHAR(3)   NOT NULL DEFAULT 'USD',
    kind                ENUM('deposit','full','balance','refund') NOT NULL DEFAULT 'full',
    status              ENUM('pending','awaiting_verification','approved','rejected','refunded')
                        NOT NULL DEFAULT 'pending',
    reference           VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'Numero de comprobante o transaccion',
    transferred_at      DATETIME     NULL DEFAULT NULL COMMENT 'Fecha declarada por el cliente',
    verified_by         BIGINT UNSIGNED NULL DEFAULT NULL,
    verified_at         DATETIME     NULL DEFAULT NULL,
    rejection_reason    VARCHAR(255) NOT NULL DEFAULT '',
    notes               VARCHAR(500) NOT NULL DEFAULT '',
    created_at          DATETIME     NOT NULL,
    updated_at          DATETIME     NOT NULL,
    deleted_at          DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_payments_appointment (appointment_id),
    KEY idx_payments_status (status, created_at),
    KEY idx_payments_client (client_id),
    CONSTRAINT fk_pay_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_client FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_pay_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
    CONSTRAINT fk_pay_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts (id) ON DELETE SET NULL,
    CONSTRAINT fk_pay_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Imagen o PDF del comprobante subido por el cliente (o foto tomada en la app).
CREATE TABLE IF NOT EXISTS payment_proofs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id      BIGINT UNSIGNED NOT NULL,
    file_path       VARCHAR(255) NOT NULL COMMENT 'Ruta relativa fuera del directorio publico',
    file_mime       VARCHAR(60)  NOT NULL DEFAULT '',
    file_size       INT UNSIGNED NOT NULL DEFAULT 0,
    file_hash       CHAR(64)     NOT NULL DEFAULT '' COMMENT 'Detecta comprobantes reutilizados',
    original_name   VARCHAR(160) NOT NULL DEFAULT '',
    uploaded_by     BIGINT UNSIGNED NULL DEFAULT NULL,
    uploaded_from   ENUM('web','app','panel') NOT NULL DEFAULT 'web',
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_proof_payment (payment_id),
    KEY idx_proof_hash (file_hash),
    CONSTRAINT fk_proof_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE,
    CONSTRAINT fk_proof_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                VARCHAR(40)  NOT NULL,
    description         VARCHAR(255) NOT NULL DEFAULT '',
    discount_type       ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    min_amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_discount        DECIMAL(10,2) NULL DEFAULT NULL,
    service_id          INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = aplica a todo',
    first_visit_only    TINYINT(1)   NOT NULL DEFAULT 0,
    usage_limit         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = ilimitado',
    usage_limit_per_user INT UNSIGNED NOT NULL DEFAULT 1,
    times_used          INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at           DATETIME     NULL DEFAULT NULL,
    ends_at             DATETIME     NULL DEFAULT NULL,
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at          DATETIME     NOT NULL,
    updated_at          DATETIME     NOT NULL,
    deleted_at          DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coupons_code (code),
    KEY idx_coupons_active (is_active, starts_at, ends_at),
    CONSTRAINT fk_coupon_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupon_redemptions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id       INT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL DEFAULT NULL,
    appointment_id  BIGINT UNSIGNED NULL DEFAULT NULL,
    discount_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_redemption_coupon (coupon_id),
    KEY idx_redemption_user (user_id),
    CONSTRAINT fk_red_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
    CONSTRAINT fk_red_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_red_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movimientos del programa de fidelidad (puntos).
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    appointment_id  BIGINT UNSIGNED NULL DEFAULT NULL,
    points          INT          NOT NULL COMMENT 'Positivo suma, negativo canjea',
    balance_after   INT          NOT NULL DEFAULT 0,
    reason          VARCHAR(160) NOT NULL DEFAULT '',
    expires_at      DATETIME     NULL DEFAULT NULL,
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_loyalty_user (user_id, created_at),
    CONSTRAINT fk_loyalty_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_loyalty_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
