-- Migration für bereits bestehende Installationen: optionale
-- Benachrichtigung per E-Mail, wenn beim Beleg-Posteingang-Import (siehe
-- Migration 039/041) neue Nachrichten eingetroffen sind — siehe
-- App\Domain\User\User::$notifyOnNewReceiptEmail,
-- App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler
-- (Versand am Ende eines Imports mit mind. einer neuen Nachricht an alle
-- aktiven Administratoren mit gesetztem Kontrollkästchen), Formularfeld
-- unter /profil/darstellung.
USE materialverleih;

ALTER TABLE users
    ADD COLUMN notify_on_new_receipt_email TINYINT(1) NOT NULL DEFAULT 0 AFTER locale;
