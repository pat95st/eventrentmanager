-- Mahnwesen: frei konfigurierbare Mahnstufen (dunning_levels), eine
-- firmenweite Einstellung, ob Mahnungen automatisch verschickt werden
-- (dunning_settings, Singleton wie invoice_sequences), eine fortlaufende
-- Mahnungsnummer (dunning_sequences, analog invoice_sequences) sowie die
-- konkret erstellten Mahnungen selbst (dunning_notices), siehe
-- App\Domain\Dunning\* für die vollständige fachliche Begründung
-- (insbesondere das Snapshot-Prinzip: Name/Fristtage/Gebühr und der offene
-- Rechnungsbetrag werden zum Erstellungszeitpunkt vollständig auf die
-- Mahnung selbst kopiert).

CREATE TABLE dunning_levels (
    id             CHAR(36)       NOT NULL PRIMARY KEY,
    name           VARCHAR(255)   NOT NULL,
    days_after_due INT            NOT NULL,
    fee_type       ENUM('none', 'fixed', 'percent') NOT NULL DEFAULT 'none',
    fee_value      DECIMAL(10, 2) NULL,
    created_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dunning_levels_days_after_due (days_after_due)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Beispielhafte Standard-Mahnstufen, direkt nutzbar nach der Migration
-- (können in den Einstellungen jederzeit angepasst, gelöscht oder um
-- weitere Stufen ergänzt werden — siehe DunningLevelController).
INSERT INTO dunning_levels (id, name, days_after_due, fee_type, fee_value) VALUES
    (UUID(), 'Zahlungserinnerung', 7, 'none', NULL),
    (UUID(), '1. Mahnung', 14, 'fixed', 5.00),
    (UUID(), '2. Mahnung', 28, 'fixed', 10.00);

-- Singleton-Zeile (id fest 1), exakt analog invoice_sequences (siehe
-- 031_add_invoice_settings_and_sequence.sql): steuert
-- bin/send-dunning-notices.php (nur bei auto_send_enabled=1 werden
-- Mahnungen automatisch erstellt und per E-Mail verschickt).
CREATE TABLE dunning_settings (
    id                TINYINT    NOT NULL PRIMARY KEY DEFAULT 1,
    auto_send_enabled TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT chk_dunning_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO dunning_settings (id, auto_send_enabled) VALUES (1, 0);

-- Nummernvergabe für Mahnungen (z. B. "MAH-000123"), exakt analog
-- invoice_sequences — siehe App\Infrastructure\Persistence\Pdo\
-- DunningNumberSequence für die Race-Condition-sichere Vergabe per
-- SELECT ... FOR UPDATE.
CREATE TABLE dunning_sequences (
    id           TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    next_number  INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT chk_dunning_sequences_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO dunning_sequences (id, next_number) VALUES (1, 1);

CREATE TABLE dunning_notices (
    id                            CHAR(36)       NOT NULL PRIMARY KEY,
    invoice_id                    CHAR(36)       NOT NULL,
    dunning_number                VARCHAR(50)    NOT NULL,
    dunning_level_id              CHAR(36)       NULL,
    level_name_snapshot           VARCHAR(255)   NOT NULL,
    days_after_due_snapshot       INT            NOT NULL,
    fee_type_snapshot             ENUM('none', 'fixed', 'percent') NOT NULL,
    fee_value_snapshot            DECIMAL(10, 2) NULL,
    fee_amount_snapshot           DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    invoice_gross_total_snapshot  DECIMAL(10, 2) NOT NULL,
    created_at                    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at                       DATETIME       NULL,
    created_by                    ENUM('manual', 'automatic') NOT NULL DEFAULT 'manual',
    UNIQUE KEY uq_dunning_notices_dunning_number (dunning_number),
    CONSTRAINT fk_dunning_notices_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    -- ON DELETE SET NULL (nicht CASCADE/RESTRICT): eine gelöschte Mahnstufe
    -- darf eine bereits erstellte Mahnung nie mit löschen oder blockieren,
    -- da deren Inhalt vollständig auf die Mahnung selbst gesnapshotet ist
    -- (siehe DeleteDunningLevelHandler-Klassendoc).
    CONSTRAINT fk_dunning_notices_dunning_level
        FOREIGN KEY (dunning_level_id) REFERENCES dunning_levels (id) ON DELETE SET NULL,
    INDEX idx_dunning_notices_invoice (invoice_id),
    INDEX idx_dunning_notices_dunning_level (dunning_level_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Neue E-Mail-Vorlage "Mahnung" (siehe App\Domain\Mail\EmailTemplateKey::DunningNotice),
-- direkt mit demselben Standardtext vorbefüllt wie EmailTemplateKey::defaultSubject()/
-- defaultBodyHtml() (siehe 028_add_email_templates.sql für die identische
-- Begründung dieses Vorbefüllungs-Musters).
INSERT INTO email_templates (template_key, subject, body_html) VALUES
('dunning_notice', '{{dunning_level_name}}: Rechnung {{invoice_number}}',
 '<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie eine {{dunning_level_name}} zu unserer Rechnung {{invoice_number}}, die am {{due_date}} fällig war.</p>\n<p>Offener Gesamtbetrag: {{total_due}}</p>\n<p>Bitte begleichen Sie den Betrag umgehend. Sollte dies bereits geschehen sein, betrachten Sie diese E-Mail als gegenstandslos.</p>\n<p>Mit freundlichen Grüßen</p>');
