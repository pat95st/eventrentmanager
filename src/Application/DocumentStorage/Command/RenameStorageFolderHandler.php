<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\DocumentStorage\StorageFolderPaths;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\DocumentStorage\StorageFolderRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\DocumentStorage\StorageClientFactoryInterface;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use DateTimeImmutable;

/**
 * Siehe RenameStorageFolder. Liegen unter dem Ordner weitere verknüpfte
 * Ordner (Veranstaltungen als Unterordner eines Kundenordners), ziehen deren
 * gespeicherte Pfade mit um. Eine Freigabe bleibt dabei bestehen — sie hängt
 * in Nextcloud/ownCloud am Ordner selbst, nicht an seinem Pfad.
 *
 * @return string Der neue Pfad (bzw. der unveränderte, wenn nichts zu tun war).
 */
final class RenameStorageFolderHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly StorageFolderRepositoryInterface $folders,
        private readonly DocumentStorageTargetRepositoryInterface $targets,
        private readonly StorageFolderPaths $paths,
        private readonly StorageClientFactoryInterface $clientFactory,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof RenameStorageFolder);

        $folder = $this->folders->findByOwner($command->ownerType, $command->ownerId);
        if ($folder === null) {
            throw new NotFoundException('Für diesen Datensatz ist kein Ordner verknüpft.');
        }
        $target = $this->targets->findById($folder->storageTargetId);
        if ($target === null) {
            throw new NotFoundException('Das Ziellaufwerk dieses Ordners wurde nicht gefunden.');
        }
        if (!$target->protocol->supportsFolderRename()) {
            throw new StorageUploadException("{$target->protocol->label()} kann Ordner nicht umbenennen.");
        }

        $oldPath = $folder->path;
        $newPath = $this->paths->expectedPath($folder, $target);
        if ($newPath === '' || $newPath === $oldPath) {
            return $oldPath;
        }

        $this->clientFactory->create($target)->moveDirectory($oldPath, $newPath);

        $folder->path = $newPath;
        $folder->updatedAt = new DateTimeImmutable();
        $this->folders->save($folder);
        $this->folders->replacePathPrefix($target->id, $oldPath, $newPath);

        return $newPath;
    }
}
