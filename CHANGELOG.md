# Changelog

All notable user-visible changes to this project are documented in this file.

## Unreleased

- **New:** Login via LDAP / Active Directory or OAuth 2.0 / OpenID Connect. Administrators choose exactly one login method (local, LDAP or OAuth) under Settings > Login (LDAP/OAuth); external identities are matched to local users by e-mail address, with optional automatic creation of unknown users using default permissions. Administrators keep the local login at `/login/lokal` as emergency access. (#47)
