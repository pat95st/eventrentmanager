-- Fortlaufende, admin-konfigurierbare Kundennummer — analog zur
-- Rechnungs-/Angebots-/Mahnungs-/Auftragsbestätigungsnummer (siehe
-- App\Domain\Numbering\*, App\Infrastructure\Persistence\Pdo\
-- DocumentNumberSequence, Migration 084). Erweitert das dort eingeführte
-- generische Nummerierungssystem um einen fünften "Dokumenttyp" — auch
-- wenn ein Kunde fachlich kein Dokument ist, ist das System bereits
-- vollständig generisch (NumberedDocumentType::all(), Einstellungsseite
-- /einstellungen/nummerierung) und spart dadurch eine eigene, parallele
-- Lösung.
--
-- Anders als die Rechnungsnummer (erst beim Versenden vergeben) wird die
-- Kundennummer wie die Angebotsnummer SOFORT bei Anlage vergeben und ist
-- daher NOT NULL (siehe CreateCustomerHandler).

-- ---------------------------------------------------------------------
-- 1) Spalte + Backfill für bestehende Kunden (in Anlage-Reihenfolge,
--    damit ältere Kunden konsequent die kleineren Nummern behalten).
--    Die Zuweisung über eine Benutzervariable in Kombination mit
--    ORDER BY ist der etablierte MySQL/MariaDB-Weg, fortlaufende Nummern
--    in einem einzigen UPDATE zu vergeben, ohne Fenster-Funktionen
--    (ROW_NUMBER() OVER (...), ab MariaDB 10.2) voraussetzen zu müssen —
--    siehe die bereits bestehende Begründung bei
--    MariadbClosingTextTemplateFolderRepository::findDescendantIds() für
--    dieselbe Kompatibilitäts-Überlegung.
-- ---------------------------------------------------------------------
ALTER TABLE customers ADD COLUMN customer_number VARCHAR(50) NULL AFTER id;

SET @customer_number_rownum = 0;
UPDATE customers
    SET customer_number = CONCAT('KD-', LPAD(@customer_number_rownum := @customer_number_rownum + 1, 6, '0'))
    ORDER BY created_at ASC, id ASC;

ALTER TABLE customers
    MODIFY COLUMN customer_number VARCHAR(50) NOT NULL,
    ADD CONSTRAINT uq_customers_customer_number UNIQUE (customer_number);

-- ---------------------------------------------------------------------
-- 2) Generisches Nummerierungssystem: neuer Eintrag 'customer' in
--    document_numbering_settings/document_number_sequences (siehe
--    Migration 084) — Format identisch zum obigen Backfill ("KD-" +
--    6-stellige, nie zurückgesetzte Ziffernfolge), damit der erste über
--    die neue Logik vergebene Kunde nahtlos an das Backfill anschließt.
-- ---------------------------------------------------------------------
INSERT INTO document_numbering_settings (document_type, scheme, prefix, `separator`, digit_count) VALUES
    ('customer', 'simple', 'KD-', '-', 6);

INSERT INTO document_number_sequences (document_type, sequence_year, next_number)
    SELECT 'customer', 0, COUNT(*) + 1 FROM customers;
