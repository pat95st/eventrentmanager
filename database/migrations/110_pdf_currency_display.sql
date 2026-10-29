-- Nutzerwunsch: in den Einstellungen wählbar, ob Beträge auf den
-- Dokumenten mit dem Euro-Zeichen ("1.234,56 €") oder dem Text EUR
-- ("1.234,56 EUR") angegeben werden. Standard bleibt EUR (bisheriges
-- Verhalten von Angebot, AB, Rechnung und Mahnung).

ALTER TABLE pdf_design_settings
    ADD COLUMN currency_display ENUM('code', 'symbol') NOT NULL DEFAULT 'code' AFTER logo_position;
