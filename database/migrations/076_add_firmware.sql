-- Migration für bereits bestehende Installationen: ergänzt die Verwaltung
-- von Firmware-Versionen für Artikel.
--
-- - articles erhält ein Flag "has_firmware" (Gerät besitzt eine
--   aktualisierbare Firmware?). Nur dann werden die weiter unten
--   angelegten Firmware-Versionen für diesen Artikel angezeigt/verwaltet.
-- - firmware_versions speichert die im Katalog eines Artikels hinterlegten
--   Versionen (Versionsnummer, optionales Erscheinungs-/Anlagedatum).
-- - inventory_numbers erhält "installed_firmware_version_id": die auf
--   diesem konkreten Exemplar installierte Version. Bewusst eine Auswahl
--   aus firmware_versions (kein Freitext), damit der Update-verfügbar-
--   Vergleich immer über einen exakten ID-Abgleich funktioniert.
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/076_add_firmware.sql

USE materialverleih;

ALTER TABLE articles
    ADD COLUMN has_firmware TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_electrical_inspection;

CREATE TABLE firmware_versions (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    article_id  CHAR(36)     NOT NULL,
    version     VARCHAR(100) NOT NULL, -- Versionsbezeichnung, z. B. "2.3.1"; Freitext, da Hersteller-Schemata variieren
    released_at DATE         NULL,     -- Erscheinungs-/Anlagedatum laut Hersteller; optional, da oft unbekannt
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_firmware_versions_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_firmware_versions_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE inventory_numbers
    ADD COLUMN installed_firmware_version_id CHAR(36) NULL AFTER storage_location,
    ADD CONSTRAINT fk_inventory_numbers_installed_firmware_version
        FOREIGN KEY (installed_firmware_version_id) REFERENCES firmware_versions (id) ON DELETE SET NULL,
    ADD INDEX idx_inventory_numbers_installed_firmware_version (installed_firmware_version_id);
