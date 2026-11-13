<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage;

use App\Domain\DocumentStorage\StorageFolder;
use App\Infrastructure\Mail\MailerException;

/** Schickt dem Kunden den Freigabe-Link eines Ordners. */
interface StorageFolderShareNotifierInterface
{
    /** @throws MailerException */
    public function notify(string $recipientEmail, string $customerName, StorageFolder $folder): void;
}
