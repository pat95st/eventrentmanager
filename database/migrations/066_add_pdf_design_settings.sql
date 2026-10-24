-- Gemeinsames Design-Grundgerüst für Angebots-/Rechnungs-PDFs (siehe
-- App\Domain\Settings\PdfDesignSettings): eine Akzentfarbe (Titel,
-- Tabellenkopf-Füllung, Trennlinien) und die Logo-Position, für beide
-- Dokumenttypen gemeinsam — Vorstufe zu einem künftigen freien
-- Seitendesigner (siehe Projektentscheidung, bewusst zunächst nur diese
-- beiden risikofreien, additiven Einstellungen statt einer kompletten
-- Neuentwicklung der PDF-Generatoren).

CREATE TABLE pdf_design_settings (
    id           TINYINT              NOT NULL PRIMARY KEY DEFAULT 1,
    -- Akzentfarbe als RGB (0-255 je Kanal) — Default (0,0,0) = Schwarz,
    -- entspricht optisch exakt dem bisherigen, hartkodierten Verhalten
    -- (Titel/Trennlinien), damit sich am bestehenden PDF-Erscheinungsbild
    -- ohne bewusste Änderung durch den Nutzer NICHTS ändert.
    accent_r     TINYINT UNSIGNED     NOT NULL DEFAULT 0,
    accent_g     TINYINT UNSIGNED     NOT NULL DEFAULT 0,
    accent_b     TINYINT UNSIGNED     NOT NULL DEFAULT 0,
    logo_position ENUM('left', 'right') NOT NULL DEFAULT 'right',
    updated_at   DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_pdf_design_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO pdf_design_settings (id, accent_r, accent_g, accent_b, logo_position) VALUES (1, 0, 0, 0, 'right');
