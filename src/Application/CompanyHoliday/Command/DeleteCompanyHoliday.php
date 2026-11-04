<?php

declare(strict_types=1);

namespace App\Application\CompanyHoliday\Command;

use App\Application\Command\CommandInterface;

final class DeleteCompanyHoliday implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
