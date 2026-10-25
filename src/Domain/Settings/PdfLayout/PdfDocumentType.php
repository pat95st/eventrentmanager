<?php

declare(strict_types=1);

namespace App\Domain\Settings\PdfLayout;

/**
 * Die Dokumenttypen, für die der visuelle PDF-Vorlagen-Editor (siehe
 * PdfLayoutBlueprint) Positionen anbietet. Der String-Wert ist zugleich
 * die URL-Kennung ("?typ=...") und der Datenbankwert in
 * pdf_layout_elements.document_type.
 */
enum PdfDocumentType: string
{
    case Invoice = 'rechnung';
    case Offer = 'angebot';
    case OrderConfirmation = 'auftragsbestaetigung';
    case Dunning = 'mahnung';
    case PackingList = 'packliste';
    case PriceList = 'preisliste';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Rechnung',
            self::Offer => 'Angebot',
            self::OrderConfirmation => 'Auftragsbestätigung',
            self::Dunning => 'Mahnung',
            self::PackingList => 'Packliste',
            self::PriceList => 'Preisliste',
        };
    }
}
