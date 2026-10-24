<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateInvoicePdfSettings implements CommandInterface
{
    public function __construct(
        public readonly ?string $introHtml,
        public readonly ?string $closingHtml,
    ) {
    }
}
