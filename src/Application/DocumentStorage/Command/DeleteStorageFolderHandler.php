<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\DocumentStorage\StorageFolderRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\DocumentStorage\StorageClientFactoryInterface;
use App\Infrastructure\DocumentStorage\StorageUploadException;

/** Siehe DeleteStorageFolder. */
final class DeleteStorageFolderHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly StorageFolderRepositoryInterface $folders,
        private readonly DocumentStorageTargetRepositoryInterface $targets,
        private readonly StorageClientFactoryInterface $clientFactory,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteStorageFolder);
        $folder = $command->folder;

        $target = $this->targets->findById($folder->storageTargetId);
        if ($target === null) {
            throw new NotFoundException('Das Ziellaufwerk dieses Ordners wurde nicht gefunden.');
        }
        if (!$target->protocol->supportsFolderDelete()) {
            throw new StorageUploadException("{$target->protocol->label()} kann Ordner nicht löschen.");
        }
        if (trim($folder->path, '/') === '') {
            throw new StorageUploadException('Der Ordner hat keinen Pfad.');
        }

        $this->clientFactory->create($target)->deleteDirectory($folder->path);

        // Die Freigabe verschwindet mit dem Ordner. Verknüpfungen aufräumen:
        // die eigene (falls der Datensatz noch existiert) und die aller
        // mitgelöschten Unterordner.
        $this->folders->delete($folder->id);
        $this->folders->deleteBelowPath($target->id, $folder->path);

        return null;
    }
}
