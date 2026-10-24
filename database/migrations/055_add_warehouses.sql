CREATE TABLE warehouses (
    id              CHAR(36)       NOT NULL PRIMARY KEY,
    name            VARCHAR(255)   NOT NULL,
    street          VARCHAR(255)   NOT NULL,
    postal_code     VARCHAR(20)    NOT NULL,
    city            VARCHAR(150)   NOT NULL,
    country         VARCHAR(100)   NULL,
    notes           TEXT           NULL,
    latitude        DECIMAL(10, 7) NULL,
    longitude       DECIMAL(10, 7) NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_warehouses_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Transport-/Logistik-Zuordnung einer Vermietung: Ausgabe und
-- Rücklieferung können jeweils einem Lagerort und einem eigenen Termin
-- zugeordnet werden. Sind pickup_warehouse_id und return_warehouse_id
-- identisch (oder ist nur eines der beiden Felder gesetzt), handelt es
-- sich fachlich um "ein Lager für Ausgabe und Rücklieferung" — der
-- Transport davor/danach wird dann in transport_note beschrieben.
-- Unterscheiden sie sich, handelt es sich um getrennte Termine an
-- unterschiedlichen Lagern. Alle Felder bleiben NULL-bar, da die
-- Logistik-Zuordnung optional ist (siehe App\Domain\Rental\Rental).
-- ---------------------------------------------------------------------
ALTER TABLE rentals
    ADD COLUMN pickup_warehouse_id CHAR(36) NULL AFTER venue_id,
    ADD COLUMN pickup_at           DATETIME NULL AFTER pickup_warehouse_id,
    ADD COLUMN return_warehouse_id CHAR(36) NULL AFTER pickup_at,
    ADD COLUMN return_at           DATETIME NULL AFTER return_warehouse_id,
    ADD COLUMN transport_note      TEXT     NULL AFTER return_at,
    ADD CONSTRAINT fk_rentals_pickup_warehouse
        FOREIGN KEY (pickup_warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_rentals_return_warehouse
        FOREIGN KEY (return_warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    ADD INDEX idx_rentals_pickup_warehouse (pickup_warehouse_id),
    ADD INDEX idx_rentals_return_warehouse (return_warehouse_id);
