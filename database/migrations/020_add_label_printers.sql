-- Migration für bereits bestehende Installationen: Etikettendrucker-Verwaltung
-- (netzwerkfähige Brother-P-touch-Drucker o. ä.), siehe
-- App\Domain\Printing\LabelPrinter und App\Infrastructure\Printing\*.
USE materialverleih;

CREATE TABLE label_printers (
    id            VARCHAR(36)  NOT NULL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    -- Verweist auf einen Schlüssel in App\Infrastructure\Printing\LabelPrinterDriverRegistry,
    -- z. B. "brother_ptouch_raster". Neue Druckermodelle bekommen einen neuen Wert.
    driver_type   VARCHAR(50)  NOT NULL,
    ip_address    VARCHAR(45)  NOT NULL,
    port          SMALLINT UNSIGNED NOT NULL DEFAULT 9100,
    -- Bandbreite in mm (z. B. 12/24/29 bei gängigen P-touch-Bändern), bestimmt
    -- die Pixelbreite der Rasterzeilen beim Etikettendruck.
    tape_width_mm SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
