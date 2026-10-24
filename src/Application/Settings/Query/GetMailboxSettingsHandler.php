<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\MailboxSettings;
use App\Domain\Settings\MailboxSettingsRepositoryInterface;

final class GetMailboxSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly MailboxSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): MailboxSettings
    {
        assert($query instanceof GetMailboxSettings);

        return $this->settings->get();
    }
}
