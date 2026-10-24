<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

interface MessagingSettingsRepositoryInterface
{
    public function get(): MessagingSettings;

    public function save(MessagingSettings $settings): void;
}
