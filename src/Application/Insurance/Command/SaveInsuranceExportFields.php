<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

use App\Application\Command\CommandInterface;

/** Gewählte Spalten des Versicherungslisten-Exports als Vorauswahl merken. */
final class SaveInsuranceExportFields implements CommandInterface
{
    /** @param string[] $fields Werte von App\Domain\Insurance\InsuranceField */
    public function __construct(public readonly array $fields)
    {
    }
}
