-- Migration für bereits bestehende Installationen: Verbindungseinstellungen
-- für den E-Mail-Posteingang, aus dem eingehende Belege (Lieferanten-
-- rechnungen/-quittungen als E-Mail-Anhang) automatisch per Cronjob
-- eingesammelt werden (siehe bin/import-receipt-emails.php,
-- App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler).
--
-- Singleton-Tabelle (id = 1) analog zu smtp_settings — das Passwort wird
-- hier bewusst GENAU WIE bei smtp_settings im Klartext gespeichert: die
-- Tabelle ist ohnehin nur für Administratoren über die Anwendung
-- erreichbar (siehe database/migrations/008_add_users_and_auth.sql,
-- Kommentar bei smtp_settings.password), ein eigenes Verschlüsselungs-
-- schema nur für dieses eine Passwort wäre hier kein echter Sicherheits-
-- gewinn, da der Schlüssel dafür ohnehin wieder in der Anwendung selbst
-- läge.
--
-- enabled=0 (Standard) sorgt dafür, dass der Cronjob sofort und sauber
-- abbricht, ohne einen Verbindungsversuch zu unternehmen, solange der
-- Posteingang nicht bewusst vom Administrator aktiviert wurde.
USE materialverleih;

CREATE TABLE mailbox_settings (
    id            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    protocol      ENUM('imap', 'pop3') NOT NULL DEFAULT 'imap',
    host          VARCHAR(255) NULL,
    port          SMALLINT UNSIGNED NULL,
    encryption    ENUM('none', 'ssl', 'starttls') NOT NULL DEFAULT 'ssl',
    username      VARCHAR(255) NULL,
    password      VARCHAR(255) NULL,
    -- Nur für protocol = 'imap' relevant, bei POP3 ignoriert (POP3 kennt
    -- keine Ordnerstruktur).
    imap_folder   VARCHAR(255) NOT NULL DEFAULT 'INBOX',
    enabled       TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_mailbox_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO mailbox_settings (id, protocol, host, port, encryption, username, password, imap_folder, enabled)
VALUES (1, 'imap', NULL, NULL, 'ssl', NULL, NULL, 'INBOX', 0);
