-- Nutzerwunsch Bundles, Ausbaustufe 2:
--
-- * Bundles haben keinen Hersteller, kein Präfix/keine Inventarnummern,
--   keine Elektroprüfung, keine Firmware und keine 19-Zoll-Angaben.
--   articles.manufacturer_id wird dafür nullbar; bestehende Bundles werden
--   bereinigt. Das Gewicht eines Bundles wird aus seinen Bestandteilen
--   berechnet (siehe BundleWeightCalculator) und hier einmalig nachgezogen.
-- * article_bundle_component_alternatives: optionale Alternativartikel je
--   Bestandteil (z. B. Lautsprecher an verschiedenen Endstufen). Beim
--   Einplanen in eine Vermietung wird der Hauptartikel genommen, ist er im
--   Zeitraum nicht verfügbar, die erste verfügbare Alternative.
-- * rental_bundle_allocations: welche Artikel ein gebuchtes Bundle in einer
--   Vermietung tatsächlich belegt (je Artikel die Menge). Für bestehende
--   Buchungen aus den aktuellen Bestandteilen befüllt.

ALTER TABLE articles
    MODIFY COLUMN manufacturer_id CHAR(36) NULL;

UPDATE articles
SET manufacturer_id = NULL,
    prefix = NULL,
    requires_electrical_inspection = 0,
    protection_class = NULL,
    has_firmware = 0,
    is_19_inch = 0,
    height_units = NULL,
    install_depth_cm = NULL
WHERE is_bundle = 1;

UPDATE articles b
LEFT JOIN (
    SELECT abc.bundle_article_id,
           SUM(abc.quantity * CASE c.weight_unit WHEN 'g' THEN c.weight_value / 1000 ELSE c.weight_value END) AS total_kg
    FROM article_bundle_components abc
    JOIN articles c ON c.id = abc.component_article_id
    WHERE c.weight_value IS NOT NULL
    GROUP BY abc.bundle_article_id
) w ON w.bundle_article_id = b.id
SET b.weight_value = ROUND(w.total_kg, 3),
    b.weight_unit = CASE WHEN w.total_kg IS NULL THEN NULL ELSE 'kg' END
WHERE b.is_bundle = 1;

CREATE TABLE article_bundle_component_alternatives (
    id           CHAR(36) NOT NULL PRIMARY KEY,
    component_id CHAR(36) NOT NULL,
    article_id   CHAR(36) NOT NULL,
    sort_order   INT      NOT NULL DEFAULT 0,
    CONSTRAINT fk_bundle_alternatives_component
        FOREIGN KEY (component_id) REFERENCES article_bundle_components (id) ON DELETE CASCADE,
    CONSTRAINT fk_bundle_alternatives_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_bundle_alternatives (component_id, article_id),
    INDEX idx_bundle_alternatives_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_bundle_allocations (
    id               CHAR(36)     NOT NULL PRIMARY KEY,
    rental_bundle_id CHAR(36)     NOT NULL,
    article_id       CHAR(36)     NOT NULL,
    quantity         INT UNSIGNED NOT NULL,
    CONSTRAINT fk_rental_bundle_allocations_bundle
        FOREIGN KEY (rental_bundle_id) REFERENCES rental_bundles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_bundle_allocations_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_bundle_allocations (rental_bundle_id, article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO rental_bundle_allocations (id, rental_bundle_id, article_id, quantity)
SELECT UUID(), rb.id, abc.component_article_id, SUM(abc.quantity * rb.quantity)
FROM rental_bundles rb
JOIN article_bundle_components abc ON abc.bundle_article_id = rb.bundle_article_id
GROUP BY rb.id, abc.component_article_id;
