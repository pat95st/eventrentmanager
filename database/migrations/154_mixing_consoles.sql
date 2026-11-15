-- Migration 154: Tonmischpulte als Spezialartikel (Nutzerwunsch, analog zu
-- digitalen Stageboxen). Ein Tonmischpult ist analog oder digital.
--
-- Analog: Eingangskanäle mit Mikrofonvorverstärker, Stereo-Eingänge,
-- Kombi-Eingänge, AUX-Wege (Pre, Post, umschaltbar), Subgruppen, Mono-Master.
--
-- Digital: digitale Schnittstellen (Protokoll aus Einstellungen › Digitale
-- Protokolle, Anzahl), DSP-Kanäle (Kanalart, Anzahl, Mono/Stereo,
-- Herstellerbezeichnung), lokale analoge Ein-/Ausgänge, Erweiterungsslots
-- mit passenden Erweiterungskarten (Protokoll, Ein-/Ausgänge) sowie
-- kompatible digitale Stageboxen: "native" (nativ kompatibel) oder
-- "limited" (gleiches Protokoll, aber z. B. anderer Hersteller →
-- kompatibel mit Einschränkungen).
--
-- Tonmischpulte haben immer die Kategorie "Tonmischpult"
-- (categories.is_mixing_console, umbenennbar, nicht löschbar).

ALTER TABLE categories
    ADD COLUMN is_mixing_console TINYINT(1) NOT NULL DEFAULT 0 AFTER is_digital_stagebox;

UPDATE categories SET is_mixing_console = 1
WHERE name IN ('Tonmischpult', 'Tonmischpulte', 'Mischpult', 'Mischpulte')
  AND is_power_distributor = 0 AND is_cable = 0 AND is_digital_stagebox = 0
ORDER BY (parent_id IS NOT NULL), created_at
LIMIT 1;

INSERT INTO categories (id, name, parent_id, is_mixing_console)
SELECT UUID(), 'Tonmischpult', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE is_mixing_console = 1);

CREATE TABLE article_mixing_consoles (
    article_id           CHAR(36)          NOT NULL PRIMARY KEY,
    console_type         VARCHAR(10)       NOT NULL,             -- 'analog' | 'digital'
    -- analog
    mic_inputs           SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- Eingangskanäle mit Mikrofonvorverstärker
    stereo_inputs        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    combo_inputs         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    aux_pre              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    aux_post             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    aux_switchable       SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- Pre/Post umschaltbar
    subgroups            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    has_mono_master      TINYINT(1)        NOT NULL DEFAULT 0,
    -- digital
    local_inputs         SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- lokale analoge Eingänge
    local_outputs        SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- lokale analoge Ausgänge
    expansion_slots      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    expansion_slot_type  VARCHAR(100)      NULL,                 -- z. B. "MY-Slot"
    CONSTRAINT fk_article_mixing_consoles_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_mixing_console_interfaces (
    article_id   CHAR(36)          NOT NULL,
    position     SMALLINT UNSIGNED NOT NULL,
    protocol_id  CHAR(36)          NOT NULL,
    quantity     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (article_id, position),
    CONSTRAINT fk_mixing_console_interfaces_console
        FOREIGN KEY (article_id) REFERENCES article_mixing_consoles (article_id) ON DELETE CASCADE,
    CONSTRAINT fk_mixing_console_interfaces_protocol
        FOREIGN KEY (protocol_id) REFERENCES digital_protocols (id) ON DELETE RESTRICT,
    INDEX idx_mixing_console_interfaces_protocol (protocol_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_mixing_console_channels (
    article_id          CHAR(36)          NOT NULL,
    position            SMALLINT UNSIGNED NOT NULL,
    channel_kind        VARCHAR(20)       NOT NULL,  -- input | aux | group | matrix | fx_return | main | other
    quantity            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_stereo           TINYINT(1)        NOT NULL DEFAULT 0,
    manufacturer_label  VARCHAR(100)      NULL,      -- Herstellerbezeichnung, z. B. "Mix Bus"
    PRIMARY KEY (article_id, position),
    CONSTRAINT fk_mixing_console_channels_console
        FOREIGN KEY (article_id) REFERENCES article_mixing_consoles (article_id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_mixing_console_cards (
    article_id   CHAR(36)          NOT NULL,
    position     SMALLINT UNSIGNED NOT NULL,
    name         VARCHAR(150)      NOT NULL,
    protocol_id  CHAR(36)          NULL,                 -- NULL = analoge Karte
    inputs       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    outputs      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    notes        VARCHAR(255)      NULL,
    PRIMARY KEY (article_id, position),
    CONSTRAINT fk_mixing_console_cards_console
        FOREIGN KEY (article_id) REFERENCES article_mixing_consoles (article_id) ON DELETE CASCADE,
    CONSTRAINT fk_mixing_console_cards_protocol
        FOREIGN KEY (protocol_id) REFERENCES digital_protocols (id) ON DELETE RESTRICT,
    INDEX idx_mixing_console_cards_protocol (protocol_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_mixing_console_stageboxes (
    console_article_id   CHAR(36)     NOT NULL,
    stagebox_article_id  CHAR(36)     NOT NULL,
    compatibility        VARCHAR(10)  NOT NULL,  -- 'native' | 'limited'
    PRIMARY KEY (console_article_id, stagebox_article_id),
    CONSTRAINT fk_mixing_console_stageboxes_console
        FOREIGN KEY (console_article_id) REFERENCES article_mixing_consoles (article_id) ON DELETE CASCADE,
    CONSTRAINT fk_mixing_console_stageboxes_stagebox
        FOREIGN KEY (stagebox_article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_mixing_console_stageboxes_stagebox (stagebox_article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Weitere typische Pult-Schnittstellen (kann in den Einstellungen geändert werden)
INSERT IGNORE INTO digital_protocols (id, name, sort_order) VALUES
    ('d1000000-0000-0000-0000-000000000006', 'AES/EBU', 60),
    ('d1000000-0000-0000-0000-000000000007', 'ADAT', 70),
    ('d1000000-0000-0000-0000-000000000008', 'USB-Audio', 80);
