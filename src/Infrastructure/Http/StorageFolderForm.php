<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Command\CommandBus;
use App\Application\DocumentStorage\Command\DeleteStorageFolder;
use App\Application\DocumentStorage\Command\RenameStorageFolder;
use App\Application\DocumentStorage\Command\SyncStorageFolder;
use App\Application\DocumentStorage\StorageFolderPaths;
use App\Application\DocumentStorage\StorageFolderShareNotifierInterface;
use App\Application\DocumentStorage\StorageFolderSyncResult;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\DocumentStorage\SharePasswordDelivery;
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
    private const SESSION_PASSWORD_SEND_KEY = 'storage_folder_password_send';
    private const SESSION_RENAME_KEY = 'storage_folder_rename_question';

    private static ?CommandBus $commands = null;
    private static ?StorageFolderRepositoryInterface $folders = null;
    private static ?DocumentStorageTargetRepositoryInterface $targets = null;
    private static ?StorageFolderPaths $paths = null;
    private static ?StorageFolderShareNotifierInterface $notifier = null;
    /** @var array<string, StorageFolder>|null */
    private static ?array $customerFolders = null;

    public static function configure(
        CommandBus $commands,
        StorageFolderRepositoryInterface $folders,
        DocumentStorageTargetRepositoryInterface $targets,
        StorageFolderPaths $paths,
        StorageFolderShareNotifierInterface $notifier,
    ): void {
        self::$commands = $commands;
        self::$folders = $folders;
        self::$targets = $targets;
        self::$paths = $paths;
        self::$notifier = $notifier;
        self::$customerFolders = null;
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
            $customerFolderTargets = $ownerType === StorageFolderOwnerType::Rental
                ? array_map(static fn (StorageFolder $f) => $f->storageTargetId, self::customerFolders())
                : [];
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
            // Nextcloud verschickt die Einladung selbst (Freigabe "per E-Mail").
            'nativeMailShare' => $folderTarget !== null && $folderTarget->protocol->supportsNativeMailShare(),
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
     * nächsten Seite (siehe layout.php), zusammen mit der Frage, ob und wie
     * es an den Kunden geschickt werden soll. Es wird nirgends dauerhaft
     * gespeichert: Nach der Anzeige bleibt es nur noch für genau den
     * nächsten Schritt (Versand) in der Session und verfällt sonst mit dem
     * nächsten Seitenaufruf.
     *
     * @return array{password: string, folder: string, nativeMailShare: bool, options: array<string, string>}|null
     */
    public static function consumeOneTimePassword(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        // Ein früher angezeigtes, nicht verschicktes Passwort verfällt jetzt.
        unset($_SESSION[self::SESSION_PASSWORD_SEND_KEY]);

        $value = $_SESSION[self::SESSION_PASSWORD_KEY] ?? null;
        unset($_SESSION[self::SESSION_PASSWORD_KEY]);
        if (!is_array($value) || !is_string($value['password'] ?? null)) {
            return null;
        }

        $options = [];
        $nativeMailShare = false;
        try {
            $ownerType = StorageFolderOwnerType::from((string) $value['ownerType']);
            $options = self::$notifier?->passwordDeliveryOptions(self::$paths->customerOf($ownerType, (string) $value['ownerId'])) ?? [];
            $info = self::folderInfo($ownerType, (string) $value['ownerId']);
            $nativeMailShare = $info !== null && (self::$targets?->findById($info['folder']->storageTargetId)?->protocol->supportsNativeMailShare() ?? false);
        } catch (Throwable $e) {
            error_log('StorageFolderForm: ' . $e->getMessage());
        }
        if ($options !== []) {
            $_SESSION[self::SESSION_PASSWORD_SEND_KEY] = $value;
        }

        return ['password' => $value['password'], 'folder' => (string) $value['folder'], 'nativeMailShare' => $nativeMailShare, 'options' => $options];
    }

    /**
     * Verschickt das zuletzt angezeigte Passwort auf dem vom Benutzer
     * gewählten Weg. Danach ist es endgültig aus der Session entfernt.
     *
     * @return string Seite, auf die zurückgeleitet wird
     */
    public static function sendPendingPassword(string $delivery): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $value = $_SESSION[self::SESSION_PASSWORD_SEND_KEY] ?? null;
        unset($_SESSION[self::SESSION_PASSWORD_SEND_KEY]);

        if (!is_array($value) || self::$notifier === null || self::$paths === null || self::$folders === null) {
            Flash::error('Das Passwort steht nicht mehr zum Versand bereit — es wird nur direkt nach dem Erzeugen angeboten. Beim Bearbeiten lässt sich ein neues erzeugen.');

            return '/';
        }

        $returnTo = self::returnPath((string) $value['ownerType'], (string) $value['ownerId']);
        try {
            $ownerType = StorageFolderOwnerType::from((string) $value['ownerType']);
            $method = SharePasswordDelivery::tryFrom($delivery);
            $folder = self::$folders->findByOwner($ownerType, (string) $value['ownerId']);
            if ($method === null || $folder === null) {
                throw new ValidationException(['delivery' => 'Bitte einen gültigen Versandweg wählen.']);
            }
            $customer = self::$paths->customerOf($ownerType, (string) $value['ownerId']);
            $label = self::$notifier->passwordDeliveryOptions($customer)[$method->value] ?? $method->label();
            self::$notifier->sendPassword($method, $customer, $folder, (string) $value['password']);
            Flash::success('Das Passwort wurde verschickt: ' . $label . '.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (Throwable $e) {
            Flash::error('Das Passwort konnte nicht verschickt werden: ' . $e->getMessage() . ' Beim Bearbeiten lässt sich ein neues erzeugen.');
        }

        return $returnTo;
    }

    // --- Umbenennen --------------------------------------------------------

    /**
     * Pfad, den der Ordner nach den aktuellen Daten hätte — VOR dem Speichern
     * einer Änderung aufrufen und das Ergebnis an askAboutRename() übergeben.
     * null: kein Ordner oder das Laufwerk kann nicht umbenennen.
     */
    public static function expectedPath(StorageFolderOwnerType $ownerType, string $ownerId): ?string
    {
        if (self::$folders === null || self::$targets === null || self::$paths === null) {
            return null;
        }

        try {
            $folder = self::$folders->findByOwner($ownerType, $ownerId);
            $target = $folder !== null ? self::$targets->findById($folder->storageTargetId) : null;
            if ($folder === null || $target === null || !$target->protocol->supportsFolderRename()) {
                return null;
            }
            $path = self::$paths->expectedPath($folder, $target);

            return $path !== '' ? $path : null;
        } catch (Throwable $e) {
            error_log('StorageFolderForm: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Nach dem Speichern: Hat DIESE Änderung den Namen verändert, den der
     * Ordner laut Namensregel hätte, wird der Benutzer auf der nächsten Seite
     * gefragt, ob der Ordner mit umbenannt werden soll (siehe layout.php).
     */
    public static function askAboutRename(StorageFolderOwnerType $ownerType, string $ownerId, ?string $expectedBefore): void
    {
        $expectedNow = self::expectedPath($ownerType, $ownerId);
        $info = $expectedNow !== null ? self::folderInfo($ownerType, $ownerId) : null;
        if ($expectedBefore === null || $expectedNow === null || $info === null
            || $expectedNow === $expectedBefore || $expectedNow === $info['folder']->path) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION[self::SESSION_RENAME_KEY] = [
            'ownerType' => $ownerType->value,
            'ownerId' => $ownerId,
            'from' => $info['folder']->path,
            'to' => $expectedNow,
            'targetName' => $info['targetName'],
        ];
    }

    /** @return array{ownerType: string, ownerId: string, from: string, to: string, targetName: string}|null */
    public static function pendingRenameQuestion(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $value = $_SESSION[self::SESSION_RENAME_KEY] ?? null;
        if (!is_array($value)) {
            return null;
        }
        // Die Frage wird einmal gestellt; die Antwort "umbenennen" darf
        // genau im nächsten Schritt noch eingelöst werden.
        if (($value['shown'] ?? false) === true) {
            unset($_SESSION[self::SESSION_RENAME_KEY]);

            return null;
        }
        $_SESSION[self::SESSION_RENAME_KEY]['shown'] = true;

        return $value;
    }

    /** Antwort "Ordner umbenennen" auf die Rückfrage. @return string Seite, auf die zurückgeleitet wird */
    public static function renamePending(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $value = $_SESSION[self::SESSION_RENAME_KEY] ?? null;
        unset($_SESSION[self::SESSION_RENAME_KEY]);

        if (!is_array($value) || self::$commands === null) {
            Flash::error('Die Rückfrage zum Umbenennen des Ordners ist nicht mehr aktuell.');

            return '/';
        }

        try {
            $newPath = self::$commands->dispatch(new RenameStorageFolder(
                StorageFolderOwnerType::from((string) $value['ownerType']),
                (string) $value['ownerId'],
            ));
            Flash::success('Der Ordner wurde umbenannt: ' . $newPath);
        } catch (Throwable $e) {
            Flash::error('Der Ordner konnte nicht umbenannt werden und behält seinen bisherigen Namen: ' . $e->getMessage());
        }

        return self::returnPath((string) $value['ownerType'], (string) $value['ownerId']);
    }

    // --- Löschen -----------------------------------------------------------

    /**
     * Attribute für das Löschen-Formular einer Veranstaltung/eines Kunden:
     * Gibt es einen Ordner, den das Laufwerk löschen kann, fragt layout.php
     * nach der eigentlichen Lösch-Bestätigung zusätzlich, ob auch der Ordner
     * gelöscht werden soll.
     */
    public static function deleteQuestionAttributes(StorageFolderOwnerType $ownerType, string $ownerId): string
    {
        try {
            $folder = $ownerType === StorageFolderOwnerType::Customer
                ? (self::customerFolders()[$ownerId] ?? null)
                : self::$folders?->findByOwner($ownerType, $ownerId);
            $target = $folder !== null ? self::$targets?->findById($folder->storageTargetId) : null;
        } catch (Throwable $e) {
            error_log('StorageFolderForm: ' . $e->getMessage());

            return '';
        }
        if ($folder === null || $target === null || !$target->protocol->supportsFolderDelete()) {
            return '';
        }

        $question = 'Zu ' . ($ownerType === StorageFolderOwnerType::Rental ? 'dieser Veranstaltung' : 'diesem Kunden')
            . ' gehört der Ordner „' . $folder->path . '“ auf „' . $target->name . '“. Soll er samt Inhalt'
            . ($ownerType === StorageFolderOwnerType::Customer ? ' (einschließlich darin liegender Veranstaltungsordner)' : '')
            . ' ebenfalls gelöscht werden?';

        return ' data-storage-folder-delete="' . e($question) . '"';
    }

    /** VOR dem Löschen des Datensatzes aufrufen: der Ordner, falls der Benutzer dessen Löschung bestätigt hat. */
    public static function folderToDelete(StorageFolderOwnerType $ownerType, string $ownerId, Request $request): ?StorageFolder
    {
        if ($request->input('delete_storage_folder') !== '1') {
            return null;
        }

        return self::folderInfo($ownerType, $ownerId)['folder'] ?? null;
    }

    /** NACH dem erfolgreichen Löschen des Datensatzes aufrufen. */
    public static function deleteFolder(?StorageFolder $folder): void
    {
        if ($folder === null || self::$commands === null) {
            return;
        }

        try {
            self::$commands->dispatch(new DeleteStorageFolder($folder));
        } catch (Throwable $e) {
            Flash::error('Der Ordner „' . $folder->path . '“ konnte nicht gelöscht werden und muss von Hand entfernt werden: ' . $e->getMessage());
        }
    }

    /** @return array<string, StorageFolder> */
    private static function customerFolders(): array
    {
        return self::$customerFolders ??= self::$folders?->customerFolders() ?? [];
    }

    private static function returnPath(string $ownerType, string $ownerId): string
    {
        return $ownerType === StorageFolderOwnerType::Rental->value ? '/veranstaltungen/' . rawurlencode($ownerId) : '/kunden';
    }

    private static function rememberPassword(StorageFolder $folder, string $password): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION[self::SESSION_PASSWORD_KEY] = [
            'password' => $password,
            'folder' => $folder->name(),
            'ownerType' => $folder->ownerType->value,
            'ownerId' => $folder->ownerId,
        ];
    }
}
