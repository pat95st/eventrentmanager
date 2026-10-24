-- Migration für bereits bestehende Installationen: optionale Reply-To-Adresse
-- sowie optionale Archivierungsadresse für ausgehende Mails (siehe
-- App\Infrastructure\Mail\SmtpMailer). Ist die Archivierungsadresse gesetzt,
-- erhält sie bei JEDER über die Anwendung versendeten Mail (2FA-Codes,
-- Packlisten-/Angebots-PDF-Versand, Test-Mail, Passwort-Einrichtungslinks
-- usw.) automatisch eine BCC-Kopie.
USE materialverleih;

ALTER TABLE smtp_settings
    ADD COLUMN reply_to_address VARCHAR(255) NULL AFTER from_name,
    ADD COLUMN archive_bcc_address VARCHAR(255) NULL AFTER reply_to_address;
