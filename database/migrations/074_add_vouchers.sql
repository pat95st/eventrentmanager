-- Migration für bereits bestehende Installationen: fügt die
-- Gutschein-Verwaltung hinzu (Tabelle "vouchers", Rabatt entweder als
-- Prozentsatz oder als fester Betrag, mit Gültigkeitszeitraum), die
-- Zuordnung eines Gutscheins zu genau einer Vermietung ("Einlösung",
-- Tabelle "voucher_redemptions") sowie die admin-editierbare
-- Standard-Gültigkeitsdauer neu angelegter Gutscheine (Tabelle
-- "voucher_settings", Singleton-Zeile analog zu pricing_settings).
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/074_add_vouchers.sql

USE materialverleih;

-- ---------------------------------------------------------------------
-- Gutschein-Stammdaten. discount_value wird je nach discount_type als
-- Prozentsatz (0-100) oder als Betrag in EUR interpretiert (die Anwendung
-- erzwingt das, nicht die Datenbank, analog zu category bei vehicles).
-- valid_from/valid_until sind bewusst DATE (nicht DATETIME), da die
-- Gültigkeit taggenau geprüft wird (siehe Voucher::isValidNow()).
-- ---------------------------------------------------------------------
CREATE TABLE vouchers (
    id              CHAR(36)        NOT NULL PRIMARY KEY,
    code            VARCHAR(40)     NOT NULL,
    discount_type   ENUM('prozent', 'betrag') NOT NULL,
    discount_value  DECIMAL(10, 2)  NOT NULL,
    valid_from      DATE            NOT NULL,
    valid_until     DATE            NOT NULL,
    notes           TEXT            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vouchers_code (code),
    INDEX idx_vouchers_valid_until (valid_until)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Einlösung: ordnet einen Gutschein genau einer Vermietung zu. UNIQUE auf
-- BEIDEN Spalten, da ein Gutschein höchstens einmal eingelöst werden kann
-- und eine Vermietung höchstens einen Gutschein trägt. "Einlösung
-- aufheben" löscht die Zeile wieder (statt sie als storniert zu markieren),
-- damit derselbe Gutschein danach erneut eingelöst werden kann — analog
-- zu rental_vehicle_loads. ON DELETE CASCADE auf beiden Fremdschlüsseln,
-- damit weder ein gelöschter Gutschein noch eine gelöschte Vermietung eine
-- verwaiste Einlösungszeile zurücklässt.
-- ---------------------------------------------------------------------
CREATE TABLE voucher_redemptions (
    id              CHAR(36)   NOT NULL PRIMARY KEY,
    voucher_id      CHAR(36)   NOT NULL,
    rental_id       CHAR(36)   NOT NULL,
    redeemed_at     DATETIME   NOT NULL,
    CONSTRAINT fk_voucher_redemptions_voucher
        FOREIGN KEY (voucher_id) REFERENCES vouchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_voucher_redemptions_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    UNIQUE KEY uq_voucher_redemptions_voucher (voucher_id),
    UNIQUE KEY uq_voucher_redemptions_rental (rental_id),
    INDEX idx_voucher_redemptions_rental (rental_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Singleton-Zeile (id = 1) für die Standard-Gültigkeitsdauer neu
-- angelegter Gutscheine in Tagen, admin-editierbar unter
-- /einstellungen/gutscheine (siehe VoucherSettingsController).
-- ---------------------------------------------------------------------
CREATE TABLE voucher_settings (
    id                      TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    default_validity_days  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO voucher_settings (id, default_validity_days) VALUES (1, 30);
