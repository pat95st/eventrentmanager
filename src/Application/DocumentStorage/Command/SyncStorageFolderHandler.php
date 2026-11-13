<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\DocumentStorage\StorageFolderPaths;
use App\Application\DocumentStorage\StorageFolderShareNotifierInterface;
use App\Application\DocumentStorage\StorageFolderSyncResult;
use App\Domain\Customer\Customer;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\DocumentStorage\FolderPathTemplate;
use App\Domain\DocumentStorage\SharePermission;
use App\Domain\DocumentStorage\StorageFolder;
use App\Domain\DocumentStorage\StorageFolderOwnerType;
use App\Domain\DocumentStorage\StorageFolderRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\DocumentStorage\ShareClientInterface;
use App\Infrastructure\DocumentStorage\StorageClientFactoryInterface;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use App\Infrastructure\Mail\MailerException;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Siehe SyncStorageFolder. Jeder abgeschlossene Schritt wird sofort
 * gespeichert: scheitert z. B. die Freigabe, bleibt der bereits angelegte
 * Ordner verknüpft und der nächste Speichervorgang macht dort weiter.
 *
 * Fehler des Speicherziels (StorageUploadException) und Eingabefehler
 * (ValidationException) werden an den Controller durchgereicht — der zeigt sie
 * als Hinweis an, ohne dass das Speichern der Veranstaltung/des Kunden selbst
 * davon abhängt.
 *
 * Bewusst NICHT automatisch: Wird der Haken entfernt, bleibt der Ordner samt
 * Inhalt auf dem Speicherziel liegen (nur die Freigabe wird aufgehoben). Und
 * ein einmal angelegter Ordner wird bei späteren Umbenennungen nicht
 * verschoben.
 */
final class SyncStorageFolderHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly StorageFolderRepositoryInterface $folders,
        private readonly DocumentStorageTargetRepositoryInterface $targets,
        private readonly StorageFolderPaths $paths,
        private readonly StorageClientFactoryInterface $clientFactory,
        private readonly StorageFolderShareNotifierInterface $notifier,
    ) {
    }

    public function handle(CommandInterface $command): StorageFolderSyncResult
    {
        assert($command instanceof SyncStorageFolder);

        $folder = $this->folders->findByOwner($command->ownerType, $command->ownerId);

        if (!$command->enabled) {
            return $this->detach($folder);
        }

        [$customer, $templateValues] = $this->paths->owner($command->ownerType, $command->ownerId);

        if ($folder === null) {
            [$target, $folder] = $this->createFolder($command, $customer, $templateValues);
        } else {
            $target = $this->targets->findById($folder->storageTargetId);
            if ($target === null) {
                throw new NotFoundException('Das Ziellaufwerk dieses Ordners wurde nicht gefunden.');
            }
        }

        $shareClient = $this->clientFactory->createShareClient($target);
        if ($shareClient === null) {
            return new StorageFolderSyncResult($folder);
        }

        if (!$command->shareEnabled) {
            if ($folder->isShared()) {
                $shareClient->deleteShare((string) $folder->shareId);
                $folder->clearShare();
                $this->save($folder);
            }

            return new StorageFolderSyncResult($folder);
        }

        return $this->syncShare($command, $folder, $target, $shareClient, $customer);
    }

    private function detach(?StorageFolder $folder): StorageFolderSyncResult
    {
        if ($folder === null) {
            return new StorageFolderSyncResult(null);
        }

        $warnings = [];
        if ($folder->isShared()) {
            // Die Verknüpfung soll sich auch dann lösen lassen, wenn das
            // Speicherziel gerade nicht erreichbar ist — dann aber mit Hinweis.
            try {
                $target = $this->targets->findById($folder->storageTargetId);
                $shareClient = $target !== null ? $this->clientFactory->createShareClient($target) : null;
                $shareClient?->deleteShare((string) $folder->shareId);
            } catch (StorageUploadException $e) {
                $warnings[] = 'Die Freigabe konnte nicht aufgehoben werden und besteht weiter — bitte direkt im Dateiablagesystem entfernen: ' . $e->getMessage();
            }
        }

        $this->folders->delete($folder->id);

        return new StorageFolderSyncResult(null, null, $warnings);
    }

    /**
     * @param array<string, string> $templateValues
     * @return array{0: DocumentStorageTarget, 1: StorageFolder}
     */
    private function createFolder(SyncStorageFolder $command, Customer $customer, array $templateValues): array
    {
        $isRental = $command->ownerType === StorageFolderOwnerType::Rental;
        $inCustomerFolder = $isRental && $command->inCustomerFolder;

        if ($inCustomerFolder) {
            $customerFolder = $this->folders->findByOwner(StorageFolderOwnerType::Customer, $customer->id);
            if ($customerFolder === null) {
                throw new ValidationException(['storageFolder' => 'Für diesen Kunden gibt es noch keinen Ordner — bitte zuerst beim Kunden einen Ordner anlegen oder die Veranstaltung ohne "Unterordner des Kundenordners" speichern.']);
            }
            // Der Unterordner liegt zwangsläufig auf dem Laufwerk des Kundenordners.
            $target = $this->targets->findById($customerFolder->storageTargetId);
            if ($target === null || !$target->enabled) {
                throw new ValidationException(['storageFolder' => 'Das Ziellaufwerk des Kundenordners ist nicht (mehr) aktiv.']);
            }
            $subfolder = FolderPathTemplate::resolve($target->eventSubfolderTemplate, $templateValues);
            $path = $subfolder !== '' ? $customerFolder->path . '/' . $subfolder : '';
        } else {
            $target = $this->chooseTarget($command->storageTargetId);
            $path = FolderPathTemplate::resolve(
                $isRental ? $target->eventFolderTemplate : $target->customerFolderTemplate,
                $templateValues,
            );
        }

        if ($path === '') {
            throw new ValidationException(['storageFolder' => 'Aus der Namensvorlage des Ziellaufwerks ergibt sich für diesen Datensatz kein Ordnername — bitte die Vorlage in den Einstellungen prüfen.']);
        }

        $this->clientFactory->create($target)->createDirectory($path);

        $now = new DateTimeImmutable();
        $folder = new StorageFolder(
            id: Uuid::v4(),
            ownerType: $command->ownerType,
            ownerId: $command->ownerId,
            storageTargetId: $target->id,
            path: $path,
            inCustomerFolder: $inCustomerFolder,
            shareId: null,
            shareUrl: null,
            sharePermission: null,
            shareHasPassword: false,
            shareMailedAt: null,
            shareMailedTo: null,
            createdAt: $now,
            updatedAt: $now,
        );
        $this->folders->save($folder);

        return [$target, $folder];
    }

    private function chooseTarget(string $storageTargetId): DocumentStorageTarget
    {
        $available = array_values(array_filter(
            $this->targets->findAll(),
            static fn (DocumentStorageTarget $target) => $target->offersFolders(),
        ));

        if ($storageTargetId === '' && count($available) === 1) {
            return $available[0];
        }
        foreach ($available as $target) {
            if ($target->id === $storageTargetId) {
                return $target;
            }
        }

        throw new ValidationException(['storageFolder' => $available === []
            ? 'Es ist kein Ziellaufwerk für Ordner freigeschaltet (Einstellungen › Ziellaufwerke).'
            : 'Bitte ein Ziellaufwerk für den Ordner auswählen.']);
    }

    private function syncShare(
        SyncStorageFolder $command,
        StorageFolder $folder,
        DocumentStorageTarget $target,
        ShareClientInterface $shareClient,
        Customer $customer,
    ): StorageFolderSyncResult {
        $permission = SharePermission::tryFrom($command->sharePermission) ?? SharePermission::Read;
        $newPassword = null;
        $warnings = [];
        $sendMail = $command->resendMail;

        $email = trim((string) $customer->email);
        $hasEmail = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        // Nextcloud teilt selbst per E-Mail und verschickt die Einladung;
        // bei ownCloud entsteht ein Link, den die Anwendung verschickt.
        $nativeMailShare = $target->protocol->supportsNativeMailShare();

        if (!$folder->isShared()) {
            if ($nativeMailShare && !$hasEmail) {
                throw new ValidationException(['storageFolder' => 'Beim Kunden ist keine gültige E-Mail-Adresse hinterlegt — Nextcloud teilt den Ordner direkt mit einer E-Mail-Adresse. Bitte die Adresse beim Kunden eintragen und erneut speichern.']);
            }

            $newPassword = $target->shareAlwaysPassword ? $target->sharePasswordPolicy()->generate() : null;
            $share = $nativeMailShare
                ? $shareClient->createMailShare($folder->path, $email, $permission, $newPassword, $folder->name())
                : $shareClient->createPublicLink($folder->path, $permission, $newPassword, $folder->name());

            $folder->shareId = $share->id;
            $folder->shareUrl = $share->url;
            $folder->sharePermission = $permission;
            $folder->shareHasPassword = $newPassword !== null;
            if ($nativeMailShare) {
                $folder->shareMailedAt = new DateTimeImmutable();
                $folder->shareMailedTo = $email;
            }
            $this->save($folder);
            $sendMail = !$nativeMailShare;
        } else {
            if ($folder->sharePermission !== $permission) {
                $shareClient->updatePermission((string) $folder->shareId, $permission);
                $folder->sharePermission = $permission;
                $this->save($folder);
            }
            if ($command->regeneratePassword) {
                $newPassword = $target->sharePasswordPolicy()->generate();
                $shareClient->updatePassword((string) $folder->shareId, $newPassword);
                $folder->shareHasPassword = true;
                $this->save($folder);
            }
        }

        if ($sendMail) {
            if (!$hasEmail) {
                $warnings[] = 'Beim Kunden ist keine gültige E-Mail-Adresse hinterlegt — der Freigabe-Link wurde angelegt, aber nicht verschickt.';
            } else {
                try {
                    $this->notifier->notify($email, $customer->displayName(), $folder);
                    $folder->shareMailedAt = new DateTimeImmutable();
                    $folder->shareMailedTo = $email;
                    $this->save($folder);
                } catch (MailerException $e) {
                    $warnings[] = 'Der Freigabe-Link wurde angelegt, die E-Mail an den Kunden konnte aber nicht verschickt werden: ' . $e->getMessage();
                }
            }
        }

        return new StorageFolderSyncResult($folder, $newPassword, $warnings);
    }

    private function save(StorageFolder $folder): void
    {
        $folder->updatedAt = new DateTimeImmutable();
        $this->folders->save($folder);
    }
}
