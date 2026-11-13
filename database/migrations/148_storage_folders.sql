-- Migration 148 (Nutzerwunsch): Ordner für Veranstaltungen und Kunden im
-- externen Dateiablagesystem (Einstellungen › Ziellaufwerke).
--  - document_storage_targets.protocol: zusätzlich 'nextcloud' und
--    'owncloud' (WebDAV + Freigabe über die OCS-Share-API). Bei diesen
--    Protokollen ist base_path ein optionaler Unterordner INNERHALB der
--    Dateien des Benutzers – der WebDAV-Pfad /remote.php/dav/files/<user>/
--    wird automatisch ergänzt. Bestehende 'webdav'-Ziele bleiben unverändert.
--  - Je Ziellaufwerk: ob es für Ordner verwendet wird, die Namens-/Pfad-
--    vorlagen (Platzhalter siehe App\Domain\DocumentStorage\FolderPathTemplate)
--    sowie – nur Nextcloud/ownCloud – ob eine Freigabe immer ein Passwort
--    bekommt und nach welcher Richtlinie es erzeugt wird.
--  - storage_folders: der einmal angelegte Ordner einer Veranstaltung bzw.
--    eines Kunden samt Freigabe. Das Freigabe-Passwort wird NICHT gespeichert,
--    es wird nur einmalig nach dem Erzeugen angezeigt.

ALTER TABLE document_storage_targets
    MODIFY COLUMN protocol ENUM('webdav', 'ftps', 'sftp', 'scp', 's3', 'nextcloud', 'owncloud') NOT NULL,
    ADD COLUMN folders_enabled           TINYINT(1)   NOT NULL DEFAULT 0 AFTER s3_use_path_style,
    ADD COLUMN event_folder_template     VARCHAR(500) NOT NULL DEFAULT 'Veranstaltungen/{year}/{date} {event_name}' AFTER folders_enabled,
    ADD COLUMN customer_folder_template  VARCHAR(500) NOT NULL DEFAULT 'Kunden/{customer_number} {customer_name}' AFTER event_folder_template,
    ADD COLUMN event_subfolder_template  VARCHAR(500) NOT NULL DEFAULT '{date} {event_name}' AFTER customer_folder_template,
    ADD COLUMN share_always_password     TINYINT(1)   NOT NULL DEFAULT 0 AFTER event_subfolder_template,
    ADD COLUMN share_password_length     TINYINT UNSIGNED NOT NULL DEFAULT 16 AFTER share_always_password,
    ADD COLUMN share_password_uppercase  TINYINT(1)   NOT NULL DEFAULT 1 AFTER share_password_length,
    ADD COLUMN share_password_lowercase  TINYINT(1)   NOT NULL DEFAULT 1 AFTER share_password_uppercase,
    ADD COLUMN share_password_digits     TINYINT(1)   NOT NULL DEFAULT 1 AFTER share_password_lowercase,
    ADD COLUMN share_password_symbols    TINYINT(1)   NOT NULL DEFAULT 0 AFTER share_password_digits;

CREATE TABLE storage_folders (
    id                 CHAR(36)     NOT NULL PRIMARY KEY,
    -- Genau eine der beiden Spalten ist gesetzt (Veranstaltung ODER Kunde).
    rental_id          CHAR(36)     NULL,
    customer_id        CHAR(36)     NULL,
    storage_target_id  CHAR(36)     NOT NULL,
    -- Aufgelöster Ordnerpfad relativ zum Basis-Pfad des Ziellaufwerks. Wird
    -- beim Anlegen festgeschrieben und bei späteren Umbenennungen der
    -- Veranstaltung/des Kunden bewusst nicht mitgezogen.
    path               VARCHAR(700) NOT NULL,
    in_customer_folder TINYINT(1)   NOT NULL DEFAULT 0,
    share_id           VARCHAR(64)  NULL,
    share_url          VARCHAR(500) NULL,
    share_permission   ENUM('read', 'edit', 'upload') NULL,
    share_has_password TINYINT(1)   NOT NULL DEFAULT 0,
    share_mailed_at    DATETIME     NULL,
    share_mailed_to    VARCHAR(255) NULL,
    created_at         DATETIME     NOT NULL,
    updated_at         DATETIME     NOT NULL,
    UNIQUE KEY uq_storage_folders_rental (rental_id),
    UNIQUE KEY uq_storage_folders_customer (customer_id),
    CONSTRAINT fk_storage_folders_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_storage_folders_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_storage_folders_target FOREIGN KEY (storage_target_id) REFERENCES document_storage_targets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
