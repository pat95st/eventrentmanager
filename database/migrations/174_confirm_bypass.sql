-- Migration 174: skipping confirmations by holding Shift becomes configurable.
--  * security_settings.confirm_bypass_enabled: company-wide switch
--    (Einstellungen › Sicherheit). Default on, the behaviour so far.
--  * security_settings.confirm_overrides: JSON object "confirmation key =>
--    required (true/false)" with the deviations from the defaults in
--    App\Domain\Settings\ConfirmCatalog. NULL = all defaults.
--  * users.confirm_bypass_enabled: a user can switch the shortcut off for
--    themselves (Profil › Darstellung); only effective while the company-wide
--    switch is on.

ALTER TABLE security_settings
    ADD COLUMN confirm_bypass_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER session_timeout_minutes,
    ADD COLUMN confirm_overrides      TEXT       NULL AFTER confirm_bypass_enabled;

ALTER TABLE users ADD COLUMN confirm_bypass_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER onboarding_tour_completed_at;
