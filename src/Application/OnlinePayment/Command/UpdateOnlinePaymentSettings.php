<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandInterface;

/** Empty secret fields keep the stored secret (the form never shows it). */
final class UpdateOnlinePaymentSettings implements CommandInterface
{
    public function __construct(
        public readonly bool $paypalEnabled,
        public readonly bool $paypalSandbox,
        public readonly ?string $paypalClientId,
        public readonly ?string $paypalClientSecret,
        public readonly bool $sumupEnabled,
        public readonly ?string $sumupApiKey,
        public readonly ?string $sumupMerchantCode,
        public readonly ?string $currency,
    ) {
    }
}
