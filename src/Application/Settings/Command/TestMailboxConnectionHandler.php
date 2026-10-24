<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\MailboxProtocol;
use App\Domain\Settings\MailboxSettingsRepositoryInterface;
use App\Infrastructure\Mail\ImapClient;
use App\Infrastructure\Mail\MailboxClientException;
use App\Infrastructure\Mail\Pop3Client;

/**
 * Für den "Verbindung testen"-Knopf in den Mailserver-Einstellungen:
 * verbindet sich mit den AKTUELL GESPEICHERTEN Zugangsdaten und meldet
 * sich an, ruft aber bewusst KEINE Nachrichten ab — damit ein Administrator
 * Tippfehler in den Zugangsdaten sofort erkennt, statt erst beim nächsten
 * Cron-Lauf.
 */
final class TestMailboxConnectionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MailboxSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof TestMailboxConnection);

        $settings = $this->settingsRepository->get();

        if (!$settings->isConfigured()) {
            throw new ValidationException(['connection' => 'Bitte zuerst Server und Port angeben und speichern.']);
        }

        $client = $settings->protocol === MailboxProtocol::Imap
            ? new ImapClient($settings)
            : new Pop3Client($settings);

        try {
            $client->testConnection();
        } catch (MailboxClientException $e) {
            throw new ValidationException(['connection' => $e->getMessage()]);
        }

        return null;
    }
}
