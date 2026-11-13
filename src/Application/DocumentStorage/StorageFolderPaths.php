<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\FolderPathTemplate;
use App\Domain\DocumentStorage\StorageFolder;
use App\Domain\DocumentStorage\StorageFolderOwnerType;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalRepositoryInterface;

/**
 * Welchen Pfad die Namensregeln eines Ziellaufwerks für eine Veranstaltung
 * bzw. einen Kunden JETZT ergeben — beim Anlegen des Ordners und um nach
 * einer Umbenennung zu erkennen, dass der Ordner nicht mehr dazu passt.
 */
final class StorageFolderPaths
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    /** Der Kunde, dem der Ordner gehört bzw. zu dessen Veranstaltung er gehört (Empfänger von Freigabe und Passwort). */
    public function customerOf(StorageFolderOwnerType $ownerType, string $ownerId): Customer
    {
        return $this->owner($ownerType, $ownerId)[0];
    }

    /**
     * @return array{0: Customer, 1: array<string, string>} Kunde und die Platzhalterwerte für die Pfadvorlage
     */
    public function owner(StorageFolderOwnerType $ownerType, string $ownerId): array
    {
        if ($ownerType === StorageFolderOwnerType::Customer) {
            $customer = $this->customers->findById($ownerId);
            if ($customer === null) {
                throw new NotFoundException("Kunde \"{$ownerId}\" wurde nicht gefunden.");
            }

            return [$customer, FolderPathTemplate::customerValues($customer)];
        }

        $rental = $this->rentals->findById($ownerId);
        if ($rental === null) {
            throw new NotFoundException("Vermietung \"{$ownerId}\" wurde nicht gefunden.");
        }
        $customer = $this->customers->findById($rental->customerId);
        if ($customer === null) {
            throw new NotFoundException("Kunde \"{$rental->customerId}\" wurde nicht gefunden.");
        }

        return [$customer, FolderPathTemplate::eventValues($rental, $customer)];
    }

    /**
     * Pfad, den ein bereits angelegter Ordner nach den aktuellen Daten hätte.
     * Ein Unterordner des Kundenordners bleibt in seinem jetzigen
     * Elternordner — nur sein eigener Name wird neu gebildet. Leerer String:
     * die Vorlage ergibt keinen Namen.
     */
    public function expectedPath(StorageFolder $folder, DocumentStorageTarget $target): string
    {
        [, $values] = $this->owner($folder->ownerType, $folder->ownerId);

        if ($folder->inCustomerFolder) {
            $name = FolderPathTemplate::resolve($target->eventSubfolderTemplate, $values);
            $parent = dirname($folder->path);

            return $name === '' ? '' : ($parent === '.' || $parent === '' ? $name : $parent . '/' . $name);
        }

        return FolderPathTemplate::resolve(
            $folder->ownerType === StorageFolderOwnerType::Rental ? $target->eventFolderTemplate : $target->customerFolderTemplate,
            $values,
        );
    }
}
