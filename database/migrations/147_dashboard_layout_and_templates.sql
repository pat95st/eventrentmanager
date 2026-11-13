-- Migration 147 (Nutzerwunsch): frei anordenbare Startseite und Vorlagen
--  - users.dashboard_layout: eigene Anordnung des Benutzers als JSON
--    {"main": [...], "side": [...], "bottom": [...]} mit Kachel-Schlüsseln
--    (siehe App\Application\Dashboard\DashboardTileCatalog). NULL = noch
--    nicht angeordnet, dann gilt die Standardanordnung; die bisherige
--    Auswahl aus users.dashboard_widgets (Migration 090) wird dabei weiter
--    berücksichtigt.
--  - dashboard_templates: von Administratoren gepflegte Vorlagen, die sich
--    Benutzer unter /profil/dashboard als Kopie übernehmen können.
-- Was ein Benutzer tatsächlich sieht, richtet sich weiterhin nach seinen
-- Rechten – weder eine eigene Anordnung noch eine Vorlage schaltet Kacheln
-- frei.

ALTER TABLE users ADD COLUMN dashboard_layout TEXT NULL AFTER dashboard_widgets;

CREATE TABLE dashboard_templates (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    layout      TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL,
    updated_at  DATETIME     NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
