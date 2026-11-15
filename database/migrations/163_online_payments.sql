-- Migration 163: Online payment of invoices via PayPal and SumUp.
--
--  - online_payment_settings: singleton (id = 1) holding the API
--    credentials of both providers and whether each one is offered to
--    customers.
--  - invoice_payment_links: one secret, unguessable token per invoice. The
--    public payment page /zahlung/{token} is reachable without login, so the
--    token is the only thing protecting it (same concept as the customer
--    checklist link).
--  - invoice_online_payments: one row per payment attempt (checkout at the
--    provider). provider_reference is the PayPal order id or the SumUp
--    checkout id. Only a row in status 'completed' marks the invoice as paid.
--  - The "invoice per e-mail" template gets the new placeholder
--    {{payment_link_html}} (empty as long as no provider is enabled).

CREATE TABLE online_payment_settings (
    id                    TINYINT       NOT NULL PRIMARY KEY DEFAULT 1,
    paypal_enabled        TINYINT(1)    NOT NULL DEFAULT 0,
    paypal_sandbox        TINYINT(1)    NOT NULL DEFAULT 1,
    paypal_client_id      VARCHAR(255)  NULL,
    paypal_client_secret  VARCHAR(255)  NULL,
    sumup_enabled         TINYINT(1)    NOT NULL DEFAULT 0,
    sumup_api_key         VARCHAR(255)  NULL,
    sumup_merchant_code   VARCHAR(50)   NULL,
    currency              VARCHAR(3)    NOT NULL DEFAULT 'EUR',
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_online_payment_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO online_payment_settings (id) VALUES (1);

CREATE TABLE invoice_payment_links (
    invoice_id  CHAR(36)     NOT NULL PRIMARY KEY,
    token       VARCHAR(64)  NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_payment_links_token (token),
    CONSTRAINT fk_invoice_payment_links_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE invoice_online_payments (
    id                  CHAR(36)       NOT NULL PRIMARY KEY,
    invoice_id          CHAR(36)       NOT NULL,
    provider            VARCHAR(20)    NOT NULL,              -- paypal | sumup
    provider_reference  VARCHAR(100)   NULL,                  -- PayPal order id / SumUp checkout id
    amount              DECIMAL(12, 2) NOT NULL,
    currency            VARCHAR(3)     NOT NULL,
    status              VARCHAR(20)    NOT NULL DEFAULT 'pending', -- pending | completed | failed | cancelled
    failure_reason      VARCHAR(500)   NULL,
    created_at          DATETIME       NOT NULL,
    updated_at          DATETIME       NOT NULL,
    completed_at        DATETIME       NULL,
    UNIQUE KEY uq_invoice_online_payments_reference (provider, provider_reference),
    INDEX idx_invoice_online_payments_invoice (invoice_id),
    CONSTRAINT fk_invoice_online_payments_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

UPDATE email_templates
SET body_html = REPLACE(body_html, '<p>Mit freundlichen Grüßen</p>', '{{payment_link_html}}\n<p>Mit freundlichen Grüßen</p>')
WHERE template_key = 'invoice_pdf'
  AND body_html NOT LIKE '%{{payment_link_html}}%';
