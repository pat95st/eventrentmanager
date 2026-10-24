<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\MailboxEncryption;
use App\Domain\Settings\MailboxProtocol;
use App\Domain\Settings\MailboxSettings;
use App\Domain\Settings\MailboxSettingsRepositoryInterface;

final class UpdateMailboxSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MailboxSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateMailboxSettings);

        $errors = [];

        $protocol = MailboxProtocol::tryFrom($command->protocol);
        if ($protocol === null) {
            $errors['protocol'] = 'Bitte ein gültiges Protokoll wählen.';
        }

        $host = trim($command->host);

        $port = null;
        if (trim($command->port) !== '') {
            $port = ctype_digit(trim($command->port)) ? (int) trim($command->port) : -1;
            if ($port < 1 || $port > 65535) {
                $errors['port'] = 'Bitte einen gültigen Port (1–65535) angeben, oder leer lassen.';
            }
        }

        $encryption = MailboxEncryption::tryFrom($command->encryption);
        if ($encryption === null) {
            $errors['encryption'] = 'Bitte eine gültige Verschlüsselung wählen.';
        }

        $imapFolder = trim($command->imapFolder);
        if ($imapFolder === '') {
            $imapFolder = 'INBOX';
        }

        // Nur bei aktiviertem Import ist ein vollständig konfigurierter
        // Server zwingend nötig — beim Erstellen/Speichern mit
        // enabled=false soll ein unvollständiger Entwurf weiter gespeichert
        // werden können.
        if ($command->enabled && $host === '') {
            $errors['host'] = 'Bitte einen Server angeben, um den Import zu aktivieren.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $current = $this->settingsRepository->get();

        $settings = new MailboxSettings(
            protocol: $protocol,
            host: $host !== '' ? $host : null,
            port: $port,
            encryption: $encryption,
            username: $this->nullIfEmpty($command->username),
            password: $command->password !== null && $command->password !== '' ? $command->password : $current->password,
            imapFolder: $imapFolder,
            enabled: $command->enabled,
        );

        $this->settingsRepository->save($settings);

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
