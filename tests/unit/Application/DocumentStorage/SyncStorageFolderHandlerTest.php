<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DocumentStorage;

use App\Application\DocumentStorage\Command\SyncStorageFolder;
use App\Application\DocumentStorage\Command\SyncStorageFolderHandler;
use App\Application\DocumentStorage\StorageFolderSyncResult;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerType;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\SharePermission;
use App\Domain\DocumentStorage\StorageFolderOwnerType;
use App\Domain\DocumentStorage\StorageProtocol;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\Rental;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeStorageBackend;
use Tests\Support\Fakes\InMemoryCustomerRepository;
use Tests\Support\Fakes\InMemoryDocumentStorageTargetRepository;
use Tests\Support\Fakes\InMemoryRentalRepository;
use Tests\Support\Fakes\InMemoryStorageFolderRepository;

/** Ordner für Veranstaltungen/Kunden samt Freigabe, Passwort und Mail — gegen ein Speicherziel ohne Netzwerk. */
final class SyncStorageFolderHandlerTest extends Unit
{
    private InMemoryStorageFolderRepository $folders;
    private InMemoryDocumentStorageTargetRepository $targets;
    private InMemoryRentalRepository $rentals;
    private InMemoryCustomerRepository $customers;
    private FakeStorageBackend $backend;
    private SyncStorageFolderHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folders = new InMemoryStorageFolderRepository();
        $this->targets = new InMemoryDocumentStorageTargetRepository();
        $this->rentals = new InMemoryRentalRepository();
        $this->customers = new InMemoryCustomerRepository();
        $this->backend = new FakeStorageBackend();
        $this->handler = new SyncStorageFolderHandler($this->folders, $this->targets, $this->rentals, $this->customers, $this->backend, $this->backend);

        $now = new DateTimeImmutable();
        $this->customers->save(new Customer(
            id: 'customer-1',
            customerNumber: 'K-0007',
            type: CustomerType::Geschaeftlich,
            companyName: 'Müller / Söhne GmbH',
            firstName: 'Anna',
            lastName: 'Müller',
            street: 'Weg 1',
            addressLine2: null,
            postalCode: '12345',
            city: 'Musterstadt',
            phone: null,
            email: 'anna@example.org',
            telegramChatId: null,
            createdAt: $now,
            updatedAt: $now,
        ));
        $this->rentals->save(new Rental(
            id: 'rental-1',
            customerId: 'customer-1',
            startDate: new DateTimeImmutable('2026-06-13'),
            endDate: new DateTimeImmutable('2026-06-14'),
            eventName: 'Sommerfest 26/27',
            eventLocation: null,
            venueId: null,
            deploymentDays: 2,
            createdAt: $now,
            updatedAt: $now,
        ));
    }

    public function testCreatesEventFolderFromTheTargetsNamingRule(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);

        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1');

        $this->assertSame(['Veranstaltungen/2026/2026-06-13 Sommerfest 26-27'], $this->backend->directories);
        $this->assertSame('Veranstaltungen/2026/2026-06-13 Sommerfest 26-27', $result->folder?->path);
        $this->assertFalse($result->folder->isShared());
        $this->assertSame([], $this->backend->shares);
        $this->assertNull($result->newPassword);
    }

    public function testFolderIsCreatedOnlyOnceAndKeepsItsPathAfterRenaming(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        $this->sync(StorageFolderOwnerType::Rental, 'rental-1');

        $this->rentals->findById('rental-1')->eventName = 'Umbenannt';
        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1');

        $this->assertCount(1, $this->backend->directories);
        $this->assertSame('Veranstaltungen/2026/2026-06-13 Sommerfest 26-27', $result->folder?->path);
    }

    public function testCustomerFolderAndEventAsItsSubfolder(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        // Ein zweites Laufwerk: der Unterordner muss trotzdem auf dem des Kundenordners landen.
        $this->target('sftp', StorageProtocol::Sftp);

        $this->sync(StorageFolderOwnerType::Customer, 'customer-1', targetId: 'nc');
        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', targetId: 'sftp', inCustomerFolder: true);

        $this->assertSame(
            ['Kunden/K-0007 Müller - Söhne GmbH', 'Kunden/K-0007 Müller - Söhne GmbH/2026-06-13 Sommerfest 26-27'],
            $this->backend->directories,
        );
        $this->assertSame('nc', $result->folder?->storageTargetId);
        $this->assertTrue($result->folder->inCustomerFolder);
    }

    public function testSubfolderRequiresAnExistingCustomerFolder(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);

        $this->expectException(ValidationException::class);
        $this->sync(StorageFolderOwnerType::Rental, 'rental-1', inCustomerFolder: true);
    }

    public function testTargetMustBeEnabledForFolders(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud, foldersEnabled: false);

        try {
            $this->sync(StorageFolderOwnerType::Rental, 'rental-1', targetId: 'nc');
            $this->fail('Erwartete ValidationException.');
        } catch (ValidationException) {
            $this->assertSame([], $this->backend->directories);
            $this->assertNull($this->folders->findByOwner(StorageFolderOwnerType::Rental, 'rental-1'));
        }
    }

    public function testTargetMustBeChosenWhenSeveralOfferFolders(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        $this->target('sftp', StorageProtocol::Sftp);

        $this->expectException(ValidationException::class);
        $this->sync(StorageFolderOwnerType::Rental, 'rental-1');
    }

    public function testFailedDirectoryCreationLeavesNothingBehind(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        $this->backend->failDirectory = true;

        try {
            $this->sync(StorageFolderOwnerType::Rental, 'rental-1');
            $this->fail('Erwartete StorageUploadException.');
        } catch (StorageUploadException) {
            $this->assertNull($this->folders->findByOwner(StorageFolderOwnerType::Rental, 'rental-1'));
        }
    }

    public function testSharingCreatesLinkWithPermissionAndMailsTheCustomer(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);

        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true, permission: 'upload');

        $this->assertCount(1, $this->backend->shares);
        $share = $this->backend->shares['1'];
        $this->assertSame('Veranstaltungen/2026/2026-06-13 Sommerfest 26-27', $share['path']);
        $this->assertSame(SharePermission::Upload, $share['permission']);
        $this->assertNull($share['password']);
        $this->assertNull($result->newPassword);
        $this->assertSame([['to' => 'anna@example.org', 'url' => 'https://cloud.example/s/1']], $this->backend->mails);
        $this->assertSame('anna@example.org', $result->folder?->shareMailedTo);
        $this->assertFalse($result->folder->shareHasPassword);
        $this->assertSame([], $result->warnings);
    }

    public function testAlwaysPasswordSettingGeneratesPasswordByPolicyAndReturnsItOnce(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud, alwaysPassword: true, passwordLength: 24);

        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);

        $this->assertNotNull($result->newPassword);
        $this->assertSame(24, strlen($result->newPassword));
        $this->assertSame($result->newPassword, $this->backend->shares['1']['password']);
        $this->assertTrue($result->folder?->shareHasPassword);

        // Beim nächsten Speichern gibt es kein Passwort mehr und keine zweite Freigabe/Mail.
        $again = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);
        $this->assertNull($again->newPassword);
        $this->assertCount(1, $this->backend->shares);
        $this->assertCount(1, $this->backend->mails);
    }

    public function testRegeneratingPasswordOnEditReplacesItOnTheServer(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud, alwaysPassword: true);
        $first = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);

        $second = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true, regenerate: true);

        $this->assertNotNull($second->newPassword);
        $this->assertNotSame($first->newPassword, $second->newPassword);
        $this->assertSame($second->newPassword, $this->backend->shares['1']['password']);
        $this->assertCount(1, $this->backend->mails, 'Ein neues Passwort verschickt keine neue Mail.');
    }

    public function testRegeneratingAlsoProtectsAShareCreatedWithoutPassword(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);

        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true, regenerate: true);

        $this->assertNotNull($result->newPassword);
        $this->assertTrue($result->folder?->shareHasPassword);
    }

    public function testPermissionChangeAndResend(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true, permission: 'read');

        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true, permission: 'edit', resend: true);

        $this->assertSame(SharePermission::Edit, $this->backend->shares['1']['permission']);
        $this->assertSame(SharePermission::Edit, $result->folder?->sharePermission);
        $this->assertCount(2, $this->backend->mails);
    }

    public function testDisablingShareRevokesLinkButKeepsFolder(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);

        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: false);

        $this->assertSame([], $this->backend->shares);
        $this->assertNotNull($result->folder);
        $this->assertFalse($result->folder->isShared());
        $this->assertNull($result->folder->shareUrl);
    }

    public function testUncheckingFolderRevokesShareAndRemovesLink(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);

        $result = $this->handler->handle(new SyncStorageFolder(StorageFolderOwnerType::Rental, 'rental-1', enabled: false));

        $this->assertNull($result->folder);
        $this->assertSame([], $this->backend->shares);
        $this->assertNull($this->folders->findByOwner(StorageFolderOwnerType::Rental, 'rental-1'));
    }

    public function testUncheckingWarnsWhenShareCannotBeRevoked(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);
        $this->backend->failShare = true;

        $result = $this->handler->handle(new SyncStorageFolder(StorageFolderOwnerType::Rental, 'rental-1', enabled: false));

        $this->assertCount(1, $result->warnings);
        $this->assertCount(1, $this->backend->shares);
        $this->assertNull($this->folders->findByOwner(StorageFolderOwnerType::Rental, 'rental-1'));
    }

    public function testShareIsIgnoredOnBackendsWithoutSharing(): void
    {
        $this->target('sftp', StorageProtocol::Sftp, alwaysPassword: true);

        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);

        $this->assertNotNull($result->folder);
        $this->assertFalse($result->folder->isShared());
        $this->assertSame([], $this->backend->shares);
        $this->assertSame([], $this->backend->mails);
        $this->assertNull($result->newPassword);
    }

    public function testMissingCustomerEmailStillCreatesLinkAndWarns(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud, alwaysPassword: true);
        $this->customers->findById('customer-1')->email = null;

        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);

        $this->assertCount(1, $this->backend->shares);
        $this->assertSame([], $this->backend->mails);
        $this->assertCount(1, $result->warnings);
        $this->assertNotNull($result->newPassword, 'Das Passwort darf trotz Warnung nicht verloren gehen.');
        $this->assertNull($result->folder?->shareMailedAt);
    }

    public function testMailFailureKeepsShareAndPassword(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud, alwaysPassword: true);
        $this->backend->failMail = true;

        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);

        $this->assertTrue($result->folder?->isShared());
        $this->assertNotNull($result->newPassword);
        $this->assertCount(1, $result->warnings);
    }

    public function testFailedShareKeepsTheFolderSoTheNextSaveContinues(): void
    {
        $this->target('nc', StorageProtocol::Nextcloud);
        $this->backend->failShare = true;

        try {
            $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);
            $this->fail('Erwartete StorageUploadException.');
        } catch (StorageUploadException) {
        }

        $this->backend->failShare = false;
        $result = $this->sync(StorageFolderOwnerType::Rental, 'rental-1', share: true);

        $this->assertCount(1, $this->backend->directories);
        $this->assertTrue($result->folder?->isShared());
    }

    public function testCustomerFolderCanBeSharedToo(): void
    {
        $this->target('oc', StorageProtocol::OwnCloud, alwaysPassword: true);

        $result = $this->sync(StorageFolderOwnerType::Customer, 'customer-1', share: true);

        $this->assertSame('Kunden/K-0007 Müller - Söhne GmbH', $this->backend->shares['1']['path']);
        $this->assertNotNull($result->newPassword);
        $this->assertSame('anna@example.org', $this->backend->mails[0]['to']);
    }

    private function sync(
        StorageFolderOwnerType $type,
        string $ownerId,
        string $targetId = '',
        bool $inCustomerFolder = false,
        bool $share = false,
        string $permission = 'read',
        bool $regenerate = false,
        bool $resend = false,
    ): StorageFolderSyncResult {
        return $this->handler->handle(new SyncStorageFolder($type, $ownerId, true, $targetId, $inCustomerFolder, $share, $permission, $regenerate, $resend));
    }

    private function target(string $id, StorageProtocol $protocol, bool $foldersEnabled = true, bool $alwaysPassword = false, int $passwordLength = 16): void
    {
        $now = new DateTimeImmutable();
        $this->targets->save(new DocumentStorageTarget(
            id: $id,
            name: strtoupper($id),
            protocol: $protocol,
            host: 'cloud.example',
            port: null,
            username: 'verleih',
            password: 'geheim',
            privateKey: null,
            privateKeyPassphrase: null,
            basePath: null,
            webdavUseHttps: true,
            webdavAllowSelfSigned: false,
            s3AccessKeyId: null,
            s3SecretAccessKey: null,
            s3Bucket: null,
            s3Region: null,
            s3Endpoint: null,
            s3UsePathStyle: false,
            enabled: true,
            createdAt: $now,
            updatedAt: $now,
            foldersEnabled: $foldersEnabled,
            shareAlwaysPassword: $alwaysPassword,
            sharePasswordLength: $passwordLength,
        ));
    }
}
