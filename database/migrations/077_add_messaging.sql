-- Migration für bereits bestehende Installationen: Kunden-Benachrichtigungen
-- und -Erinnerungen über WhatsApp Business (Meta Cloud API), Telegram
-- (Bot-API) und Signal (signal-cli-rest-api), siehe App\Domain\Messaging,
-- App\Infrastructure\Messaging\HttpMessageSender.
--
-- messaging_settings ist eine Singleton-Tabelle (id = 1) analog zu
-- mailbox_settings/smtp_settings/push_settings — EINE gemeinsame
-- Zugangsdaten-Zeile für alle drei Kanäle, da es ohnehin nur eine
-- Einstellungsseite dafür gibt (/einstellungen/messenger). Alle
-- Zugangsdaten (*_access_token, *_bot_token) werden im Klartext
-- gespeichert, genau wie das SMTP-/Mailbox-Passwort — nur für
-- Administratoren über die Anwendung erreichbar.
--
-- WICHTIG: whatsapp_access_token/whatsapp_phone_number_id sind eine EIGENE
-- Meta-Verbindung für den Nachrichtenversand, UNABHÄNGIG von der bereits
-- bestehenden whatsapp_business_settings-Tabelle (Migration 060), die
-- ausschließlich den Produktkatalog für die Artikel-Veröffentlichung
-- steuert — bewusst nicht wiederverwendet/zusammengelegt, siehe
-- App\Domain\Messaging\MessagingSettings-Klassenkommentar.
--
-- enabled=0 (Standard) je Kanal sorgt dafür, dass weder der Cronjob
-- (bin/send-rental-reminders.php) noch die "Nachricht senden"-Aktion auf
-- der Kundenseite versucht, über einen noch nicht bewusst aktivierten
-- Kanal zu verschicken.
USE materialverleih;

CREATE TABLE messaging_settings (
    id                            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,

    whatsapp_enabled              TINYINT(1)   NOT NULL DEFAULT 0,
    whatsapp_phone_number_id      VARCHAR(64)  NULL,
    whatsapp_access_token         VARCHAR(512) NULL,
    whatsapp_business_account_id  VARCHAR(64)  NULL,

    telegram_enabled              TINYINT(1)   NOT NULL DEFAULT 0,
    telegram_bot_token            VARCHAR(255) NULL,

    signal_enabled                TINYINT(1)   NOT NULL DEFAULT 0,
    signal_api_base_url           VARCHAR(255) NULL,
    signal_sender_number          VARCHAR(50)  NULL,

    updated_at                    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_messaging_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO messaging_settings (id) VALUES (1);

-- Admin-bearbeitbare Vorlagen je (Kanal, Schlüssel) — siehe
-- App\Domain\Messaging\MessageTemplateKey für die genaue Bedeutung von
-- body_text (Telegram/Signal, Klartext mit {{platzhalter}}) vs.
-- meta_template_name/meta_template_language (WhatsApp, Referenz auf eine
-- bei Meta genehmigte Vorlage). Fehlt eine Zeile, gelten die eingebauten
-- Standardwerte (siehe MariadbMessageTemplateRepository::get()).
CREATE TABLE message_templates (
    id                       CHAR(36)     NOT NULL PRIMARY KEY,
    channel                  VARCHAR(20)  NOT NULL,
    template_key             VARCHAR(50)  NOT NULL,
    body_text                TEXT         NULL,
    meta_template_name       VARCHAR(255) NULL,
    meta_template_language   VARCHAR(10)  NULL DEFAULT 'de',
    created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_message_templates_channel_key (channel, template_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Telegram-Chat-ID des Kunden (siehe App\Domain\Customer\Customer::$telegramChatId)
-- — WhatsApp/Signal nutzen dagegen die bereits bestehende Spalte "phone".
ALTER TABLE customers
    ADD COLUMN telegram_chat_id VARCHAR(64) NULL AFTER email;
