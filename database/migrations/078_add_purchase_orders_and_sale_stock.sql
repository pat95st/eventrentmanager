-- Migration für bereits bestehende Installationen: Direktverkauf von
-- Artikeln (Einkauf beim Lieferanten UND Verkauf an Kunden), siehe
-- App\Domain\Purchase, App\Domain\Stock, App\Domain\Offer\OfferSaleItem.
--
-- Drei neue, voneinander unabhängige Bausteine:
--
-- 1. purchase_orders/purchase_order_items: einfache Bestellverwaltung
--    (Lieferant + Positionen mit Artikel/Menge/erwartetem Preis). Jede
--    Position wählt EINZELN ihr Ziel bei Wareneingang (target_type):
--    'rental_pool' legt beim Eingang einzelne Inventurnummern an (wie
--    AddInventoryNumberHandler), 'sale_stock' legt stattdessen eine
--    Bestandscharge (sale_stock_lots) an. received_quantity summiert alle
--    bisherigen (Teil-)Lieferungen je Position (siehe
--    ReceivePurchaseOrderItemHandler) — Teillieferungen sind bewusst
--    unterstützt (Nutzerentscheidung), kein einfacher Status "geliefert".
--
-- 2. sale_stock_lots/sale_stock_consumptions: reiner Verkaufsbestand (Ware
--    OHNE eigene Inventurnummer, die NIE in den Vermietpool gelangt, siehe
--    App\Domain\Purchase\PurchaseTargetType). Mehrere Chargen je Artikel mit
--    ggf. unterschiedlichem Einkaufspreis; beim Verkauf werden Chargen in
--    FIFO-Reihenfolge (receivedAt) verbraucht (siehe
--    App\Application\Stock\StockFifoAllocator), damit sich die tatsächliche
--    Marge je Verkauf exakt (statt nur über einen laufenden
--    Mittelwert-Zähler) berechnen lässt — explizite Nutzerentscheidung für
--    die genauere, aufwändigere Variante. sale_stock_consumptions ist ein
--    reines Audit-Protokoll, welche Charge(n) zu welchem Preis von welchem
--    Verkauf verbraucht wurden; die Summe steht zusätzlich gesnapshotet auf
--    sales.cost_of_goods_sold.
--
-- 3. offer_sale_items: Verkaufspositionen innerhalb eines Angebots, analog
--    zu offer_items/offer_labor_items aber als eigene Tabelle (gleiches
--    Muster: eigene Zeilenart statt eines Diskriminator-Felds). Ein Angebot
--    darf Miet- UND Verkaufspositionen gemischt enthalten (Nutzerentscheidung
--    "Gemischt erlauben"). fulfilled_sale_id bleibt NULL, bis die Position
--    tatsächlich über CreateInvoiceFromOfferHandler abgerechnet wurde —
--    dadurch kann ein Angebot (wie bisher schon für Mietpositionen) beliebig
--    oft erneut in eine Rechnung umgewandelt werden (Teil-/Schlussrechnung),
--    ohne dieselbe Verkaufsposition ein zweites Mal abzurechnen.
--
-- sales: inventory_number_id wird NULLable (ein Verkauf aus dem reinen
-- Verkaufsbestand hat kein Exemplar), quantity (bei Vermietpool-Verkäufen
-- immer 1, bei Bestandsverkäufen die verkaufte Menge) und
-- cost_of_goods_sold (gesnapshoteter Einkaufswert für die spätere
-- Margen-Berechnung, siehe App\Domain\Sale\Sale::marginAmount()) kommen neu
-- hinzu. Die bestehende UNIQUE-Sperre auf inventory_number_id bleibt
-- erhalten — MariaDB/MySQL erlauben beliebig viele NULL-Werte in einem
-- UNIQUE-Index, Bestandsverkäufe (inventory_number_id = NULL) stoßen sich
-- also nicht gegenseitig.
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
USE materialverleih;

ALTER TABLE sales
    MODIFY COLUMN inventory_number_id CHAR(36) NULL,
    ADD COLUMN quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER article_id,
    ADD COLUMN cost_of_goods_sold DECIMAL(10, 2) NULL AFTER sale_price;

CREATE TABLE purchase_orders (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    supplier_id   CHAR(36)     NOT NULL,
    status        VARCHAR(20)  NOT NULL DEFAULT 'draft',
    ordered_at    DATETIME     NULL,
    note          TEXT         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_purchase_orders_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    INDEX idx_purchase_orders_supplier (supplier_id),
    INDEX idx_purchase_orders_status (status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
    id                 CHAR(36)     NOT NULL PRIMARY KEY,
    purchase_order_id  CHAR(36)     NOT NULL,
    article_id         CHAR(36)     NOT NULL,
    target_type        VARCHAR(20)  NOT NULL, -- 'rental_pool' | 'sale_stock', siehe App\Domain\Purchase\PurchaseTargetType
    ordered_quantity   INT UNSIGNED NOT NULL,
    unit_price         DECIMAL(10, 2) NOT NULL,
    received_quantity  INT UNSIGNED NOT NULL DEFAULT 0, -- Summe aller bisherigen Teillieferungen, siehe ReceivePurchaseOrderItemHandler
    CONSTRAINT fk_purchase_order_items_order
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_order_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_purchase_order_items_order (purchase_order_id),
    INDEX idx_purchase_order_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE sale_stock_lots (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    article_id              CHAR(36)     NOT NULL,
    purchase_order_item_id  CHAR(36)     NULL, -- NULL bei einer manuellen Bestandskorrektur ohne Bestellbezug
    unit_purchase_price     DECIMAL(10, 2) NOT NULL,
    quantity_received       INT UNSIGNED NOT NULL,
    quantity_remaining      INT UNSIGNED NOT NULL, -- sinkt mit jedem Verkaufsverbrauch, siehe sale_stock_consumptions
    received_at             DATETIME     NOT NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sale_stock_lots_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sale_stock_lots_purchase_order_item
        FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id) ON DELETE SET NULL,
    -- FIFO-Verbrauch sortiert nach (article_id, received_at) — siehe
    -- App\Application\Stock\StockFifoAllocator.
    INDEX idx_sale_stock_lots_article_received (article_id, received_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE sale_stock_consumptions (
    id                   CHAR(36)     NOT NULL PRIMARY KEY,
    sale_id              CHAR(36)     NOT NULL,
    stock_lot_id         CHAR(36)     NOT NULL,
    quantity             INT UNSIGNED NOT NULL,
    unit_purchase_price  DECIMAL(10, 2) NOT NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sale_stock_consumptions_sale
        FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_stock_consumptions_stock_lot
        FOREIGN KEY (stock_lot_id) REFERENCES sale_stock_lots (id) ON DELETE RESTRICT,
    INDEX idx_sale_stock_consumptions_sale (sale_id),
    INDEX idx_sale_stock_consumptions_stock_lot (stock_lot_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Verkaufspositionen eines Angebots — strukturell analog zu offer_items/
-- offer_labor_items (eigene Tabelle statt Diskriminator-Feld), aber mit
-- Einmalpreis (unit_price) statt Tagesmietpreis und derselben
-- Vermietpool-/Verkaufsbestand-Unterscheidung wie bei sales.
CREATE TABLE offer_sale_items (
    id                   CHAR(36)     NOT NULL PRIMARY KEY,
    offer_id             CHAR(36)     NOT NULL,
    article_id           CHAR(36)     NOT NULL,
    inventory_number_id  CHAR(36)     NULL, -- gesetzt = Vermietpool (konkretes Exemplar, quantity immer 1); NULL = Verkaufsbestand
    quantity             INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price           DECIMAL(10, 2) NOT NULL,
    line_note            VARCHAR(255) NULL,
    fulfilled_sale_id    CHAR(36)     NULL, -- gesetzt, sobald über CreateInvoiceFromOfferHandler tatsächlich abgerechnet
    CONSTRAINT fk_offer_sale_items_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    CONSTRAINT fk_offer_sale_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_offer_sale_items_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_offer_sale_items_fulfilled_sale
        FOREIGN KEY (fulfilled_sale_id) REFERENCES sales (id) ON DELETE SET NULL,
    INDEX idx_offer_sale_items_offer (offer_id),
    INDEX idx_offer_sale_items_article (article_id),
    INDEX idx_offer_sale_items_inventory_number (inventory_number_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
