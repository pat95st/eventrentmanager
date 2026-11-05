-- Migration 132 (Nutzerwunsch): Kontoführung je Firmenkonto in
-- Einstellungen › Bankverbindungen einschaltbar. Konten, für die bereits
-- Buchungen oder ein Anfangsbestand erfasst wurden, bleiben eingeschaltet.

ALTER TABLE bank_accounts
    ADD COLUMN ledger_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default;

UPDATE bank_accounts
SET ledger_enabled = 1
WHERE opening_balance <> 0
   OR opening_balance_date IS NOT NULL
   OR id IN (SELECT DISTINCT bank_account_id FROM account_transactions);
