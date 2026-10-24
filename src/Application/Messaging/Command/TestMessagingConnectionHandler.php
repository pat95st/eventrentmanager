<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Messaging\MessageSenderException;
use App\Infrastructure\Messaging\MessageSenderInterface;

/**
 * Für den "Verbindung testen"-Knopf je Kanal in den Messenger-Einstellungen
 * — analog TestMailboxConnectionHandler: prüft die AKTUELL GESPEICHERTEN
 * Zugangsdaten mit einem möglichst "billigen" API-Aufruf (siehe
 * MessageSenderInterface::testConnection()), ohne eine echte Nachricht zu
 * verschicken.
 */
final class TestMessagingConnectionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MessageSenderInterface $sender)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof TestMessagingConnection);

        try {
            return $this->sender->testConnection($command->channel);
        } catch (MessageSenderException $e) {
            throw new ValidationException(['connection' => $e->getMessage()]);
        }
    }
}
