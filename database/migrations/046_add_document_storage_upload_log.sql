-- Migration für bereits bestehende Installationen: Protokoll jedes einzelnen
-- (Versuchs eines) synchronen Datei-Pushs auf ein Ziellaufwerk — WICHTIG für
-- die Admin-Sichtbarkeit, da ein fehlgeschlagener Push NIEMALS die
-- eigentliche Aktion (Rechnung versenden, Beleg anlegen, …) unterbricht
-- oder rückgängig macht (siehe
-- App\Infrastructure\DocumentStorage\DocumentStoragePusher) — ohne dieses
-- Protokoll gäbe es sonst keine sichtbare Spur eines fehlgeschlagenen Pushs.
--
-- storage_target_id ist ZUSÄTZLICH zu assignment_id gespeichert (statt nur
-- über die Zuordnung referenziert), damit ein Log-Eintrag auch nach dem
-- Löschen der Zuordnung noch erkennen lässt, welches Ziellaufwerk gemeint
-- war (ON DELETE SET NULL statt CASCADE bei beiden Fremdschlüsseln — das
-- Protokoll soll ein Löschen der Konfiguration überdauern).
USE materialverleih;

CREATE TABLE document_storage_upload_log (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    document_type     ENUM('invoice', 'offer', 'packing_list', 'receipt') NOT NULL,
    -- ID der jeweiligen Rechnung/des Angebots/der Veranstaltung (Packliste)/des Belegs.
    source_id         CHAR(36)     NOT NULL,
    assignment_id     CHAR(36)     NULL,
    storage_target_id CHAR(36)     NULL,
    remote_path       VARCHAR(500) NOT NULL,
    status            ENUM('success', 'failed') NOT NULL,
    error_message     VARCHAR(1000) NULL,
    created_at        DATETIME     NOT NULL,
    CONSTRAINT fk_document_storage_upload_log_assignment
        FOREIGN KEY (assignment_id) REFERENCES document_storage_assignments (id) ON DELETE SET NULL,
    CONSTRAINT fk_document_storage_upload_log_target
        FOREIGN KEY (storage_target_id) REFERENCES document_storage_targets (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_document_storage_upload_log_document ON document_storage_upload_log (document_type, source_id);
CREATE INDEX idx_document_storage_upload_log_created_at ON document_storage_upload_log (created_at);
