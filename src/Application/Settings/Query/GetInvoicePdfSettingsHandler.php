<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\InvoicePdfSettings;
use App\Domain\Settings\InvoicePdfSettingsRepositoryInterface;

final class GetInvoicePdfSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly InvoicePdfSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): InvoicePdfSettings
    {
        assert($query instanceof GetInvoicePdfSettings);

        return $this->settings->get();
    }
}
