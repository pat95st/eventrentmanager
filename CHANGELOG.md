# Changelog

All notable user-visible changes to this project are documented in this file.

## Unreleased

- **New:** Login via LDAP / Active Directory or OAuth 2.0 / OpenID Connect. Administrators choose exactly one login method (local, LDAP or OAuth) under Settings > Login (LDAP/OAuth); external identities are matched to local users by e-mail address, with optional automatic creation of unknown users using default permissions. Administrators keep the local login at `/login/lokal` as emergency access. (#47)
- **New:** Per-trade notes in minute programs. Each program item now has one note per trade in addition to the general note, replacing the fixed sound/light/video/stage columns; the trades of a program are chosen in the program form or derived automatically from the event and its technicians. The FOH live screen shows every note as a colour-coded card per trade with filter chips, and the run sheet API v1 gains `trades` and `tradeNotes` (the legacy `notes` keys are still filled). Existing notes are migrated to the trades "Ton", "Licht", "Video" and "Bühne". (#48)
