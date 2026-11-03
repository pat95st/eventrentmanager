-- Migration 123: Rechnungskorrekturen/Stornorechnungen automatisch ablegen
-- (Nutzerwunsch). Erweitert den ENUM document_type um 'invoice_correction',
-- damit ausgestellte Korrekturen über die Speicherziel-Zuordnungen
-- (Einstellungen › Dokumentablage) auf ein Ziellaufwerk gelegt und im
-- Upload-Protokoll erfasst werden – siehe IssueInvoiceCorrectionHandler.

ALTER TABLE document_storage_assignments
    MODIFY document_type ENUM('invoice', 'offer', 'packing_list', 'receipt', 'dunning_notice', 'invoice_correction') NOT NULL;

ALTER TABLE document_storage_upload_log
    MODIFY document_type ENUM('invoice', 'offer', 'packing_list', 'receipt', 'dunning_notice', 'invoice_correction') NOT NULL;
