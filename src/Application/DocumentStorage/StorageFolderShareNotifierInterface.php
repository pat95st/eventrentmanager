<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage;

use App\Domain\Customer\Customer;
use App\Domain\DocumentStorage\SharePasswordDelivery;
use App\Domain\DocumentStorage\StorageFolder;
use App\Infrastructure\Mail\MailerException;
use App\Infrastructure\Messaging\MessageSenderException;

/** Schickt dem Kunden den Freigabe-Link eines Ordners bzw. — auf ausdrücklichen Wunsch des Benutzers — das Passwort dazu. */
interface StorageFolderShareNotifierInterface
{
    /** @throws MailerException */
    public function notify(string $recipientEmail, string $customerName, StorageFolder $folder): void;

    /**
     * Wege, auf denen dieser Kunde ein Passwort bekommen kann: nur, was beim
     * Kunden hinterlegt UND in den Einstellungen eingerichtet ist.
     *
     * @return array<string, string> SharePasswordDelivery::value => Beschriftung inkl. Ziel, z. B. "E-Mail an anna@example.org"
     */
    public function passwordDeliveryOptions(Customer $customer): array;

    /**
     * @throws MailerException
     * @throws MessageSenderException
     */
    public function sendPassword(SharePasswordDelivery $delivery, Customer $customer, StorageFolder $folder, string $password): void;
}
