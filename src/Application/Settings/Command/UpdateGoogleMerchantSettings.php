<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateGoogleMerchantSettings implements CommandInterface
{
    public function __construct(
        public readonly ?string $merchantId,
        public readonly ?string $targetCountry,
        public readonly ?string $contentLanguage,
        public readonly ?string $productLinkTemplate,
    ) {
    }
}
