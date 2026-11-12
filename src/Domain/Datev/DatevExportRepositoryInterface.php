<?php

declare(strict_types=1);

namespace App\Domain\Datev;

use DateTimeImmutable;

interface DatevExportRepositoryInterface
{
    public function save(DatevExport $export): void;

    /** @return DatevExport[] neueste zuerst */
    public function findRecent(int $limit): array;

    public function existsAutoSentForPeriod(DateTimeImmutable $from, DateTimeImmutable $to): bool;
}
