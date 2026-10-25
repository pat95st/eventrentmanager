<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Settings\PdfLayout\PdfDocumentType;

final class UpdatePdfLayoutSettings implements CommandInterface
{
    /**
     * @param array<string, array{x: float, y: float, width: float|null, fontSize: float|null}> $positions
     *     keyed nach element_key, wie vom Editor (siehe pdf-layout-editor.php) per fetch() gesendet
     */
    public function __construct(
        public readonly PdfDocumentType $documentType,
        public readonly array $positions,
    ) {
    }
}
