-- Migration für bereits bestehende Installationen: echte Browser-Push-
-- Benachrichtigungen (Web Push, RFC 8291/8292/8188), ZUSÄTZLICH zur bereits
-- bestehenden E-Mail-Benachrichtigung, wenn der Beleg-Posteingang-Cronjob
-- neue Nachrichten eingesammelt hat (siehe
-- App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler,
-- User::$notifyOnNewReceiptEmail — dasselbe Opt-in-Feld steuert BEIDE
-- Zustellarten, es gibt bewusst kein separates Push-spezifisches Häkchen).
--
-- push_settings ist eine Singleton-Tabelle (id = 1) analog zu
-- mailbox_settings/smtp_settings: EIN VAPID-Schlüsselpaar (EC/P-256) für die
-- GESAMTE Anwendung, nicht pro Benutzer — identifiziert den sendenden
-- Server gegenüber dem jeweiligen Push-Dienst (z. B. FCM/Mozilla Autopush),
-- OHNE dass sich die Anwendung dort registrieren muss. Wird beim ersten
-- Aufruf von /einstellungen/push automatisch per ext-openssl erzeugt (siehe
-- App\Application\Settings\Command\EnsureVapidKeysGeneratedHandler) und
-- bleibt danach STABIL — ein späterer Schlüsselwechsel würde alle
-- bestehenden Browser-Subscriptions (push_subscriptions) ungültig machen.
--
-- vapid_private_key_pem wird bewusst im Klartext gespeichert (genau wie das
-- SMTP-/Mailbox-Passwort, siehe dortige Migrationen) — nur für
-- Administratoren über die Anwendung erreichbar.
USE materialverleih;

CREATE TABLE push_settings (
    id                     TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    vapid_public_key       VARCHAR(255) NULL,
    vapid_private_key_pem  TEXT         NULL,
    subject_email          VARCHAR(255) NULL,
    updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_push_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO push_settings (id, vapid_public_key, vapid_private_key_pem, subject_email)
VALUES (1, NULL, NULL, NULL);

-- Ein Benutzer kann mehrere Push-Subscriptions besitzen (mehrere
-- Geräte/Browser gleichzeitig abonniert). endpoint ist die vom Push-Dienst
-- vergebene, pro Geräte-Registrierung eindeutige URL (siehe
-- pushManager.subscribe() im Browser) — global eindeutig, daher UNIQUE statt
-- nur je Benutzer, damit ein erneutes Abonnieren desselben Geräts (z. B.
-- nach Cache-Löschung) den bestehenden Datensatz aktualisiert statt einen
-- Duplikat-Fehler zu werfen (siehe MariadbPushSubscriptionRepository::save(),
-- INSERT ... ON DUPLICATE KEY UPDATE).
CREATE TABLE push_subscriptions (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    user_id      CHAR(36)     NOT NULL,
    endpoint     VARCHAR(500) NOT NULL,
    p256dh       VARCHAR(255) NOT NULL,
    auth         VARCHAR(255) NOT NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   DATETIME     NOT NULL,
    CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uq_push_subscriptions_endpoint UNIQUE (endpoint)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_push_subscriptions_user_id ON push_subscriptions (user_id);
