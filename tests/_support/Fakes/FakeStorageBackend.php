<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\DocumentStorage\StorageFolderShareNotifierInterface;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\SharePermission;
use App\Domain\DocumentStorage\StorageFolder;
use App\Infrastructure\DocumentStorage\PublicShare;
use App\Infrastructure\DocumentStorage\ShareClientInterface;
use App\Infrastructure\DocumentStorage\StorageClientFactoryInterface;
use App\Infrastructure\DocumentStorage\StorageClientInterface;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use App\Infrastructure\Mail\MailerException;

/**
 * Ein Speicherziel ohne Netzwerk: Factory, Datei-Client, Freigabe-Client und
 * Mail-Versand in einem, mit öffentlichem Protokoll dessen, was "auf dem
 * Server" passiert ist (siehe tests/README.md).
 */
final class FakeStorageBackend implements
    StorageClientFactoryInterface,
    StorageClientInterface,
    ShareClientInterface,
    StorageFolderShareNotifierInterface
{
    /** @var list<string> */
    public array $directories = [];
    /** @var array<string, array{path: string, permission: SharePermission, password: ?string}> */
    public array $shares = [];
    /** @var list<array{to: string, url: ?string}> */
    public array $mails = [];
    public bool $failDirectory = false;
    public bool $failShare = false;
    public bool $failMail = false;
    private int $nextShareId = 1;

    public function create(DocumentStorageTarget $target): StorageClientInterface
    {
        return $this;
    }

    public function createShareClient(DocumentStorageTarget $target): ?ShareClientInterface
    {
        return $target->protocol->supportsSharing() ? $this : null;
    }

    public function upload(string $contents, string $remotePath): void
    {
    }

    public function testConnection(): string
    {
        return 'ok';
    }

    public function createDirectory(string $remotePath): void
    {
        if ($this->failDirectory) {
            throw new StorageUploadException('Server nicht erreichbar.');
        }
        $this->directories[] = $remotePath;
    }

    public function createPublicLink(string $remotePath, SharePermission $permission, ?string $password, string $label): PublicShare
    {
        if ($this->failShare) {
            throw new StorageUploadException('Freigabe abgelehnt.');
        }
        $id = (string) $this->nextShareId++;
        $this->shares[$id] = ['path' => $remotePath, 'permission' => $permission, 'password' => $password];

        return new PublicShare($id, 'https://cloud.example/s/' . $id);
    }

    public function updatePermission(string $shareId, SharePermission $permission): void
    {
        $this->shares[$shareId]['permission'] = $permission;
    }

    public function updatePassword(string $shareId, string $password): void
    {
        $this->shares[$shareId]['password'] = $password;
    }

    public function deleteShare(string $shareId): void
    {
        if ($this->failShare) {
            throw new StorageUploadException('Server nicht erreichbar.');
        }
        unset($this->shares[$shareId]);
    }

    public function notify(string $recipientEmail, string $customerName, StorageFolder $folder): void
    {
        if ($this->failMail) {
            throw new MailerException('SMTP nicht erreichbar.');
        }
        $this->mails[] = ['to' => $recipientEmail, 'url' => $folder->shareUrl];
    }
}
