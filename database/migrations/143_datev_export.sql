-- Migration 143 (Nutzerwunsch): DATEV-Export für den Steuerberater
--  - datev_settings: Einstellungen (Einstellungen → Integrationen → DATEV),
--    Singleton-Zeile wie integration_base_settings. Kontenfelder NULL =
--    Vorschlagskonto des gewählten Kontenrahmens (SKR 03/04).
--  - datev_exports: Protokoll erzeugter/versendeter Exporte; verhindert
--    außerdem, dass der automatische Versand (bin/send-datev-export.php)
--    denselben Zeitraum mehrfach verschickt.
CREATE TABLE IF NOT EXISTS datev_settings (
    id                          TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    enabled                     TINYINT(1)   NOT NULL DEFAULT 0,
    consultant_number           INT UNSIGNED NULL,
    client_number               INT UNSIGNED NULL,
    chart_of_accounts           CHAR(2)      NOT NULL DEFAULT '03',
    account_length              TINYINT      NOT NULL DEFAULT 4,
    fiscal_year_start_month     TINYINT      NOT NULL DEFAULT 1,
    debtor_mode                 VARCHAR(20)  NOT NULL DEFAULT 'collective',
    debtor_account              INT UNSIGNED NULL,
    creditor_account            INT UNSIGNED NULL,
    account_revenue_19          INT UNSIGNED NULL,
    account_revenue_7           INT UNSIGNED NULL,
    account_revenue_tax_free    INT UNSIGNED NULL,
    account_revenue_small_business INT UNSIGNED NULL,
    account_expense_goods       INT UNSIGNED NULL,
    account_expense_tools       INT UNSIGNED NULL,
    account_expense_vehicle     INT UNSIGNED NULL,
    account_expense_other       INT UNSIGNED NULL,
    receipt_tax_key             VARCHAR(4)   NULL,
    include_receipts            TINYINT(1)   NOT NULL DEFAULT 1,
    include_documents           TINYINT(1)   NOT NULL DEFAULT 1,
    advisor_email               VARCHAR(255) NULL,
    auto_send_enabled           TINYINT(1)   NOT NULL DEFAULT 0,
    export_interval             VARCHAR(20)  NOT NULL DEFAULT 'monthly',
    send_day                    TINYINT      NOT NULL DEFAULT 5,
    updated_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_datev_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO datev_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS datev_exports (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    period_from     DATE         NOT NULL,
    period_to       DATE         NOT NULL,
    export_trigger  VARCHAR(20)  NOT NULL,
    recipient       VARCHAR(255) NULL,
    booking_count   INT          NOT NULL DEFAULT 0,
    document_count  INT          NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    INDEX idx_datev_exports_period (period_from, period_to),
    INDEX idx_datev_exports_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
