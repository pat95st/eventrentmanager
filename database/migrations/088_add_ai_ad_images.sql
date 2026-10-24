-- KI-Werbebildgenerator für Artikel (OpenAI gpt-image-1): Zugangsdaten
-- (Singleton-Einstellungszeile wie z. B. google_merchant_settings) + eine
-- Historie-Tabelle für die je Artikel generierten Werbebilder.

CREATE TABLE IF NOT EXISTS ai_ad_image_settings (
    id              TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    openai_api_key  VARCHAR(255) NULL,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_ai_ad_image_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ai_ad_image_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS article_ad_images (
    id CHAR(36) NOT NULL PRIMARY KEY,
    article_id CHAR(36) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    format VARCHAR(20) NOT NULL,
    prompt TEXT NOT NULL,
    source_article_image_id CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_article_ad_images_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_article_ad_images_article (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
