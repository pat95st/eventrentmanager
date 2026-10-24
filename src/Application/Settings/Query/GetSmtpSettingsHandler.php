<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\SmtpSettings;
use App\Domain\Settings\SmtpSettingsRepositoryInterface;

final class GetSmtpSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly SmtpSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): SmtpSettings
    {
        assert($query instanceof GetSmtpSettings);

        return $this->settings->get();
    }
}
