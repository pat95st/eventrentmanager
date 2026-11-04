<?php

declare(strict_types=1);

namespace App\Application\CompanyHoliday\Command;

use App\Application\Command\CommandInterface;

/** Betriebsurlaub anlegen ($id null) oder ändern (Migration 130). */
final class SaveCompanyHoliday implements CommandInterface
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $startDate, // 'Y-m-d'
        public readonly string $endDate,   // 'Y-m-d'
        public readonly ?string $note,
    ) {
    }
}
