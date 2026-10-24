-- Migration für bereits bestehende Installationen: Angebote (Kostenvoranschläge)
-- als eigene, von Vermietungen (rentals) unabhängige Entität, siehe
-- App\Domain\Offer\Offer und App\Domain\Offer\OfferItem. Ein Angebot kann
-- über ConvertOfferToRentalHandler in eine Veranstaltung (rentals)
-- umgewandelt werden (converted_rental_id).
USE materialverleih;

CREATE TABLE offers (
    id                    CHAR(36)      NOT NULL PRIMARY KEY,
    -- Bewusst nullable: ein Angebot kann als grober Entwurf ohne
    -- endgültige Kundenzuordnung begonnen werden (anders als bei rentals).
    customer_id           CHAR(36)      NULL,
    event_location        VARCHAR(255)  NULL,
    venue_id              CHAR(36)      NULL,
    start_date            DATE          NOT NULL,
    end_date              DATE          NOT NULL,
    valid_until           DATE          NULL,
    status                VARCHAR(20)   NOT NULL DEFAULT 'draft',
    note                  TEXT          NULL,
    -- Gesetzt, sobald das Angebot per ConvertOfferToRentalHandler in eine
    -- Veranstaltung umgewandelt wurde; das Angebot bleibt danach unverändert
    -- bestehen (kein Löschen), dient nur noch als Nachweis/Verweis.
    converted_rental_id   CHAR(36)      NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_offers_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_offers_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    CONSTRAINT fk_offers_converted_rental
        FOREIGN KEY (converted_rental_id) REFERENCES rentals (id) ON DELETE SET NULL,
    INDEX idx_offers_customer (customer_id),
    INDEX idx_offers_venue (venue_id),
    INDEX idx_offers_status (status),
    INDEX idx_offers_dates (start_date, end_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Positionen eines Angebots. unit_price_per_day ist trotz des Namens der
-- (ggf. manuell überschriebene) END-Preis je Einheit für den gesamten
-- Angebotszeitraum, vorbefüllt per RentalPriceCalculator, siehe
-- App\Domain\Offer\OfferItem. Anders als bei rental_items KEINE
-- UNIQUE-Sperre auf (offer_id, article_id): Positionen werden beim
-- Speichern des Angebotsformulars als Ganzes ersetzt (siehe
-- MariadbOfferRepository::replaceItems()), ein Artikel könnte theoretisch
-- mit unterschiedlichen line_note-Werten mehrfach vorkommen.
-- ---------------------------------------------------------------------
CREATE TABLE offer_items (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    offer_id            CHAR(36)      NOT NULL,
    article_id          CHAR(36)      NOT NULL,
    quantity            INT UNSIGNED  NOT NULL,
    unit_price_per_day  DECIMAL(10, 2) NOT NULL,
    line_note           VARCHAR(255)  NULL,
    CONSTRAINT fk_offer_items_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    CONSTRAINT fk_offer_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_offer_items_offer (offer_id),
    INDEX idx_offer_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
