<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\InvoiceSettings;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;

final class GetInvoiceSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly InvoiceSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): InvoiceSettings
    {
        assert($query instanceof GetInvoiceSettings);

        return $this->settings->get();
    }
}
