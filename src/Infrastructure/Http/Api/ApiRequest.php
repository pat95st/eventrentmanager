<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Domain\Api\ApiToken;

/**
 * Request as seen by API controllers: route parameters, query string, the
 * decoded body (JSON object, or form fields for multipart uploads) and the
 * authenticated token.
 */
final class ApiRequest
{
    /**
     * @param array<string, string> $routeParams
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $routeParams,
        public readonly array $query,
        public readonly array $body,
        public readonly array $files,
        public readonly ApiToken $token,
    ) {
    }

    public function param(string $name): string
    {
        return $this->routeParams[$name] ?? '';
    }

    public function query(string $name, ?string $default = null): ?string
    {
        $value = $this->query[$name] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->body);
    }

    public function input(string $field, mixed $default = null): mixed
    {
        return $this->body[$field] ?? $default;
    }

    /** Uploaded file in PHP's $_FILES shape, or null. */
    public function file(string $name): ?array
    {
        $file = $this->files[$name] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }
}
