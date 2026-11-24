-- OAuth 2.1 authorization server for the MCP server /mcp, so clients such as
-- claude.ai / Claude Desktop can connect with the user's normal login.
-- Dynamic client registration (RFC 7591), authorization code + PKCE (S256),
-- rotating refresh tokens. Access tokens are rows of api_tokens linked to a
-- grant (connection of one user with one client); they are only valid for /mcp.
-- See App\Application\OAuth\OAuthService and docs/mcp.md.

CREATE TABLE oauth_clients (
    id                         CHAR(36)      NOT NULL PRIMARY KEY,
    name                       VARCHAR(255)  NOT NULL,
    redirect_uris              TEXT          NOT NULL,
    client_secret_hash         CHAR(64)      NULL,
    token_endpoint_auth_method VARCHAR(30)   NOT NULL DEFAULT 'none',
    created_at                 DATETIME      NOT NULL,
    last_used_at               DATETIME      NULL,
    KEY idx_oauth_clients_created (created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- One row per connection of a user with a client ("Verbundene KI-Assistenten").
-- Only the hash of the current refresh token is stored; the previous one is
-- kept to detect reuse of a rotated token (then the whole grant is revoked).
CREATE TABLE oauth_grants (
    id                          CHAR(36)     NOT NULL PRIMARY KEY,
    client_id                   CHAR(36)     NOT NULL,
    user_id                     CHAR(36)     NOT NULL,
    scopes                      TEXT         NOT NULL,
    refresh_token_hash          CHAR(64)     NOT NULL,
    previous_refresh_token_hash CHAR(64)     NULL,
    refresh_expires_at          DATETIME     NOT NULL,
    created_at                  DATETIME     NOT NULL,
    last_used_at                DATETIME     NULL,
    CONSTRAINT uniq_oauth_grants_refresh UNIQUE (refresh_token_hash),
    KEY idx_oauth_grants_previous (previous_refresh_token_hash),
    KEY idx_oauth_grants_user (user_id),
    CONSTRAINT fk_oauth_grants_client FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_oauth_grants_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Single-use authorization codes (10 minutes), stored as hash.
CREATE TABLE oauth_authorization_codes (
    code_hash      CHAR(64)      NOT NULL PRIMARY KEY,
    client_id      CHAR(36)      NOT NULL,
    user_id        CHAR(36)      NOT NULL,
    redirect_uri   VARCHAR(2000) NOT NULL,
    code_challenge VARCHAR(128)  NOT NULL,
    scopes         TEXT          NOT NULL,
    resource       VARCHAR(500)  NULL,
    expires_at     DATETIME      NOT NULL,
    created_at     DATETIME      NOT NULL,
    KEY idx_oauth_codes_expires (expires_at),
    CONSTRAINT fk_oauth_codes_client FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_oauth_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Access tokens issued via OAuth: owner, grant and expiry (NULL for the
-- tokens created under Settings › API, which do not expire).
ALTER TABLE api_tokens
    ADD COLUMN user_id        CHAR(36) NULL AFTER mcp_allowed,
    ADD COLUMN oauth_grant_id CHAR(36) NULL AFTER user_id,
    ADD COLUMN expires_at     DATETIME NULL AFTER oauth_grant_id,
    ADD CONSTRAINT fk_api_tokens_oauth_grant FOREIGN KEY (oauth_grant_id) REFERENCES oauth_grants (id) ON DELETE CASCADE;
