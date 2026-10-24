<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\RentalReminderSettings;
use App\Domain\Settings\RentalReminderSettingsRepositoryInterface;

final class GetRentalReminderSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly RentalReminderSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): RentalReminderSettings
    {
        assert($query instanceof GetRentalReminderSettings);

        return $this->settings->get();
    }
}
