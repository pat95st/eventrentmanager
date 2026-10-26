<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Command;

use App\Application\Command\CommandInterface;

final class CreateClosingTextTemplate implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $folderId,
        // Rohes Rich-Text-HTML aus dem Formular, wird vom Handler über
        // HtmlSanitizer bereinigt (siehe ClosingTextTemplate::$content).
        public readonly ?string $content,
    ) {
    }
}
