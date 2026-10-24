-- Hinweisdokumente (generische, einmal hochgeladene Dokumente, die
-- bestimmten Artikeln zugeordnet und bei Bedarf an Kunden verschickt werden
-- können), siehe architektur-und-setup.md, Abschnitt "Hinweisdokumente".

CREATE TABLE notice_documents (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    original_filename   VARCHAR(255) NOT NULL,
    stored_filename     VARCHAR(255) NOT NULL,
    mime_type           VARCHAR(127) NOT NULL,
    size_bytes          INT UNSIGNED NOT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE notice_document_articles (
    notice_document_id CHAR(36) NOT NULL,
    article_id          CHAR(36) NOT NULL,
    PRIMARY KEY (notice_document_id, article_id),
    CONSTRAINT fk_notice_document_articles_document
        FOREIGN KEY (notice_document_id) REFERENCES notice_documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_notice_document_articles_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_notice_document_articles_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
