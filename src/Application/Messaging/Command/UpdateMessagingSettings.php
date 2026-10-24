<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Application\Command\CommandInterface;

final class UpdateMessagingSettings implements CommandInterface
{
    public function __construct(
        public readonly bool $whatsappEnabled,
        public readonly ?string $whatsappPhoneNumberId,
        // null/leer = Access-Token unverändert lassen (siehe Handler) — analog UpdateSmtpSettings::$password.
        public readonly ?string $whatsappAccessToken,
        public readonly ?string $whatsappBusinessAccountId,

        public readonly bool $telegramEnabled,
        public readonly ?string $telegramBotToken,

        public readonly bool $signalEnabled,
        public readonly ?string $signalApiBaseUrl,
        public readonly ?string $signalSenderNumber,
    ) {
    }
}
