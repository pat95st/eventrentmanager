-- Automatische Erinnerung an Kunden vor Fälligkeit einer versendeten,
-- noch offenen Rechnung — AUSSCHLIESSLICH per Messenger (WhatsApp Business/
-- Telegram/Signal, siehe App\Domain\Messaging), da es dafür (anders als bei
-- Rechnungsversand/Mahnung) keine parallele E-Mail-Erinnerung gibt. Siehe
-- bin/send-invoice-due-reminders.php (empfohlen: täglich per Cronjob).
--
-- invoice_due_reminder_settings: Singleton-Zeile (analog rental_reminder_
-- settings/dunning_settings) mit dem Vorlauf in TAGEN (bewusst in Tagen,
-- nicht Stunden wie bei rental_reminder_settings — Invoice::$dueDate ist ein
-- reines Datum ohne Uhrzeit, siehe Invoice-Klassendoc) sowie einem eigenen
-- Aktiv-Schalter (Standard: deaktiviert, analog messaging_settings'
-- *_enabled-Spalten — ein Betrieb muss diesen zusätzlichen automatischen
-- Kundenkontakt erst bewusst einschalten).
--
-- invoice_due_reminders: EIN Log-Eintrag pro tatsächlich verschickter
-- Erinnerung (invoice_id), verhindert per UNIQUE-Constraint doppelten
-- Versand bei mehrfachem Cronjob-Lauf innerhalb des Vorlauf-Zeitfensters —
-- exakt dasselbe Prinzip wie rental_reminders (Migration 067).
USE materialverleih;

CREATE TABLE invoice_due_reminder_settings (
    id         TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    enabled    TINYINT(1)   NOT NULL DEFAULT 0,
    lead_days  INT UNSIGNED NOT NULL DEFAULT 3,
    CONSTRAINT chk_invoice_due_reminder_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO invoice_due_reminder_settings (id, enabled, lead_days) VALUES (1, 0, 3);

CREATE TABLE invoice_due_reminders (
    id         CHAR(36)  NOT NULL PRIMARY KEY,
    invoice_id CHAR(36)  NOT NULL,
    sent_at    DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_due_reminders_invoice (invoice_id),
    CONSTRAINT fk_invoice_due_reminders_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
