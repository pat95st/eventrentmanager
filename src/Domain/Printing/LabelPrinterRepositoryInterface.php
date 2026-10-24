<?php

declare(strict_types=1);

namespace App\Domain\Printing;

interface LabelPrinterRepositoryInterface
{
    public function save(LabelPrinter $printer): void;

    public function findById(string $id): ?LabelPrinter;

    /** @return LabelPrinter[] alphabetisch nach Name */
    public function findAll(): array;

    public function delete(string $id): void;
}
