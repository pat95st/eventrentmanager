<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

final class CloudHttpResponse
{
    /** @param array<string, string> $headers Header names in lower case. */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** @return array<string, mixed>|null */
    public function json(): ?array
    {
        if ($this->body === '') {
            return null;
        }
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
