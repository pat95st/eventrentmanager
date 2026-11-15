-- Migration 157: Stromplanung für Veranstaltungen (Nutzerwunsch "mit welcher
-- Stromstärke/Leistung auf welcher Phase rechnen").
--
--   1. articles.input_power_w: Eingangsleistung eines Geräts in Watt
--      (Leitungen haben keine Leistung; NULL = unbekannt/keine).
--   2. rental_power_plans: Gleichzeitigkeitsfaktor je Veranstaltung.
--   3. rental_power_assignments: wo ein geplantes Gerät bzw. ein geplanter
--      Stromverteiler steckt. Geplante Artikel haben eine Menge, deshalb
--      wird jedes Exemplar über (article_id, unit_no) angesprochen. Ziel ist
--      entweder ein Abgang (outlet_id) eines eingeplanten Stromverteilers
--      (target_article_id, target_unit_no) oder ein Stromanschluss des
--      Veranstaltungsorts (venue_power_connection_id). phase wählt die Phase,
--      wenn sie sich nicht aus dem Abgang ergibt (z. B. Adapter von CEE rot).
--      Auf Abgänge und Anschlüsse zeigt bewusst kein Fremdschlüssel: veraltete
--      Ziele zeigt die Planung als Hinweis an, statt die Zuordnung zu löschen.

ALTER TABLE articles
    ADD COLUMN input_power_w INT UNSIGNED NULL;

CREATE TABLE rental_power_plans (
    rental_id            CHAR(36)      NOT NULL PRIMARY KEY,
    simultaneity_factor  DECIMAL(4, 2) NOT NULL DEFAULT 1.00,       -- 0,01 – 1,00
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_power_plans_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_power_assignments (
    rental_id                  CHAR(36)          NOT NULL,
    article_id                 CHAR(36)          NOT NULL,
    unit_no                    SMALLINT UNSIGNED NOT NULL,          -- 1 … geplante Menge
    target_article_id          CHAR(36)          NULL,              -- Stromverteiler, in dem das Exemplar steckt
    target_unit_no             SMALLINT UNSIGNED NULL,
    outlet_id                  CHAR(36)          NULL,              -- power_distributor_outlets.id
    venue_power_connection_id  CHAR(36)          NULL,              -- alternativ: Anschluss des Veranstaltungsorts
    phase                      VARCHAR(2)        NULL,              -- L1 | L2 | L3
    simultaneity_factor        DECIMAL(4, 2)     NULL,              -- abweichender Faktor für dieses Exemplar
    PRIMARY KEY (rental_id, article_id, unit_no),
    CONSTRAINT fk_rental_power_assignments_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_power_assignments_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_power_assignments_target
        FOREIGN KEY (target_article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
