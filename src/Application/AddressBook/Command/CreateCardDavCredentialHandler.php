<?php

declare(strict_types=1);

namespace App\Application\AddressBook\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\AddressBook\CardDavCredential;
use App\Domain\AddressBook\CardDavCredentialRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Erzeugt ein neues CardDAV-App-Passwort für den User (Basic-Auth-
 * Zugangsdaten für Kontakt-Apps, siehe CardDavAuthGuard) — Passwort/
 * Hashing-Pattern identisch zu CreateCalDavCredentialHandler (siehe dort
 * für die Begründung: zufällig generiertes Secret, nur der SHA-256-Hash
 * wird gespeichert, das Klartext-Passwort wird nur einmalig zurückgegeben).
 */
final class CreateCardDavCredentialHandler implements CommandHandlerInterface
{
    private const PASSWORD_BYTES = 24;

    public function __construct(private readonly CardDavCredentialRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CreateCardDavCredential);

        $name = trim((string) $command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für das Passwort angeben (z. B. "iPhone" oder "Thunderbird").']);
        }

        $rawPassword = bin2hex(random_bytes(self::PASSWORD_BYTES));
        $id = Uuid::v4();

        $credential = new CardDavCredential(
            id: $id,
            userId: $command->userId,
            name: $name,
            tokenHash: hash('sha256', $rawPassword),
            tokenLastFour: substr($rawPassword, -4),
            lastUsedAt: null,
            createdAt: new DateTimeImmutable(),
        );

        $this->repository->save($credential);

        return new CreatedCardDavCredential(id: $id, rawPassword: $rawPassword);
    }
}
