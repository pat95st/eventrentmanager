-- Automatische Erinnerungs-E-Mails an Kunden vor Abholung bzw. Rückgabe
-- einer Vermietung (siehe App\Domain\Rental\Rental::$pickupAt/$returnAt und
-- bin/send-rental-reminders.php, das stündlich per Cronjob laufen soll).
--
-- rental_reminder_settings: Singleton-Zeile (analog invoice_settings/
-- dunning_settings) mit dem Vorlauf in STUNDEN je Erinnerungsart — bewusst
-- in Stunden statt Tagen, da pickup_at/return_at echte Datum+Uhrzeit-Werte
-- sind und der Cronjob stündlich läuft (siehe Klassen-Doc
-- App\Domain\Settings\RentalReminderSettings).
--
-- rental_reminders: EIN Log-Eintrag pro tatsächlich verschickter Erinnerung
-- (rental_id + type), verhindert per UNIQUE-Constraint doppelten Versand bei
-- mehrfachem Cronjob-Lauf innerhalb des Vorlauf-Zeitfensters — exakt
-- dasselbe Prinzip wie dunning_notices' Existenzprüfung
-- (existsForInvoiceAndLevel) in bin/send-dunning-notices.php.

CREATE TABLE rental_reminder_settings (
    id                 TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    pickup_lead_hours  INT UNSIGNED NOT NULL DEFAULT 24,
    return_lead_hours  INT UNSIGNED NOT NULL DEFAULT 24,
    CONSTRAINT chk_rental_reminder_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO rental_reminder_settings (id, pickup_lead_hours, return_lead_hours) VALUES (1, 24, 24);

CREATE TABLE rental_reminders (
    id         CHAR(36)                    NOT NULL PRIMARY KEY,
    rental_id  CHAR(36)                    NOT NULL,
    type       ENUM('pickup', 'return')    NOT NULL,
    sent_at    DATETIME                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_reminders_rental_type (rental_id, type),
    CONSTRAINT fk_rental_reminders_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    INDEX idx_rental_reminders_rental (rental_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
