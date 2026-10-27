-- Schränkt einen Gutschein optional auf eine Auswahl von Kunden ein (siehe
-- App\Domain\Voucher\VoucherRepositoryInterface::replaceCustomerRestriction()/
-- findCustomerIds()). Eine n:m-Zuordnungstabelle statt einer einzelnen
-- customer_id-Spalte auf vouchers, da ein Gutschein auf MEHRERE Kunden
-- gleichzeitig eingeschränkt werden können soll (siehe Entscheidung in der
-- Aufgabenstellung) — kein Eintrag für einen Gutschein bedeutet "für alle
-- Kunden gültig" (keine Einschränkung), nicht "für keinen Kunden gültig".
--
-- ON DELETE CASCADE auf beiden Fremdschlüsseln: wird der Gutschein selbst
-- gelöscht, ist die Einschränkung gegenstandslos; wird ein eingeschränkter
-- Kunde gelöscht, soll das weder das Löschen verhindern noch verwaiste
-- Zeilen hinterlassen — der Gutschein bleibt dann einfach mit den
-- verbleibenden Kunden eingeschränkt (oder wieder uneingeschränkt, falls es
-- der letzte war).

CREATE TABLE voucher_customers (
    voucher_id  CHAR(36) NOT NULL,
    customer_id CHAR(36) NOT NULL,
    PRIMARY KEY (voucher_id, customer_id),
    CONSTRAINT fk_voucher_customers_voucher
        FOREIGN KEY (voucher_id) REFERENCES vouchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_voucher_customers_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    INDEX idx_voucher_customers_customer (customer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
