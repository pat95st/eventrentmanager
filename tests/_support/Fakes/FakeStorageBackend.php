<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\DocumentStorage\StorageFolderShareNotifierInterface;
use App\Domain\Customer\Customer;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\SharePasswordDelivery;
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
    /** @var array<string, array{path: string, permission: SharePermission, password: ?string, mailTo: ?string}> */
    public array $shares = [];
    /** @var list<array{to: string, url: ?string}> */
    public array $mails = [];
    /** @var list<array{delivery: string, password: string}> */
    public array $sentPasswords = [];
    /** @var array<string, string> */
    public array $passwordOptions = ['email' => 'E-Mail an anna@example.org'];
    public bool $failMove = false;
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
        $this->shares[$id] = ['path' => $remotePath, 'permission' => $permission, 'password' => $password, 'mailTo' => null];

        return new PublicShare($id, 'https://cloud.example/s/' . $id);
    }

    public function createMailShare(string $remotePath, string $email, SharePermission $permission, ?string $password, string $label): PublicShare
    {
        $share = $this->createPublicLink($remotePath, $permission, $password, $label);
        $this->shares[$share->id]['mailTo'] = $email;

        return $share;
    }

    public function moveDirectory(string $fromPath, string $toPath): void
    {
        if ($this->failMove || in_array($toPath, $this->directories, true)) {
            throw new StorageUploadException('Ziel existiert bereits.');
        }
        foreach ($this->directories as $i => $directory) {
            if ($directory === $fromPath || str_starts_with($directory, $fromPath . '/')) {
                $this->directories[$i] = $toPath . substr($directory, strlen($fromPath));
            }
        }
    }

    public function deleteDirectory(string $remotePath): void
    {
        if ($this->failDirectory) {
            throw new StorageUploadException('Server nicht erreichbar.');
        }
        $this->directories = array_values(array_filter(
            $this->directories,
            static fn (string $d) => $d !== $remotePath && !str_starts_with($d, $remotePath . '/'),
        ));
    }

    public function passwordDeliveryOptions(Customer $customer): array
    {
        return $this->passwordOptions;
    }

    public function sendPassword(SharePasswordDelivery $delivery, Customer $customer, StorageFolder $folder, string $password): void
    {
        $this->sentPasswords[] = ['delivery' => $delivery->value, 'password' => $password];
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
