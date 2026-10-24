-- Arbeitszeit-Positionen eines Angebots — strukturell analog zu
-- offer_items, aber mit hours statt quantity und unit_rate_per_hour (beim
-- Hinzufügen gesnapshoteter Stundensatz, siehe labor_time_articles) statt
-- eines frei editierbaren unit_price_per_day. Erscheint auf Angebot/
-- Rechnung immer NACH dem Kombirabatt (siehe App\Domain\Pricing\
-- DocumentTotals).
CREATE TABLE offer_labor_items (
    id                      CHAR(36)       NOT NULL PRIMARY KEY,
    offer_id                CHAR(36)       NOT NULL,
    labor_time_article_id   CHAR(36)       NOT NULL,
    hours                   DECIMAL(6, 2)  NOT NULL,
    unit_rate_per_hour      DECIMAL(10, 2) NOT NULL,
    CONSTRAINT fk_offer_labor_items_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    CONSTRAINT fk_offer_labor_items_labor_time_article
        FOREIGN KEY (labor_time_article_id) REFERENCES labor_time_articles (id) ON DELETE RESTRICT,
    INDEX idx_offer_labor_items_offer (offer_id),
    INDEX idx_offer_labor_items_labor_time_article (labor_time_article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
