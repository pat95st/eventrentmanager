<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessagingChannel;

final class SaveMessageTemplate implements CommandInterface
{
    public function __construct(
        public readonly MessagingChannel $channel,
        public readonly MessageTemplateKey $key,
        // Telegram/Signal.
        public readonly ?string $bodyText,
        // WhatsApp.
        public readonly ?string $metaTemplateName,
        public readonly ?string $metaTemplateLanguage,
    ) {
    }
}
