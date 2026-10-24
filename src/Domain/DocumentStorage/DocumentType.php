<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

/**
 * Die Dokumenttypen, für die ein automatischer Push auf ein Ziellaufwerk
 * konfiguriert werden kann (siehe
 * App\Infrastructure\DocumentStorage\DocumentStoragePusher und dessen
 * Aufrufer: EmailInvoicePdfHandler, EmailOfferPdfHandler,
 * EmailPackingListPdfHandler, CreateReceiptHandler,
 * ConvertReceiptInboxAttachmentToReceiptHandler,
 * EmailDunningNoticeHandler).
 */
enum DocumentType: string
{
    case Invoice = 'invoice';
    case Offer = 'offer';
    case PackingList = 'packing_list';
    case Receipt = 'receipt';
    case DunningNotice = 'dunning_notice';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Rechnung',
            self::Offer => 'Angebot',
            self::PackingList => 'Packliste',
            self::Receipt => 'Beleg',
            self::DunningNotice => 'Mahnung',
        };
    }
}
