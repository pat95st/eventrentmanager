<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Command\CommandBus;
use App\Application\DocumentStorage\Command\SyncStorageFolder;
use App\Application\DocumentStorage\StorageFolderSyncResult;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\DocumentStorage\SharePermission;
use App\Domain\DocumentStorage\StorageFolder;
use App\Domain\DocumentStorage\StorageFolderOwnerType;
use App\Domain\DocumentStorage\StorageFolderRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use Throwable;

/**
 * Formularbaustein "Ordner im Dateiablagesystem" (Migration 148) für
 * Veranstaltungen und Kunden — siehe resources/views/_storage_folder_fields.php.
 * Wie DryHireLookup in bootstrap.php gesetzt; ohne Konfiguration (z. B. Tests)
 * gibt es den Baustein schlicht nicht.
 */
final class StorageFolderForm
{
    private const SESSION_PASSWORD_KEY = 'storage_folder_password_once';

    private static ?CommandBus $commands = null;
    private static ?StorageFolderRepositoryInterface $folders = null;
    private static ?DocumentStorageTargetRepositoryInterface $targets = null;

    public static function configure(
        CommandBus $commands,
        StorageFolderRepositoryInterface $folders,
        DocumentStorageTargetRepositoryInterface $targets,
    ): void {
        self::$commands = $commands;
        self::$folders = $folders;
        self::$targets = $targets;
    }

    /**
     * Daten für den Formularbaustein. Nach einem Validierungsfehler des
     * umgebenden Formulars (erneutes Rendern im selben POST) bleiben die
     * gerade abgeschickten Angaben erhalten.
     *
     * @return array<string, mixed>
     */
    public static function formData(StorageFolderOwnerType $ownerType, ?string $ownerId): array
    {
        if (self::$folders === null || self::$targets === null) {
            return ['available' => false];
        }

        try {
            $folder = $ownerId !== null ? self::$folders->findByOwner($ownerType, $ownerId) : null;
            $allTargets = self::$targets->findAll();
            $customerFolderTargets = $ownerType === StorageFolderOwnerType::Rental ? self::$folders->customerFolderTargets() : [];
        } catch (Throwable $e) {
            // Z. B. Migration 148 noch nicht eingespielt — das Formular selbst soll trotzdem funktionieren.
            error_log('StorageFolderForm: ' . $e->getMessage());

            return ['available' => false];
        }

        $targetsById = [];
        foreach ($allTargets as $target) {
            $targetsById[$target->id] = $target;
        }
        $folderTarget = $folder !== null ? ($targetsById[$folder->storageTargetId] ?? null) : null;
        $selectable = array_values(array_filter($allTargets, static fn (DocumentStorageTarget $t) => $t->offersFolders()));

        if ($folder === null && $selectable === []) {
            return ['available' => false];
        }

        $posted = ($_POST['storage_folder_section'] ?? null) === '1';
        $flag = static fn (string $field, bool $default): bool => $posted ? ($_POST[$field] ?? null) === '1' : $default;

        return [
            'available' => true,
            'ownerType' => $ownerType->value,
            'folder' => $folder,
            'folderTarget' => $folderTarget,
            'targets' => array_map(static fn (DocumentStorageTarget $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'sharing' => $t->protocol->supportsSharing(),
                'alwaysPassword' => $t->shareAlwaysPassword,
            ], $selectable),
            // Kunden-ID => Angaben zum Laufwerk des Kundenordners (nur Veranstaltungen).
            'customerFolders' => array_map(static fn (string $targetId) => [
                'name' => isset($targetsById[$targetId]) ? $targetsById[$targetId]->name : '',
                'sharing' => isset($targetsById[$targetId]) && $targetsById[$targetId]->protocol->supportsSharing(),
                'alwaysPassword' => isset($targetsById[$targetId]) && $targetsById[$targetId]->shareAlwaysPassword,
            ], $customerFolderTargets),
            'enabled' => $flag('storage_folder_enabled', $folder !== null),
            'selectedTargetId' => $posted ? (string) ($_POST['storage_folder_target_id'] ?? '') : '',
            'inCustomerFolder' => $flag('storage_folder_in_customer_folder', $folder?->inCustomerFolder ?? false),
            'shareEnabled' => $flag('storage_folder_share_enabled', $folder?->isShared() ?? false),
            'sharePermission' => $posted
                ? (string) ($_POST['storage_folder_share_permission'] ?? SharePermission::Read->value)
                : ($folder?->sharePermission?->value ?? SharePermission::Read->value),
            'permissions' => SharePermission::cases(),
        ];
    }

    /**
     * Für die Detailseite: der angelegte Ordner samt Name des Ziellaufwerks.
     *
     * @return array{folder: StorageFolder, targetName: string}|null
     */
    public static function folderInfo(StorageFolderOwnerType $ownerType, string $ownerId): ?array
    {
        if (self::$folders === null || self::$targets === null) {
            return null;
        }

        try {
            $folder = self::$folders->findByOwner($ownerType, $ownerId);
            if ($folder === null) {
                return null;
            }

            return ['folder' => $folder, 'targetName' => self::$targets->findById($folder->storageTargetId)?->name ?? ''];
        } catch (Throwable $e) {
            error_log('StorageFolderForm: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Nach dem erfolgreichen Speichern der Veranstaltung/des Kunden aufrufen.
     * Scheitert etwas am Speicherziel, bleibt die Veranstaltung/der Kunde
     * gespeichert — der Benutzer bekommt einen Fehlerhinweis.
     */
    public static function sync(StorageFolderOwnerType $ownerType, string $ownerId, Request $request): void
    {
        if (self::$commands === null || $request->input('storage_folder_section') !== '1') {
            return;
        }

        try {
            $result = self::$commands->dispatch(new SyncStorageFolder(
                ownerType: $ownerType,
                ownerId: $ownerId,
                enabled: $request->input('storage_folder_enabled') === '1',
                storageTargetId: $request->input('storage_folder_target_id', '') ?? '',
                inCustomerFolder: $request->input('storage_folder_in_customer_folder') === '1',
                shareEnabled: $request->input('storage_folder_share_enabled') === '1',
                sharePermission: $request->input('storage_folder_share_permission', '') ?? '',
                regeneratePassword: $request->input('storage_folder_regenerate_password') === '1',
                resendMail: $request->input('storage_folder_resend_mail') === '1',
            ));
            assert($result instanceof StorageFolderSyncResult);

            if ($result->newPassword !== null && $result->folder !== null) {
                self::rememberPassword($result->folder, $result->newPassword);
            }
            if ($result->warnings !== []) {
                Flash::error('Ordner im Dateiablagesystem: ' . implode(' ', $result->warnings));
            }
        } catch (ValidationException $e) {
            Flash::error('Ordner im Dateiablagesystem: ' . implode(' ', $e->errors()));
        } catch (StorageUploadException | NotFoundException $e) {
            Flash::error('Ordner im Dateiablagesystem: ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log('StorageFolderForm::sync: ' . $e->getMessage());
            Flash::error('Ordner im Dateiablagesystem: Es ist ein unerwarteter Fehler aufgetreten.');
        }
    }

    /**
     * Das frisch erzeugte Freigabe-Passwort für genau EINE Anzeige auf der
     * nächsten Seite (siehe layout.php). Es liegt nur bis dahin in der
     * Session und wird nirgends dauerhaft gespeichert.
     *
     * @return array{password: string, folder: string, url: string}|null
     */
    public static function consumeOneTimePassword(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $value = $_SESSION[self::SESSION_PASSWORD_KEY] ?? null;
        unset($_SESSION[self::SESSION_PASSWORD_KEY]);

        return is_array($value) && is_string($value['password'] ?? null) ? $value : null;
    }

    private static function rememberPassword(StorageFolder $folder, string $password): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION[self::SESSION_PASSWORD_KEY] = [
            'password' => $password,
            'folder' => $folder->name(),
            'url' => (string) $folder->shareUrl,
        ];
    }
}
