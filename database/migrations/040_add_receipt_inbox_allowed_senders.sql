-- Migration für bereits bestehende Installationen: Allow-List der
-- Absenderadressen, deren E-Mails beim Beleg-Posteingang-Import (siehe
-- Migration 039/041) überhaupt berücksichtigt werden — jede Nachricht von
-- einer nicht gelisteten Adresse wird beim Import komplett ignoriert
-- (nicht gespeichert), siehe
-- App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler.
--
-- Bewusst eine einfache, admin-verwaltete Liste (hinzufügen/löschen, kein
-- Bearbeiten) analog zu manufacturers/categories, nicht in
-- mailbox_settings integriert, da es sich um beliebig viele Einträge statt
-- fester Einstellungen handelt.
USE materialverleih;

CREATE TABLE receipt_inbox_allowed_senders (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    email       VARCHAR(255) NOT NULL,
    note        VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_receipt_inbox_allowed_senders_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
