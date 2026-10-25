-- Visueller PDF-Vorlagen-Editor (siehe App\Domain\Settings\PdfLayout,
-- App\Infrastructure\Http\Controllers\PdfLayoutSettingsController).
--
-- Bisher ließen sich in den PDF-Vorlagen nur Texte (Einleitung/Schluss)
-- bearbeiten, nicht aber, AN WELCHER STELLE im Dokument welcher Block
-- erscheint (Logo, Titel, Adressen, Rechnungsinfo-Block, Einleitungs-/
-- Schlusstext usw.) — das war Kundenwunsch für diese Erweiterung.
--
-- Jede Zeile überschreibt die Position EINES Blocks (`element_key`,
-- siehe App\Domain\Settings\PdfLayout\PdfLayoutBlueprint::ELEMENTS) für
-- GENAU einen Dokumenttyp (`document_type`, siehe
-- App\Domain\Settings\PdfLayout\PdfDocumentType). Fehlt eine Zeile für
-- einen Block, zeichnet der jeweilige *PdfGenerator ihn weiterhin über
-- seine bisherige, fest kodierte Logik — bewusst so gewählt, damit
-- bestehende Installationen ohne Anpassung optisch unverändert bleiben
-- (keine Notwendigkeit, "perfekte" Default-Koordinaten zu pflegen; die
-- im Editor angezeigten Default-Boxen sind nur eine Orientierungshilfe
-- zum Ziehen, keine tatsächlich gerenderten Koordinaten).
--
-- Koordinaten sind in Millimetern, Ursprung oben links (wie in einem
-- Textverarbeitungsprogramm) — nicht im PDF-eigenen pt-Koordinatensystem
-- (Ursprung unten links), das bleibt Implementierungsdetail der
-- *PdfGenerator-Klassen (siehe dort MM_TO_PT-Konstante für die Umrechnung).
CREATE TABLE pdf_layout_elements (
    document_type VARCHAR(40) NOT NULL,
    element_key VARCHAR(40) NOT NULL,
    x_mm DECIMAL(6, 2) NOT NULL,
    y_mm DECIMAL(6, 2) NOT NULL,
    width_mm DECIMAL(6, 2) NULL,
    font_size DECIMAL(5, 2) NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (document_type, element_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
