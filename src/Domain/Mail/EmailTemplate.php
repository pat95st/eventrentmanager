<?php

declare(strict_types=1);

namespace App\Domain\Mail;

final class EmailTemplate
{
    public function __construct(
        public readonly EmailTemplateKey $key,
        public string $subject,
        public string $bodyHtml,
    ) {
    }
}
