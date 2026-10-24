-- Optionale, token-gesicherte Lese-REST-API für Artikel (siehe
-- App\Domain\Api\ApiToken und ArticleApiController). Der globale
-- Ein-/Ausschalter liegt in api_settings (Singleton-Zeile analog zu
-- company_settings), einzelne Zugriffs-Tokens in api_tokens.
CREATE TABLE api_settings (
    id          TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    enabled     TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_api_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO api_settings (id, enabled) VALUES (1, 0);

CREATE TABLE api_tokens (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    name              VARCHAR(255) NOT NULL,
    token_hash        CHAR(64)     NOT NULL, -- sha256() des Klartext-Tokens, siehe ApiToken-Docblock
    token_last_four   CHAR(4)      NOT NULL,
    ip_allowlist      TEXT         NULL, -- eine IP/CIDR pro Zeile; NULL/leer = von überall erlaubt
    enabled           TINYINT(1)   NOT NULL DEFAULT 1,
    last_used_at      DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uniq_api_tokens_token_hash UNIQUE (token_hash)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
