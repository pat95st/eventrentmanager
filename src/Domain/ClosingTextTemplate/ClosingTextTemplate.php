<?php

declare(strict_types=1);

namespace App\Domain\ClosingTextTemplate;

use DateTimeImmutable;

/**
 * Eine speicherbare Textvorlage für den Schlusstext von Rechnungen,
 * Angeboten und Auftragsbestätigungen (siehe
 * Invoice::$closingText/Offer::$closingText/OrderConfirmation::$closingText
 * sowie renderDocumentClosingTextField() in den jeweiligen Formularen) —
 * optional in einem ClosingTextTemplateFolder abgelegt (null = unsortiert,
 * oberste Ebene).
 */
final class ClosingTextTemplate
{
    public function __construct(
        public readonly string $id,
        public ?string $folderId,
        public string $name,
        // Bereits bereinigtes Rich-Text-HTML, identisches Format wie
        // Invoice::$closingText (siehe App\Infrastructure\RichText\HtmlSanitizer).
        public ?string $content,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
