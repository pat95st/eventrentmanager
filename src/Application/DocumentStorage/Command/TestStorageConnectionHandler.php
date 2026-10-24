<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Infrastructure\DocumentStorage\StorageClientFactory;
use App\Infrastructure\DocumentStorage\StorageUploadException;

/**
 * Lädt eine kleine Testdatei in die Wurzel des Ziellaufwerks hoch und
 * versucht sie danach wieder zu entfernen — anders als der automatische
 * Dokument-Push (siehe DocumentStoragePusher) wird eine Exception hier
 * bewusst NICHT abgefangen, sondern an den Controller durchgereicht: Der
 * Administrator hat den Test aktiv ausgelöst und soll die konkrete
 * Fehlermeldung sehen.
 */
final class TestStorageConnectionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly DocumentStorageTargetRepositoryInterface $targets,
        private readonly StorageClientFactory $clientFactory,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof TestStorageConnection);

        $target = $this->targets->findById($command->id);
        if ($target === null) {
            throw new NotFoundException("Ziellaufwerk \"{$command->id}\" wurde nicht gefunden.");
        }

        $client = $this->clientFactory->create($target);

        /** @throws StorageUploadException */
        return $client->testConnection();
    }
}
