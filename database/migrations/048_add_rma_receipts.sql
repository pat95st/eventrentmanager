-- Verknüpfung von Belegen (Ersatzteil-Einkäufen) zu einem RMA-Vorgang vom
-- Typ "eigenreparatur". n:m, da ein Beleg theoretisch mehrere Ersatzteile
-- für dieselbe Reparatur enthalten kann und eine Reparatur mehrere Belege
-- hat.
CREATE TABLE rma_receipts (
    id          CHAR(36)  NOT NULL PRIMARY KEY,
    rma_id      CHAR(36)  NOT NULL,
    receipt_id  CHAR(36)  NOT NULL,
    created_at  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rma_receipts_rma
        FOREIGN KEY (rma_id) REFERENCES rmas (id) ON DELETE CASCADE,
    CONSTRAINT fk_rma_receipts_receipt
        FOREIGN KEY (receipt_id) REFERENCES receipts (id) ON DELETE CASCADE,
    UNIQUE KEY uq_rma_receipts_rma_receipt (rma_id, receipt_id),
    INDEX idx_rma_receipts_rma (rma_id),
    INDEX idx_rma_receipts_receipt (receipt_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
