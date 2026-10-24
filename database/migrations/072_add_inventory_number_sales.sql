-- Migration für bereits bestehende Installationen: Verkauf einzelner
-- Artikel-Exemplare (Inventarnummern) aus dem Bestand.
--
-- sales: EIN Verkauf pro Inventarnummer (UNIQUE auf inventory_number_id) —
-- einmal verkauft, bleibt die Inventarnummer selbst als Datensatz
-- dauerhaft erhalten (siehe App\Infrastructure\Persistence\Pdo\
-- MariadbDatabaseDumper-analoge Begründung: KEIN DELETE auf
-- inventory_numbers mehr möglich für verkaufte Exemplare, siehe
-- DeleteInventoryNumberHandler) — die Nummer selbst wird dadurch NIE wieder
-- für ein neues Exemplar vergeben (InventoryNumberGenerator ermittelt die
-- nächste freie Nummer weiterhin anhand ALLER vorhandenen Zeilen,
-- verkaufte eingeschlossen). customer_id ist bewusst NULLable (Barverkauf
-- ohne hinterlegten Kunden). invoice_id verweist auf die beim Verkauf
-- automatisch als Entwurf angelegte Rechnung (siehe
-- App\Application\Sale\Command\SellInventoryNumberHandler) — dieselbe
-- fortlaufende Rechnungsnummern-Sequenz wie alle anderen Rechnungen, daher
-- bewusst KEIN eigenständiges "Verkaufsbeleg"-Nummernformat.
--
-- invoices.shipping_amount: optionaler, zusätzlich zur eigentlichen Position
-- abgerechneter Versandkostenbetrag (siehe App\Domain\Pricing\
-- DocumentTotals) — als allgemeines Rechnungsfeld angelegt (nicht nur für
-- Verkäufe), aktuell aber nur beim Verkauf-Workflow tatsächlich befüllbar;
-- über die normale Rechnungsbearbeitung bleibt ein einmal gesetzter Wert
-- unverändert erhalten (UpdateInvoiceHandler fasst dieses Feld nicht an).
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
USE materialverleih;

ALTER TABLE invoices
    ADD COLUMN shipping_amount DECIMAL(10, 2) NULL AFTER discount_value;

CREATE TABLE sales (
    id                  CHAR(36)       NOT NULL PRIMARY KEY,
    inventory_number_id CHAR(36)       NOT NULL,
    article_id          CHAR(36)       NOT NULL,
    customer_id         CHAR(36)       NULL,
    invoice_id          CHAR(36)       NOT NULL,
    sale_price          DECIMAL(10, 2) NOT NULL,
    shipping_amount     DECIMAL(10, 2) NULL,
    sold_at             DATETIME       NOT NULL,
    note                TEXT           NULL,
    created_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_sales_inventory_number UNIQUE (inventory_number_id),
    CONSTRAINT fk_sales_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id),
    CONSTRAINT fk_sales_article
        FOREIGN KEY (article_id) REFERENCES articles (id),
    CONSTRAINT fk_sales_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
