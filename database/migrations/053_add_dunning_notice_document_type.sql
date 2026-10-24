-- Erweitert den ENUM document_type um 'dunning_notice', damit auch
-- Mahnungs-PDFs (siehe App\Application\Dunning\Command\EmailDunningNoticeHandler)
-- über die bestehenden Speicherziel-Zuordnungen (document_storage_assignments)
-- automatisch auf ein konfiguriertes Ziellaufwerk gepusht und im
-- Upload-Protokoll (document_storage_upload_log) erfasst werden können —
-- siehe App\Domain\DocumentStorage\DocumentType::DunningNotice.

ALTER TABLE document_storage_assignments
    MODIFY document_type ENUM('invoice', 'offer', 'packing_list', 'receipt', 'dunning_notice') NOT NULL;

ALTER TABLE document_storage_upload_log
    MODIFY document_type ENUM('invoice', 'offer', 'packing_list', 'receipt', 'dunning_notice') NOT NULL;
