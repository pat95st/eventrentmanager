<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

interface AudioSettingsRepositoryInterface
{
    public function get(): AudioSettings;

    public function save(AudioSettings $settings): void;
}
