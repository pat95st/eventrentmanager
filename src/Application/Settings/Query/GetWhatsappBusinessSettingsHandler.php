<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\WhatsappBusinessSettings;
use App\Domain\Settings\WhatsappBusinessSettingsRepositoryInterface;

final class GetWhatsappBusinessSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly WhatsappBusinessSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): WhatsappBusinessSettings
    {
        assert($query instanceof GetWhatsappBusinessSettings);

        return $this->repository->get();
    }
}
