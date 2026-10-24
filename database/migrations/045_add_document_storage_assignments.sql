-- Migration für bereits bestehende Installationen: Zuordnung "welcher
-- Dokumenttyp wird auf welches(s) Ziellaufwerk(e) mit welchem Ablagepfad
-- geschoben" (siehe 044_add_document_storage_targets.sql sowie
-- App\Infrastructure\DocumentStorage\DocumentStoragePusher).
--
-- Ein Dokumenttyp kann mehrfach vorkommen (mehrere Ziellaufwerke gleich-
-- zeitig für dieselbe Rechnung/dasselbe Angebot/… — z. B. Nextcloud UND
-- S3-Archiv), daher keine UNIQUE-Beschränkung auf document_type allein.
USE materialverleih;

CREATE TABLE document_storage_assignments (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    document_type     ENUM('invoice', 'offer', 'packing_list', 'receipt') NOT NULL,
    storage_target_id CHAR(36)     NOT NULL,
    -- Ablagepfad-Vorlage relativ zum base_path/Bucket des Ziellaufwerks,
    -- unterstützt Platzhalter {year}/{month}/{day}/{document_type}/{number}/
    -- {filename} — siehe App\Infrastructure\DocumentStorage\DocumentStoragePathResolver.
    target_path       VARCHAR(500) NOT NULL,
    enabled           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at        DATETIME     NOT NULL,
    CONSTRAINT fk_document_storage_assignments_target
        FOREIGN KEY (storage_target_id) REFERENCES document_storage_targets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_document_storage_assignments_document_type ON document_storage_assignments (document_type);
