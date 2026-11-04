-- Prüfabfrage nach Migration 124 (in phpMyAdmin ausführen).
-- Jede Zeile sollte bei "problems" eine 0 zeigen.
SELECT 'Racks ohne Exemplar' AS check_name, COUNT(*) AS problems
  FROM racks r WHERE NOT EXISTS (SELECT 1 FROM rack_exemplars e WHERE e.rack_id = r.id)
UNION ALL SELECT 'Exemplare ohne Rack-Nummer', COUNT(*) FROM rack_exemplars WHERE rack_number IS NULL
UNION ALL SELECT 'Bestückung ohne Exemplar', COUNT(*) FROM rack_slots s LEFT JOIN rack_exemplars e ON e.id = s.rack_exemplar_id WHERE e.id IS NULL
UNION ALL SELECT 'Rack-Typen ohne Hersteller', COUNT(*) FROM racks WHERE manufacturer_id IS NULL
UNION ALL SELECT 'Case-Exemplare ohne Case-Nummer', COUNT(*) FROM case_inventory_numbers WHERE case_number IS NULL
UNION ALL SELECT 'Rack-Nummernkreis zu niedrig', COUNT(*) FROM rack_exemplars e
  WHERE e.rack_number REGEXP '^RACK-[0-9]+$'
    AND CAST(SUBSTRING(e.rack_number, 6) AS UNSIGNED) >= (SELECT next_number FROM document_number_sequences WHERE document_type = 'rack' AND sequence_year = 0)
UNION ALL SELECT 'Case-Nummernkreis zu niedrig', COUNT(*) FROM case_inventory_numbers c
  WHERE c.case_number REGEXP '^CASE-[0-9]+$'
    AND CAST(SUBSTRING(c.case_number, 6) AS UNSIGNED) >= (SELECT next_number FROM document_number_sequences WHERE document_type = 'case_item' AND sequence_year = 0);
