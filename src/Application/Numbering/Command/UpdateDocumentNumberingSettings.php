<?php

declare(strict_types=1);

namespace App\Application\Numbering\Command;

use App\Application\Command\CommandInterface;

final class UpdateDocumentNumberingSettings implements CommandInterface
{
    /**
     * @param array<string, array{scheme: string, prefix: string, separator: string, digit_count: string, next_number: string}> $entries
     *   Schlüssel ist der NumberedDocumentType-String-Wert (z. B. "invoice").
     *   next_number ist leer = Zähler unverändert lassen, sonst Admin-
     *   Override des Zählerstands für das aktuelle Vorschaujahr (siehe
     *   DocumentNumberingSettingsRepositoryInterface::setNextNumber()) —
     *   ermöglicht den Umstieg von einem anderen Tool ohne doppelte Nummern.
     */
    public function __construct(
        public readonly array $entries,
    ) {
    }
}
