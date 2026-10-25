<?php

declare(strict_types=1);

namespace App\Domain\Settings\PdfLayout;

interface PdfLayoutSettingsRepositoryInterface
{
    public function getForDocumentType(PdfDocumentType $type): PdfLayoutSettings;

    /** @param array<string, PdfLayoutElementPosition> $positions keyed nach element_key, ersetzt ALLE bisherigen Positionen dieses Dokumenttyps */
    public function saveForDocumentType(PdfDocumentType $type, array $positions): void;

    public function resetElement(PdfDocumentType $type, string $elementKey): void;
}
