-- Nutzerwunsch "Ansprechpartner-Auswahl + eigene Adresse/Telefon je
-- Ansprechpartner" (siehe App\Domain\Customer\CustomerContact, Migration
-- 100): ein Ansprechpartner hatte bislang bereits ein eigenes Telefon
-- (customer_contacts.phone), aber keine eigene Adresse — diese wurde immer
-- vom Kunden selbst übernommen. Ergänzt optionale eigene Adressfelder je
-- Ansprechpartner (bleiben alle NULL = "wie Kunde") sowie eine optionale
-- Verknüpfung von Rechnungen/Angeboten/Auftragsbestätigungen mit einem
-- konkreten Ansprechpartner des gewählten Kunden (analog zu
-- source_rental_id, siehe Migration 103).

ALTER TABLE customer_contacts
    ADD COLUMN street VARCHAR(255) NULL AFTER email,
    ADD COLUMN address_line2 VARCHAR(255) NULL AFTER street,
    ADD COLUMN postal_code VARCHAR(20) NULL AFTER address_line2,
    ADD COLUMN city VARCHAR(255) NULL AFTER postal_code;

ALTER TABLE invoices
    ADD COLUMN contact_id CHAR(36) NULL AFTER customer_id,
    ADD CONSTRAINT fk_invoices_contact
        FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL;

ALTER TABLE offers
    ADD COLUMN contact_id CHAR(36) NULL AFTER customer_id,
    ADD CONSTRAINT fk_offers_contact
        FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL;

ALTER TABLE order_confirmations
    ADD COLUMN contact_id CHAR(36) NULL AFTER customer_id,
    ADD CONSTRAINT fk_order_confirmations_contact
        FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL;
