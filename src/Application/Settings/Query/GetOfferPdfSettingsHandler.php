<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\OfferPdfSettings;
use App\Domain\Settings\OfferPdfSettingsRepositoryInterface;

final class GetOfferPdfSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly OfferPdfSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): OfferPdfSettings
    {
        assert($query instanceof GetOfferPdfSettings);

        return $this->settings->get();
    }
}
