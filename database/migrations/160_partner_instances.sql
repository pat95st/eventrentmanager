-- Migration 160: Partner instances (federation between installations).
--
-- Two installations of this software can become "friends": one side creates
-- an invitation link, the other side enters it, and the inviting side
-- confirms. Afterwards both sides can browse each other's material pool,
-- request rentals and request technicians.
--
--  - partner_instances: one row per befriended installation. Holds the
--    token used to call the partner (outbound_token) and the hash of the
--    token the partner uses to call us (inbound_token_hash), the agreed
--    discounts (from our point of view), a pending discount proposal and
--    local settings (auto-accept rental requests, allow technician requests).
--  - partner_invitations: invitation links (only the SHA-256 hash of the
--    secret code is stored).
--  - partner_requests: material and technician requests in both directions.
CREATE TABLE IF NOT EXISTS partner_instances (
    id                         CHAR(36)      NOT NULL PRIMARY KEY,
    name                       VARCHAR(255)  NOT NULL,
    base_url                   VARCHAR(500)  NOT NULL,
    status                     VARCHAR(30)   NOT NULL,
    outbound_token             VARCHAR(255)  NULL,
    inbound_token_hash         CHAR(64)      NULL,
    profile                    TEXT          NULL,
    auto_accept_requests       TINYINT(1)    NOT NULL DEFAULT 0,
    allow_technician_requests  TINYINT(1)    NOT NULL DEFAULT 1,
    -- Discount we grant the partner on our material / the partner grants us.
    discount_granted           DECIMAL(5, 2) NOT NULL DEFAULT 0,
    discount_received          DECIMAL(5, 2) NOT NULL DEFAULT 0,
    proposed_discount_granted  DECIMAL(5, 2) NULL,
    proposed_discount_received DECIMAL(5, 2) NULL,
    -- 'us' or 'them': who made the pending proposal (NULL = none).
    proposal_from              VARCHAR(10)   NULL,
    customer_id                CHAR(36)      NULL,
    last_error                 VARCHAR(1000) NULL,
    last_contact_at            DATETIME      NULL,
    confirmed_at               DATETIME      NULL,
    created_at                 DATETIME      NOT NULL,
    updated_at                 DATETIME      NOT NULL,
    UNIQUE KEY uq_partner_instances_inbound_token (inbound_token_hash),
    CONSTRAINT fk_partner_instances_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_invitations (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    code_hash   CHAR(64)     NOT NULL,
    note        VARCHAR(255) NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    partner_id  CHAR(36)     NULL,
    created_at  DATETIME     NOT NULL,
    UNIQUE KEY uq_partner_invitations_code (code_hash),
    CONSTRAINT fk_partner_invitations_partner
        FOREIGN KEY (partner_id) REFERENCES partner_instances (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_requests (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    partner_id          CHAR(36)      NOT NULL,
    -- 'outgoing' (we asked the partner) or 'incoming' (the partner asked us).
    direction           VARCHAR(10)   NOT NULL,
    -- 'material' or 'technician'.
    kind                VARCHAR(20)   NOT NULL,
    -- Shared identifier of the request on both installations.
    reference           CHAR(36)      NOT NULL,
    status              VARCHAR(20)   NOT NULL,
    event_name          VARCHAR(255)  NOT NULL,
    start_date          DATE          NOT NULL,
    end_date            DATE          NOT NULL,
    event_location      VARCHAR(255)  NULL,
    note                TEXT          NULL,
    contact_name        VARCHAR(255)  NULL,
    contact_email       VARCHAR(255)  NULL,
    -- JSON: requested articles, trades, preferred technician, technicians.
    items               TEXT          NULL,
    trades              TEXT          NULL,
    technician_count    INT           NOT NULL DEFAULT 0,
    preferred_technician VARCHAR(500) NULL,
    technicians         TEXT          NULL,
    discount_percent    DECIMAL(5, 2) NULL,
    rental_id           CHAR(36)      NULL,
    response_message    VARCHAR(1000) NULL,
    sync_error          VARCHAR(1000) NULL,
    created_at          DATETIME      NOT NULL,
    updated_at          DATETIME      NOT NULL,
    responded_at        DATETIME      NULL,
    UNIQUE KEY uq_partner_requests_reference (partner_id, direction, reference),
    INDEX idx_partner_requests_rental (rental_id),
    INDEX idx_partner_requests_status (status),
    CONSTRAINT fk_partner_requests_partner
        FOREIGN KEY (partner_id) REFERENCES partner_instances (id) ON DELETE CASCADE,
    CONSTRAINT fk_partner_requests_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
