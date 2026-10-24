-- Erweitert rmas um eine Unterscheidung zwischen externer Einsendung (bisheriges
-- Verhalten) und Eigenreparatur (siehe 048_add_rma_receipts.sql für die
-- verknüpften Belege der Ersatzteile). worked_hours ist nur bei
-- Eigenreparaturen gesetzt.
ALTER TABLE rmas
    ADD COLUMN kind ENUM('extern', 'eigenreparatur') NOT NULL DEFAULT 'extern' AFTER inventory_number_id,
    ADD COLUMN worked_hours DECIMAL(6, 2) NULL AFTER notes;
