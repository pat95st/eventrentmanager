<?php

declare(strict_types=1);

namespace App\Application\Federation;

/** Port for server-to-server calls to a partner installation. */
interface FederationClientInterface
{
    /**
     * @param array<string, mixed>|null $body JSON body (POST)
     * @return array<string, mixed> decoded JSON response
     * @throws FederationException on network errors and non-2xx responses
     */
    public function request(string $method, string $baseUrl, string $path, ?string $token = null, ?array $body = null): array;
}
