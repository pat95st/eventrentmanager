<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

/** Wozu ein Ordner im Dateiablagesystem gehört (siehe StorageFolder). */
enum StorageFolderOwnerType: string
{
    case Rental = 'rental';
    case Customer = 'customer';
}
