<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateLegalSettings implements CommandInterface
{
    public function __construct(
        public readonly ?string $rentalTermsMarkdown,
        public readonly ?string $imprintMode,
        public readonly ?string $imprintMarkdown,
        public readonly ?string $imprintUrl,
        public readonly ?string $privacyMode,
        public readonly ?string $privacyMarkdown,
        public readonly ?string $privacyUrl,
    ) {
    }
}
