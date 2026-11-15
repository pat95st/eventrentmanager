-- Migration 161: onboarding tour shown after the first login.
--  - users.onboarding_tour_completed_at: when the user finished or skipped
--    the guided tour. NULL = not seen yet, the tour starts automatically on
--    the next page view. Existing users stay NULL on purpose, so everyone
--    sees the new tour once (it can be skipped with one click). The tour can
--    be started again any time under Profil › Darstellung.

ALTER TABLE users ADD COLUMN onboarding_tour_completed_at DATETIME NULL AFTER dashboard_layout;
