<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\LegalSettings;
use App\Domain\Settings\LegalSettingsRepositoryInterface;

final class GetLegalSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly LegalSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): LegalSettings
    {
        assert($query instanceof GetLegalSettings);

        return $this->settings->get();
    }
}
