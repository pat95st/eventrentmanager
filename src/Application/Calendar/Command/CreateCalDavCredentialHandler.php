<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Calendar\CalDavCredential;
use App\Domain\Calendar\CalDavCredentialRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Erzeugt ein neues CalDAV-App-Passwort für den User (Basic-Auth-Zugangsdaten
 * für Kalender-Apps, siehe CalDavAuthGuard) — Passwort/Hashing-Pattern
 * identisch zu CreateApiTokenHandler (siehe dort für die Begründung:
 * zufällig generiertes Secret, nur der SHA-256-Hash wird gespeichert, das
 * Klartext-Passwort wird nur einmalig zurückgegeben).
 */
final class CreateCalDavCredentialHandler implements CommandHandlerInterface
{
    private const PASSWORD_BYTES = 24;

    public function __construct(private readonly CalDavCredentialRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CreateCalDavCredential);

        $name = trim((string) $command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für das Passwort angeben (z. B. "iPhone" oder "Thunderbird").']);
        }

        $rawPassword = bin2hex(random_bytes(self::PASSWORD_BYTES));
        $id = Uuid::v4();

        $credential = new CalDavCredential(
            id: $id,
            userId: $command->userId,
            name: $name,
            tokenHash: hash('sha256', $rawPassword),
            tokenLastFour: substr($rawPassword, -4),
            lastUsedAt: null,
            createdAt: new DateTimeImmutable(),
        );

        $this->repository->save($credential);

        return new CreatedCalDavCredential(id: $id, rawPassword: $rawPassword);
    }
}
