-- Freitext-Posten und Zwischentexte (Zwischenüberschriften ohne Preis) auf
-- Angeboten und Rechnungen — generalisiert das orderedRows()-Konzept, das
-- zuerst für Auftragsbestätigungen eingeführt wurde (siehe Migration 084,
-- order_confirmation_free_text_items/order_confirmation_section_texts).
-- Arbeitszeit-/Verkaufspositionen bleiben davon bewusst unberührt (siehe
-- OfferDetail/InvoiceDetail-Klassendoc) und erhalten KEIN sort_order.

-- ---------------------------------------------------------------------
-- 1) sort_order auf offer_items/invoice_items: regelt die gemeinsame
--    Reihenfolge mit den neuen Freitext-Posten/Zwischentexten (siehe
--    OfferRow/InvoiceRow sowie OfferDetail::orderedRows()/
--    InvoiceDetail::orderedRows()). Bestehende Zeilen werden anhand der
--    bisherigen Anzeige-Reihenfolge (Artikelname aufsteigend, siehe vormals
--    "ORDER BY a.name ASC" in MariadbOfferRepository/MariadbInvoiceRepository)
--    je Angebot/Rechnung durchnummeriert, damit sich die bisherige Reihenfolge
--    nach der Umstellung auf sort_order nicht ändert.
-- ---------------------------------------------------------------------
ALTER TABLE offer_items ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER line_note;

UPDATE offer_items oi
    JOIN articles a ON a.id = oi.article_id
    SET oi.sort_order = (
        SELECT COUNT(*)
        FROM offer_items oi2
        JOIN articles a2 ON a2.id = oi2.article_id
        WHERE oi2.offer_id = oi.offer_id
          AND (a2.name < a.name OR (a2.name = a.name AND oi2.id < oi.id))
    );

ALTER TABLE invoice_items ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER line_note;

UPDATE invoice_items ii
    JOIN articles a ON a.id = ii.article_id
    SET ii.sort_order = (
        SELECT COUNT(*)
        FROM invoice_items ii2
        JOIN articles a2 ON a2.id = ii2.article_id
        WHERE ii2.invoice_id = ii.invoice_id
          AND (a2.name < a.name OR (a2.name = a.name AND ii2.id < ii.id))
    );

-- ---------------------------------------------------------------------
-- 2) Neue Tabellen für Freitext-Posten/Zwischentexte, strukturell identisch
--    zu order_confirmation_free_text_items/order_confirmation_section_texts
--    (siehe Migration 084).
-- ---------------------------------------------------------------------
CREATE TABLE offer_free_text_items (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    offer_id            CHAR(36)      NOT NULL,
    description         VARCHAR(255)  NOT NULL,
    quantity            DECIMAL(10, 2) NOT NULL DEFAULT 1,
    unit_price          DECIMAL(10, 2) NOT NULL DEFAULT 0,
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_offer_free_text_items_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    INDEX idx_offer_free_text_items_offer (offer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE offer_section_texts (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    offer_id            CHAR(36)      NOT NULL,
    heading             VARCHAR(255)  NOT NULL,
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_offer_section_texts_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    INDEX idx_offer_section_texts_offer (offer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE invoice_free_text_items (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    invoice_id          CHAR(36)      NOT NULL,
    description         VARCHAR(255)  NOT NULL,
    quantity            DECIMAL(10, 2) NOT NULL DEFAULT 1,
    unit_price          DECIMAL(10, 2) NOT NULL DEFAULT 0,
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_invoice_free_text_items_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    INDEX idx_invoice_free_text_items_invoice (invoice_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE invoice_section_texts (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    invoice_id          CHAR(36)      NOT NULL,
    heading             VARCHAR(255)  NOT NULL,
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_invoice_section_texts_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    INDEX idx_invoice_section_texts_invoice (invoice_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
