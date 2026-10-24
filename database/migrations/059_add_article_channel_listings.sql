-- Übermittlung einzelner Artikel an externe Vertriebskanäle (Google
-- Merchant Center, Google Unternehmensprofil, WhatsApp Business), siehe
-- App\Domain\Article\ArticleChannelListing. Je Artikel und Kanal existiert
-- höchstens eine Zeile (UNIQUE KEY).
CREATE TABLE article_channel_listings (
    id              CHAR(36)        NOT NULL PRIMARY KEY,
    article_id      CHAR(36)        NOT NULL,
    channel         VARCHAR(30)     NOT NULL, -- 'google_merchant' | 'google_business' | 'whatsapp_business'
    enabled         TINYINT(1)      NOT NULL DEFAULT 0,
    external_id     VARCHAR(255)    NULL, -- vom jeweiligen Kanal vergebene Produkt-/Beitrags-ID
    external_link   VARCHAR(500)    NULL, -- in der Artikelverwaltung angezeigter "Link zum Artikel"
    status          VARCHAR(20)     NOT NULL DEFAULT 'pending', -- 'pending' | 'synced' | 'failed'
    last_synced_at  DATETIME        NULL,
    last_error      TEXT            NULL,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_channel_listings_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT uniq_article_channel_listings_article_channel UNIQUE (article_id, channel)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
