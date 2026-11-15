-- Migration 155: Netzwerke und IP-Adressen der Geräte (Nutzerwunsch
-- "dokumentieren, welche IP-Adressen unsere Geräte haben dürfen").
--
-- Einstellungen › Netzwerke: je Netzwerk IPv4 und/oder IPv6 (IPv4 only,
-- IPv6 only oder Dualstack), Netzwerkadresse und Präfixlänge (die
-- IPv4-Subnetzmaske wird daraus abgeleitet), Standard-Präfixlänge für IPv6
-- und optional die VLAN-ID. Erlaubt sind nur private Bereiche (RFC 1918
-- bzw. Unique Local Addresses fc00::/7) – das prüft die Anwendung.
--
-- Kategorien können einen festen Adressbereich innerhalb eines Netzwerks
-- bekommen (gilt auch für Unterkategorien ohne eigenen Bereich). Er dient
-- als Vorschlag bei der Adressvergabe und lässt sich dort überschreiben.
--
-- Artikel (außer Leitungen) können eine Netzwerkschnittstelle haben, die
-- ggf. zwingend einen DHCP-Server benötigt. Digitale Protokolle können
-- IP-basiert sein (z. B. Dante); dann bekommen die entsprechenden
-- Schnittstellen von Stageboxen, Mischpulten und deren Erweiterungskarten
-- je Inventarnummer ebenfalls eine IP-Adresse.

CREATE TABLE networks (
    id                   CHAR(36)          NOT NULL PRIMARY KEY,
    name                 VARCHAR(100)      NOT NULL,
    ip_mode              VARCHAR(10)       NOT NULL,            -- 'ipv4' | 'ipv6' | 'dual'
    vlan_id              SMALLINT UNSIGNED NULL,                -- 1–4094
    ipv4_network         VARCHAR(15)       NULL,                -- z. B. 192.168.10.0
    ipv4_prefix          TINYINT UNSIGNED  NULL,                -- z. B. 24 (= 255.255.255.0)
    ipv6_network         VARCHAR(39)       NULL,                -- z. B. fd00:10::
    ipv6_prefix          TINYINT UNSIGNED  NULL,                -- z. B. 48
    ipv6_default_prefix  TINYINT UNSIGNED  NULL,                -- Standard-Präfixlänge, z. B. 64
    notes                VARCHAR(255)      NULL,
    sort_order           INT               NOT NULL DEFAULT 0,
    created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_networks_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Fester Adressbereich je Kategorie (höchstens einer je Kategorie).
CREATE TABLE network_category_ranges (
    category_id   CHAR(36)     NOT NULL PRIMARY KEY,
    network_id    CHAR(36)     NOT NULL,
    ipv4_start    VARCHAR(15)  NULL,
    ipv4_end      VARCHAR(15)  NULL,
    ipv6_start    VARCHAR(39)  NULL,
    ipv6_end      VARCHAR(39)  NULL,
    CONSTRAINT fk_network_category_ranges_category
        FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT fk_network_category_ranges_network
        FOREIGN KEY (network_id) REFERENCES networks (id) ON DELETE CASCADE,
    INDEX idx_network_category_ranges_network (network_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Netzwerkschnittstelle eines Artikels (kein Eintrag = keine Schnittstelle).
CREATE TABLE article_network_settings (
    article_id             CHAR(36)    NOT NULL PRIMARY KEY,
    has_network_interface  TINYINT(1)  NOT NULL DEFAULT 0,
    requires_dhcp          TINYINT(1)  NOT NULL DEFAULT 0,      -- Gerät benötigt zwingend einen DHCP-Server
    CONSTRAINT fk_article_network_settings_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- IP-/MAC-Adressen je Inventarnummer und Schnittstelle. interface_key:
-- 'main' (Netzwerkschnittstelle des Artikels), 'stagebox' (IP-basiertes
-- Protokoll der digitalen Stagebox), 'console-if-<position>-<n>'
-- (Mischpult-Schnittstelle) bzw. 'console-card-<position>'
-- (Erweiterungskarte). label hält die Bezeichnung fest, falls die
-- Schnittstelle später am Artikel entfällt.
CREATE TABLE inventory_number_network_interfaces (
    inventory_number_id  CHAR(36)      NOT NULL,
    interface_key        VARCHAR(60)   NOT NULL,
    label                VARCHAR(150)  NOT NULL,
    network_id           CHAR(36)      NULL,
    ipv4_address         VARCHAR(15)   NULL,
    ipv6_address         VARCHAR(39)   NULL,
    mac_address          CHAR(17)      NULL,                    -- AA:BB:CC:DD:EE:FF
    PRIMARY KEY (inventory_number_id, interface_key),
    CONSTRAINT fk_inventory_number_network_interfaces_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_number_network_interfaces_network
        FOREIGN KEY (network_id) REFERENCES networks (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_inventory_number_network_interfaces_ipv4 (network_id, ipv4_address),
    UNIQUE KEY uq_inventory_number_network_interfaces_ipv6 (network_id, ipv6_address)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Digitale Protokolle: IP-basiert (z. B. Dante, AES67, SoundGrid)?
ALTER TABLE digital_protocols
    ADD COLUMN is_ip_based TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order;

UPDATE digital_protocols SET is_ip_based = 1
WHERE id IN ('d1000000-0000-0000-0000-000000000001', 'd1000000-0000-0000-0000-000000000005')
   OR name IN ('Dante', 'AES67', 'SoundGrid', 'WSG', 'Ravenna');
