-- REST API v1 extension: scopes per token, read-only switch, rate limiting
-- (database or Redis backend) and a log of write requests.
-- See App\Domain\Api\ApiScope, App\Domain\Api\ApiToken, App\Domain\Settings\ApiSettings
-- and docs/openapi/.

-- Scopes as a JSON array of strings (e.g. ["articles:read","customers:write"]).
ALTER TABLE api_tokens
    ADD COLUMN scopes                TEXT       NULL AFTER enabled,
    ADD COLUMN read_only             TINYINT(1) NOT NULL DEFAULT 0 AFTER scopes,
    ADD COLUMN rate_limit_per_minute INT        NULL AFTER read_only;

-- Existing tokens keep exactly their previous access: read articles only.
UPDATE api_tokens SET scopes = '["articles:read"]', read_only = 1;

ALTER TABLE api_settings
    ADD COLUMN rate_limit_per_minute INT UNSIGNED NOT NULL DEFAULT 600 AFTER enabled,
    ADD COLUMN rate_limit_backend    ENUM('database', 'redis') NOT NULL DEFAULT 'database' AFTER rate_limit_per_minute,
    ADD COLUMN redis_connection      ENUM('tcp', 'tls', 'socket') NOT NULL DEFAULT 'tcp' AFTER rate_limit_backend,
    ADD COLUMN redis_host            VARCHAR(255) NOT NULL DEFAULT '127.0.0.1' AFTER redis_connection,
    ADD COLUMN redis_port            INT UNSIGNED NOT NULL DEFAULT 6379 AFTER redis_host,
    ADD COLUMN redis_socket_path     VARCHAR(500) NULL AFTER redis_port,
    ADD COLUMN redis_username        VARCHAR(255) NULL AFTER redis_socket_path,
    ADD COLUMN redis_password        VARCHAR(500) NULL AFTER redis_username,
    ADD COLUMN redis_database        INT UNSIGNED NOT NULL DEFAULT 0 AFTER redis_password,
    ADD COLUMN redis_key_prefix      VARCHAR(100) NOT NULL DEFAULT 'mvl:ratelimit:' AFTER redis_database,
    ADD COLUMN redis_timeout_ms      INT UNSIGNED NOT NULL DEFAULT 1000 AFTER redis_key_prefix,
    ADD COLUMN redis_last_error      VARCHAR(500) NULL AFTER redis_timeout_ms,
    ADD COLUMN redis_last_error_at   DATETIME NULL AFTER redis_last_error;

-- Fixed one-minute windows per token (database backend; also the fallback
-- when Redis is unreachable). Old windows are purged opportunistically.
CREATE TABLE api_rate_limits (
    token_id     CHAR(36)     NOT NULL,
    window_start DATETIME     NOT NULL,
    hits         INT UNSIGNED NOT NULL,
    PRIMARY KEY (token_id, window_start)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Every write request (POST/PATCH/PUT/DELETE) through /api/v1, kept 90 days.
CREATE TABLE api_request_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    token_id    CHAR(36)     NOT NULL,
    token_name  VARCHAR(255) NOT NULL,
    method      VARCHAR(10)  NOT NULL,
    path        VARCHAR(500) NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL,
    ip          VARCHAR(45)  NULL,
    created_at  DATETIME     NOT NULL,
    KEY idx_api_request_log_token (token_id, created_at),
    KEY idx_api_request_log_created (created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
