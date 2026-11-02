<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateDocumentIntroTexts implements CommandInterface
{
    /** @param array<string, string> $texts Dokumenttyp => Text */
    public function __construct(public readonly array $texts)
    {
    }
}
