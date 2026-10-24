<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\AiAdImageSettings;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;

final class GetAiAdImageSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly AiAdImageSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): AiAdImageSettings
    {
        assert($query instanceof GetAiAdImageSettings);

        return $this->settings->get();
    }
}
