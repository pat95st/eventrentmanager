<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Domain\Messaging\MessagingChannel;

/** Ergebnis eines Versandversuchs über mehrere Kanäle gleichzeitig (siehe SendRentalReminderViaMessagingHandler). */
final class MessagingSendResult
{
    /**
     * @param MessagingChannel[] $sentChannels
     * @param array<string, string> $failedChannels [Kanal-Wert => Fehlermeldung]
     */
    public function __construct(
        public readonly array $sentChannels,
        public readonly array $failedChannels,
    ) {
    }
}
