-- Migration für bereits bestehende Installationen: Rechtstexte
-- (Vermietbedingungen/Impressum/Datenschutzerklärung), Singleton-Tabelle
-- analog zu company_settings/smtp_settings. Impressum und
-- Datenschutzerklärung können wahlweise als Markdown-Text ODER als externe
-- URL gepflegt werden (siehe *_mode-Spalten); Vermietbedingungen sind
-- ausschließlich Markdown.
USE materialverleih;

CREATE TABLE legal_settings (
    id                    TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    rental_terms_markdown TEXT         NULL,
    imprint_mode          VARCHAR(20)  NOT NULL DEFAULT 'markdown',
    imprint_markdown      TEXT         NULL,
    imprint_url           VARCHAR(2000) NULL,
    privacy_mode          VARCHAR(20)  NOT NULL DEFAULT 'markdown',
    privacy_markdown      TEXT         NULL,
    privacy_url           VARCHAR(2000) NULL,
    updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_legal_settings_singleton CHECK (id = 1),
    CONSTRAINT chk_legal_settings_imprint_mode CHECK (imprint_mode IN ('markdown', 'link')),
    CONSTRAINT chk_legal_settings_privacy_mode CHECK (privacy_mode IN ('markdown', 'link'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO legal_settings (id, rental_terms_markdown, imprint_mode, imprint_markdown, imprint_url, privacy_mode, privacy_markdown, privacy_url)
VALUES (1, NULL, 'markdown', NULL, NULL, 'markdown', NULL, NULL);
