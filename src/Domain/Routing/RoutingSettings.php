<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/** Settings of the route planning (migration 178). */
final class RoutingSettings
{
    public function __construct(
        public ?string $apiKey = null,      // OpenRouteService
        public int $bufferMinutes = 30,     // between teardown + driving time and the next setup
    ) {
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== null && trim($this->apiKey) !== '';
    }
}
