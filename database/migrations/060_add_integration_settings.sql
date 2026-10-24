-- Admin-Einstellungen für die drei externen Vertriebskanal-Integrationen
-- (siehe App\Domain\Settings\GoogleMerchantSettings/GoogleBusinessSettings/
-- WhatsappBusinessSettings/IntegrationBaseSettings). Je Tabelle genau eine
-- Singleton-Zeile (id = 1), analog zu company_settings.

CREATE TABLE google_merchant_settings (
    id                              TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    merchant_id                     VARCHAR(50)  NULL,
    service_account_json_filename   VARCHAR(255) NULL,
    target_country                  VARCHAR(2)   NOT NULL DEFAULT 'DE',
    content_language                VARCHAR(5)   NOT NULL DEFAULT 'de',
    product_link_template           VARCHAR(500) NULL,
    updated_at                      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_google_merchant_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO google_merchant_settings (id) VALUES (1);

CREATE TABLE google_business_settings (
    id                          TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    client_id                   VARCHAR(255) NULL,
    client_secret               VARCHAR(255) NULL,
    access_token                VARCHAR(2048) NULL,
    refresh_token               VARCHAR(2048) NULL,
    access_token_expires_at     DATETIME     NULL,
    account_id                  VARCHAR(255) NULL,
    location_id                 VARCHAR(255) NULL,
    connected_at                DATETIME     NULL,
    updated_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_google_business_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO google_business_settings (id) VALUES (1);

CREATE TABLE whatsapp_business_settings (
    id            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    access_token  VARCHAR(2048) NULL,
    catalog_id    VARCHAR(255) NULL,
    currency      VARCHAR(3)   NOT NULL DEFAULT 'EUR',
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_whatsapp_business_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO whatsapp_business_settings (id) VALUES (1);

CREATE TABLE integration_base_settings (
    id          TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    base_url    VARCHAR(255) NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_integration_base_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO integration_base_settings (id) VALUES (1);
