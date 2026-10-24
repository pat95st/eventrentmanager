<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\InvoiceDueReminderSettings;
use App\Domain\Settings\InvoiceDueReminderSettingsRepositoryInterface;

final class GetInvoiceDueReminderSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly InvoiceDueReminderSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): InvoiceDueReminderSettings
    {
        assert($query instanceof GetInvoiceDueReminderSettings);

        return $this->settings->get();
    }
}
