-- Arbeitszeit-Positionen einer Rechnung — strukturell analog zu
-- invoice_items, aber mit hours statt quantity und unit_rate_per_hour (beim
-- Hinzufügen gesnapshoteter Stundensatz, siehe labor_time_articles) statt
-- eines frei editierbaren unit_price_per_day. Erscheint auf der Rechnung
-- immer NACH dem Kombirabatt (siehe App\Domain\Pricing\DocumentTotals).
CREATE TABLE invoice_labor_items (
    id                      CHAR(36)       NOT NULL PRIMARY KEY,
    invoice_id              CHAR(36)       NOT NULL,
    labor_time_article_id   CHAR(36)       NOT NULL,
    hours                   DECIMAL(6, 2)  NOT NULL,
    unit_rate_per_hour      DECIMAL(10, 2) NOT NULL,
    CONSTRAINT fk_invoice_labor_items_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_labor_items_labor_time_article
        FOREIGN KEY (labor_time_article_id) REFERENCES labor_time_articles (id) ON DELETE RESTRICT,
    INDEX idx_invoice_labor_items_invoice (invoice_id),
    INDEX idx_invoice_labor_items_labor_time_article (labor_time_article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
