<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateAiAdImageSettings implements CommandInterface
{
    public function __construct(
        // null/leer = API-Key unverändert lassen (siehe Handler) — analog UpdateMessagingSettings::$whatsappAccessToken.
        public readonly ?string $openaiApiKey,
    ) {
    }
}
