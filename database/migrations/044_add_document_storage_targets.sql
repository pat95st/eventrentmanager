-- Migration für bereits bestehende Installationen: "Ziellaufwerke" — vom
-- Administrator hinterlegte externe Speicherziele (WebDAV/Nextcloud, FTPS,
-- SFTP, SCP, S3-kompatibler Objektspeicher), auf die fertige Dokumente
-- (Rechnung, Angebot, Packliste, Beleg) automatisch UND SYNCHRON, im selben
-- Web-Request, in dem sie erzeugt werden, geschoben werden können — siehe
-- App\Infrastructure\DocumentStorage\DocumentStoragePusher sowie
-- 045_add_document_storage_assignments.sql für die Zuordnung "welcher
-- Dokumenttyp -> welches(s) Ziellaufwerk(e)".
--
-- Anders als z. B. mailbox_settings/push_settings ist dies KEINE
-- Singleton-Tabelle: ein Betrieb kann beliebig viele Ziellaufwerke gleich-
-- zeitig verbinden (z. B. Nextcloud für die Buchhaltung UND ein separater
-- S3-Bucket als Archiv), daher CHAR(36)-UUID-Primärschlüssel analog zu
-- label_printers/receipts statt TINYINT(id=1).
--
-- Alle protokollspezifischen Felder liegen bewusst in EINER breiten Tabelle
-- (statt je Protokoll eine eigene Tabelle) — analog zu label_printers, wo
-- der driver_type ebenfalls bestimmt, welche Felder tatsächlich genutzt
-- werden. Zugangsdaten (password/private_key/s3_secret_access_key) werden
-- im Klartext gespeichert, genau wie das SMTP-/Mailbox-Passwort — nur für
-- Administratoren über die Anwendung erreichbar.
USE materialverleih;

CREATE TABLE document_storage_targets (
    id                       CHAR(36)      NOT NULL PRIMARY KEY,
    name                     VARCHAR(255)  NOT NULL,
    protocol                 ENUM('webdav', 'ftps', 'sftp', 'scp', 's3') NOT NULL,

    -- WebDAV / FTPS / SFTP / SCP
    host                     VARCHAR(255)  NULL,
    port                     SMALLINT UNSIGNED NULL,
    username                 VARCHAR(255)  NULL,
    password                 VARCHAR(255)  NULL,

    -- SFTP / SCP: alternativ zu username+password (Public-Key-Auth statt Passwort-Auth).
    private_key              TEXT          NULL,
    private_key_passphrase   VARCHAR(255)  NULL,

    -- WebDAV: URL-Pfad-Präfix nach dem Host (z. B. Nextcloud
    -- "/remote.php/dav/files/username/"). FTPS/SFTP/SCP: Verzeichnis-Präfix
    -- auf dem Zielserver.
    base_path                VARCHAR(500)  NULL,

    webdav_use_https         TINYINT(1)    NOT NULL DEFAULT 1,
    -- Nur für selbstsignierte WebDAV-Zertifikate gedacht, Default AUS
    -- (siehe App\Infrastructure\DocumentStorage\WebDavClient) — TLS-
    -- Verifikation bleibt sonst immer aktiv.
    webdav_allow_self_signed TINYINT(1)    NOT NULL DEFAULT 0,

    -- S3-kompatibler Objektspeicher (AWS S3 selbst oder MinIO/Hetzner/Wasabi/…).
    s3_access_key_id         VARCHAR(255)  NULL,
    s3_secret_access_key     VARCHAR(255)  NULL,
    s3_bucket                VARCHAR(255)  NULL,
    s3_region                VARCHAR(64)   NULL DEFAULT 'eu-central-1',
    -- NULL/leer = echtes AWS S3 (https://s3.{region}.amazonaws.com), gesetzt
    -- = S3-kompatibler Dienst mit eigenem Endpoint.
    s3_endpoint              VARCHAR(255)  NULL,
    s3_use_path_style        TINYINT(1)    NOT NULL DEFAULT 0,

    enabled                  TINYINT(1)    NOT NULL DEFAULT 1,
    created_at               DATETIME      NOT NULL,
    updated_at               DATETIME      NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
