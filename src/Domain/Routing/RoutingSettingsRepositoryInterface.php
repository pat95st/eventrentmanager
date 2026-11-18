<?php

declare(strict_types=1);

namespace App\Domain\Routing;

interface RoutingSettingsRepositoryInterface
{
    public function get(): RoutingSettings;

    public function save(RoutingSettings $settings): void;
}
