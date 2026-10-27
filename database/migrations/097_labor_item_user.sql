-- Mitarbeiter/Benutzer je Arbeitszeit-Position (siehe
-- App\Domain\Invoice\InvoiceLaborItem::$userId /
-- App\Domain\Offer\OfferLaborItem::$userId). Bewusst NULLable und
-- ON DELETE SET NULL: ein gelöschter Benutzer soll weder das Löschen
-- verhindern noch die Rechnung/das Angebot selbst beeinträchtigen —
-- die Arbeitszeit-Position (Stunden/Satz) bleibt dann einfach ohne
-- zugeordneten Mitarbeiter bestehen. Da Arbeitszeit-Positionen bereits
-- vorher als Array (mehrere Zeilen je Dokument) geführt wurden, ergibt
-- sich "mehrere Mitarbeiter je Veranstaltung" allein aus mehreren
-- Zeilen mit unterschiedlichem user_id — keine Strukturänderung nötig.

ALTER TABLE invoice_labor_items
    ADD COLUMN user_id CHAR(36) NULL AFTER labor_time_article_id,
    ADD CONSTRAINT fk_invoice_labor_items_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    ADD INDEX idx_invoice_labor_items_user (user_id);

ALTER TABLE offer_labor_items
    ADD COLUMN user_id CHAR(36) NULL AFTER labor_time_article_id,
    ADD CONSTRAINT fk_offer_labor_items_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    ADD INDEX idx_offer_labor_items_user (user_id);
