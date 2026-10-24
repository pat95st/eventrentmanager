-- Ermöglicht das Deaktivieren einzelner Artikel: deaktivierte Artikel
-- bleiben in der Artikelverwaltung (nur Administration/Artikelverwaltung)
-- sichtbar und bearbeitbar, verschwinden aber aus der Artikelauswahl beim
-- Erstellen von Angeboten/Rechnungen/Veranstaltungspositionen/Case-Inhalten
-- (siehe App\Application\Article\Query\ListArticlesHandler, Parameter
-- onlyActive). Neue Artikel sind per Default aktiv.
ALTER TABLE articles
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_billable;
