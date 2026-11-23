-- Driver's logbook, part 1: private vehicles and per-vehicle logbook settings.
--
--  - vehicles.is_private / owner_user_id: private vehicle of a user (e.g. a
--    technician's own car). The owner is required while is_private = 1; if
--    the user is deleted the vehicle keeps the flag without an owner.
--  - vehicles.logbook_qr_enabled: trips can be recorded through the QR code
--    of the vehicle.
--  - vehicles.logbook_anonymous_allowed: the QR code also works without a
--    login (external drivers enter their name).
--  - vehicles.logbook_anonymous_private_allowed: external drivers may record
--    private trips.
--  - vehicles.logbook_anonymous_requires_approval: trips of external drivers
--    only count for billing after a logbook manager approved them.
--  - vehicles.logbook_odometer_check: the current odometer reading has to be
--    entered correctly (last recorded reading +/- logbook_odometer_tolerance_km)
--    before a trip can be recorded. logbook_odometer_initial_km is the
--    reading the chain starts with.
ALTER TABLE vehicles
    ADD COLUMN is_private                          TINYINT(1)   NOT NULL DEFAULT 0 AFTER cost_per_km,
    ADD COLUMN owner_user_id                       CHAR(36)     NULL AFTER is_private,
    ADD COLUMN logbook_qr_enabled                  TINYINT(1)   NOT NULL DEFAULT 0 AFTER owner_user_id,
    ADD COLUMN logbook_anonymous_allowed           TINYINT(1)   NOT NULL DEFAULT 0 AFTER logbook_qr_enabled,
    ADD COLUMN logbook_anonymous_private_allowed   TINYINT(1)   NOT NULL DEFAULT 0 AFTER logbook_anonymous_allowed,
    ADD COLUMN logbook_anonymous_requires_approval TINYINT(1)   NOT NULL DEFAULT 0 AFTER logbook_anonymous_private_allowed,
    ADD COLUMN logbook_odometer_check              TINYINT(1)   NOT NULL DEFAULT 0 AFTER logbook_anonymous_requires_approval,
    ADD COLUMN logbook_odometer_tolerance_km       INT UNSIGNED NOT NULL DEFAULT 0 AFTER logbook_odometer_check,
    ADD COLUMN logbook_odometer_initial_km         INT UNSIGNED NULL AFTER logbook_odometer_tolerance_km,
    ADD CONSTRAINT fk_vehicles_owner_user FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL;
