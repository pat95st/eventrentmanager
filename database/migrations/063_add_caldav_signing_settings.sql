-- Zertifikat zum Signieren heruntergeladener .mobileconfig-Profile (CalDAV-
-- Konto-Einrichtung), siehe App\Domain\Settings\CalDavSigningSettings.
CREATE TABLE caldav_signing_settings (
    id                   TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    -- Beide NULL, solange kein Zertifikat hochgeladen/erzeugt wurde; die
    -- Dateien selbst liegen unter storage/uploads/caldav-signing/ (siehe
    -- LocalFileStorage).
    certificate_filename VARCHAR(255) NULL,
    private_key_filename VARCHAR(255) NULL,
    -- true = automatisch von der Anwendung erzeugtes Zertifikat (Fallback),
    -- false = vom Administrator hochgeladenes echtes Zertifikat.
    is_self_signed       TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_caldav_signing_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO caldav_signing_settings (id) VALUES (1);
