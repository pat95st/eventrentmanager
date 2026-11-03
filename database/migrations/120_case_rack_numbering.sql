-- Migration 120: Inventarnummern für Cases und Racks automatisch und
-- fortlaufend (Nutzerwunsch): CASE-0001, RACK-0001 … über das bestehende
-- Nummerierungssystem (Einstellungen › Nummerierung, dort anpassbar).
-- Vorhandene Nummern bleiben unverändert.

INSERT IGNORE INTO document_numbering_settings (document_type, scheme, prefix, `separator`, digit_count) VALUES
    ('case_item', 'simple', 'CASE-', '-', 4),
    ('rack', 'simple', 'RACK-', '-', 4);

INSERT IGNORE INTO document_number_sequences (document_type, sequence_year, next_number) VALUES
    ('case_item', 0, 1),
    ('rack', 0, 1);
