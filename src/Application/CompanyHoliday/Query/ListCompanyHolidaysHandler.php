<?php

declare(strict_types=1);

namespace App\Application\CompanyHoliday\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\CompanyHoliday\CompanyHoliday;
use App\Domain\CompanyHoliday\CompanyHolidayRepositoryInterface;

final class ListCompanyHolidaysHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CompanyHolidayRepositoryInterface $holidays)
    {
    }

    /** @return CompanyHoliday[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListCompanyHolidays);

        return $this->holidays->findAll();
    }
}
