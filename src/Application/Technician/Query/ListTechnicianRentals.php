<?php

declare(strict_types=1);

namespace App\Application\Technician\Query;

use App\Application\Query\QueryInterface;

/** Veranstaltungen, für die ein Techniker angefragt oder eingeplant ist (ohne Absagen, optional inkl. vergangener). */
final class ListTechnicianRentals implements QueryInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly bool $includePast = false,
    ) {
    }
}
