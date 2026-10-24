<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\PdfDesignSettings;
use App\Domain\Settings\PdfDesignSettingsRepositoryInterface;

final class GetPdfDesignSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PdfDesignSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): PdfDesignSettings
    {
        assert($query instanceof GetPdfDesignSettings);

        return $this->settings->get();
    }
}
