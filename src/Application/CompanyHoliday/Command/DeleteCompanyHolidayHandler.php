<?php

declare(strict_types=1);

namespace App\Application\CompanyHoliday\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CompanyHoliday\CompanyHolidayRepositoryInterface;

final class DeleteCompanyHolidayHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CompanyHolidayRepositoryInterface $holidays)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCompanyHoliday);
        $this->holidays->delete($command->id);

        return null;
    }
}
