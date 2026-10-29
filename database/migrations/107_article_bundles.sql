-- Nutzerwunsch "Artikel nicht einzeln vermietbar, nur als Bundle" (Beispiel:
-- passive Lautsprecher nur zusammen mit der passenden Endstufe).
--
-- * articles.is_rentable_individually: 0 = darf in Vermietungen und
--   Miet-Positionen von Angeboten/Auftragsbestätigungen/Rechnungen NICHT
--   einzeln ausgewählt werden, nur als Bestandteil eines Bundles.
-- * articles.is_bundle: Artikel ist ein Bundle (eigener Name und
--   Tagesmietsatz inkl. Preisstaffelung, aber kein physischer Bestand) —
--   seine Bestandteile stehen in article_bundle_components.
-- * rental_bundles: welche Bundles in einer Vermietung gebucht sind. Die
--   physischen Bestandteile stehen zusätzlich wie bisher als rental_items
--   (Planung/Ausgabe je Artikel); rental_bundles merkt sich, welche Mengen
--   davon zu einem Bundle gehören (Schutz vor Einzel-Entfernung, Abrechnung
--   zum Bundle-Preis beim Erzeugen einer Rechnung).

ALTER TABLE articles
    ADD COLUMN is_rentable_individually TINYINT(1) NOT NULL DEFAULT 1 AFTER is_billable,
    ADD COLUMN is_bundle TINYINT(1) NOT NULL DEFAULT 0 AFTER is_rentable_individually;

CREATE TABLE article_bundle_components (
    id                   CHAR(36)     NOT NULL PRIMARY KEY,
    bundle_article_id    CHAR(36)     NOT NULL,
    component_article_id CHAR(36)     NOT NULL,
    quantity             INT UNSIGNED NOT NULL,
    sort_order           INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_article_bundle_components_bundle
        FOREIGN KEY (bundle_article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_bundle_components_component
        FOREIGN KEY (component_article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_article_bundle_components (bundle_article_id, component_article_id),
    INDEX idx_article_bundle_components_component (component_article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_bundles (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id         CHAR(36)     NOT NULL,
    bundle_article_id CHAR(36)     NOT NULL,
    quantity          INT UNSIGNED NOT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_bundles_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_bundles_article
        FOREIGN KEY (bundle_article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_bundles (rental_id, bundle_article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
