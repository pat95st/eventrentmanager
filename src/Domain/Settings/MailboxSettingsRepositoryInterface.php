<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface MailboxSettingsRepositoryInterface
{
    public function get(): MailboxSettings;

    public function save(MailboxSettings $settings): void;
}
