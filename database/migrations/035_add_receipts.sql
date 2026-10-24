-- Migration für bereits bestehende Installationen: "Belege" (eingehende
-- Lieferantenrechnungen/-quittungen, z. B. für Verbrauchsmaterial, Werkzeug
-- oder Fahrzeugkosten), siehe App\Domain\Receipt\Receipt.
--
-- Anders als bei Rechnungen (invoices, die WIR an Kunden stellen) ist ein
-- Beleg hier bewusst ein einfacher CRUD-Datensatz OHNE fortlaufende
-- Nummerierung, ohne PDF-Erzeugung durch die Anwendung (die PDF-Datei wird
-- hochgeladen, siehe original_filename/stored_filename/mime_type/size_bytes,
-- strukturell analog zu rma_documents aus Migration 007) und ohne
-- Sperr-/Unveränderlichkeits-Workflow — der Zahlungsstatus (status/paid_at)
-- ist eine rein interne, jederzeit in beide Richtungen umschaltbare
-- Buchhaltungs-Markierung (siehe MarkReceiptPaidHandler/MarkReceiptOpenHandler).
--
-- Ein Lieferant ist ZWINGEND erforderlich (supplier_id NOT NULL, ON DELETE
-- RESTRICT) — ein Lieferant mit mindestens einem Beleg darf nicht mehr
-- gelöscht werden, analog zur bestehenden Löschsperre über
-- inventory_numbers.supplier_id (siehe SupplierRepositoryInterface::isUsedByInventoryNumbers()
-- bzw. die neue Methode isUsedByReceipts()).
USE materialverleih;

CREATE TABLE receipts (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    supplier_id         CHAR(36)      NOT NULL,
    -- Beleg-/Rechnungsnummer DES LIEFERANTEN (nicht unsere eigene), rein
    -- informatives Freitextfeld, optional.
    receipt_number      VARCHAR(64)   NULL,
    amount              DECIMAL(10, 2) NOT NULL,
    receipt_date        DATE          NOT NULL,
    category            VARCHAR(20)   NOT NULL DEFAULT 'sonstiges',
    status              VARCHAR(20)   NOT NULL DEFAULT 'offen',
    paid_at             DATETIME      NULL,
    note                TEXT          NULL,
    original_filename   VARCHAR(255)  NOT NULL,
    stored_filename      VARCHAR(255) NOT NULL,
    mime_type           VARCHAR(100)  NOT NULL,
    size_bytes           INT UNSIGNED NOT NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_receipts_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    INDEX idx_receipts_supplier (supplier_id),
    INDEX idx_receipts_status (status),
    INDEX idx_receipts_date (receipt_date),
    INDEX idx_receipts_category (category)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
