-- Migration 171: login through LDAP / Active Directory or through
-- OAuth 2.0 / OpenID Connect as an alternative to the local login
-- (singleton row, Einstellungen › Anmeldung).
--  * login_method: the one active method. 'local' (default) is the login
--    with a password stored here and passkeys. With 'ldap' or 'oauth' the
--    local login only remains as emergency access for administrators.
-- An external identity is mapped to a local user by its e-mail address.
--  * ldap_user_filter: "%s" is replaced by the escaped login name.
--  * oauth_issuer_url: optional, only used to discover the three endpoints.
--  * oauth_skip_two_factor: the identity provider already asks for a second
--    factor, so the local one is not requested again after an OAuth login.
--  * auto_create_users / default_permissions: create unknown users on their
--    first external login with these rights (comma separated, like
--    users.permissions; never "administration").

CREATE TABLE external_login_settings (
    id                        TINYINT           NOT NULL PRIMARY KEY DEFAULT 1,
    login_method              ENUM('local', 'ldap', 'oauth') NOT NULL DEFAULT 'local',
    ldap_host                 VARCHAR(255)      NULL,
    ldap_port                 SMALLINT UNSIGNED NOT NULL DEFAULT 389,
    ldap_encryption           ENUM('none', 'starttls', 'ldaps') NOT NULL DEFAULT 'none',
    ldap_bind_dn              VARCHAR(255)      NULL,
    ldap_bind_password        VARCHAR(255)      NULL,
    ldap_base_dn              VARCHAR(255)      NULL,
    ldap_user_filter          VARCHAR(500)      NOT NULL DEFAULT '(|(uid=%s)(mail=%s))',
    ldap_email_attribute      VARCHAR(100)      NOT NULL DEFAULT 'mail',
    ldap_first_name_attribute VARCHAR(100)      NOT NULL DEFAULT 'givenName',
    ldap_last_name_attribute  VARCHAR(100)      NOT NULL DEFAULT 'sn',
    oauth_label               VARCHAR(100)      NOT NULL DEFAULT 'Single Sign-On',
    oauth_client_id           VARCHAR(255)      NULL,
    oauth_client_secret       TEXT              NULL,
    oauth_issuer_url          VARCHAR(500)      NULL,
    oauth_authorization_url   VARCHAR(500)      NULL,
    oauth_token_url           VARCHAR(500)      NULL,
    oauth_userinfo_url        VARCHAR(500)      NULL,
    oauth_scopes              VARCHAR(255)      NOT NULL DEFAULT 'openid email profile',
    oauth_email_claim         VARCHAR(100)      NOT NULL DEFAULT 'email',
    oauth_first_name_claim    VARCHAR(100)      NOT NULL DEFAULT 'given_name',
    oauth_last_name_claim     VARCHAR(100)      NOT NULL DEFAULT 'family_name',
    oauth_skip_two_factor     TINYINT(1)        NOT NULL DEFAULT 0,
    auto_create_users         TINYINT(1)        NOT NULL DEFAULT 0,
    default_permissions       VARCHAR(255)      NOT NULL DEFAULT '',
    CONSTRAINT chk_external_login_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO external_login_settings (id) VALUES (1);
