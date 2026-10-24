<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateOverageSettings implements CommandInterface
{
    public function __construct(
        public readonly bool $flatFeeEnabled,
        public readonly string $flatFeeAmount, // als String, wird im Handler validiert/geparst (siehe ValidatesDunningLevelInput::$feeValue für dasselbe Muster)
    ) {
    }
}
