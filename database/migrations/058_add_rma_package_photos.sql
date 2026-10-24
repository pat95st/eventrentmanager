-- Fotos des Pakets/der Verpackung VOR dem Versand einer externen RMA
-- (Beweissicherung, siehe App\Domain\Rma\RmaPackagePhoto). Analog zu
-- rma_documents, aber bewusst als eigene Tabelle: nur Bilder erlaubt und
-- eigener Anzeigebereich ("Fotos vor dem Versand") in der Oberfläche.
CREATE TABLE rma_package_photos (
    id                  CHAR(36)        NOT NULL PRIMARY KEY,
    rma_id              CHAR(36)        NOT NULL,
    original_filename   VARCHAR(255)    NOT NULL,
    stored_filename     VARCHAR(255)    NOT NULL,
    mime_type           VARCHAR(150)    NOT NULL,
    size_bytes          INT UNSIGNED    NOT NULL,
    uploaded_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rma_package_photos_rma
        FOREIGN KEY (rma_id) REFERENCES rmas (id) ON DELETE CASCADE,
    INDEX idx_rma_package_photos_rma (rma_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
