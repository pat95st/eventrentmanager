-- Migration für bereits bestehende Installationen: optionaler Markenname für
-- die Firmeneinstellungen. Getrennt vom rechtlich-korrekten Firmennamen
-- (company_settings.name, wird z. B. für Impressum/Angebote/Rechnungen
-- gebraucht) — der Markenname ist rein kosmetisch und wird dort verwendet,
-- wo eine kürzere/freundlichere Bezeichnung sinnvoll ist (aktuell: Alt-Text
-- des Logos im Header). Bleibt der Markenname leer, wird weiterhin der
-- bisherige Standardtext verwendet.
USE materialverleih;

ALTER TABLE company_settings
    ADD COLUMN brand_name VARCHAR(255) NULL AFTER name;
