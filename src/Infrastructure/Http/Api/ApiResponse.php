<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

/**
 * Immutable description of an API response. API controllers return it instead
 * of writing to the output directly (unlike Response::json(), which exits),
 * so they can be unit-tested; ApiEmitter sends it at the very end.
 */
final class ApiResponse
{
    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    private function __construct(
        public readonly int $status,
        public readonly ?array $json = null,
        public readonly array $headers = [],
        public readonly ?string $content = null,
        public readonly ?string $contentType = null,
        public readonly ?string $filePath = null,
        public readonly ?string $fileName = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self($status, $data);
    }

    /** Wraps a single resource as {"data": …}. */
    public static function data(mixed $data, int $status = 200): self
    {
        return new self($status, ['data' => $data]);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    /** 202 without body, e.g. for JSON-RPC notifications (MCP server). */
    public static function accepted(): self
    {
        return new self(202);
    }

    /** @param array<string, string> $errors field => message (validation) */
    public static function error(int $status, string $message, array $errors = []): self
    {
        $body = ['error' => $message];
        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return new self($status, $body);
    }

    /** In-memory file content, e.g. a generated PDF. */
    public static function content(string $content, string $contentType, ?string $fileName = null): self
    {
        return new self(200, null, [], $content, $contentType, null, $fileName);
    }

    /** File on disk (supports range requests when emitted). */
    public static function file(string $absolutePath, string $fileName, string $contentType): self
    {
        return new self(200, null, [], null, $contentType, $absolutePath, $fileName);
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, $this->json, array_merge($this->headers, $headers), $this->content, $this->contentType, $this->filePath, $this->fileName);
    }

    /** Body as array (JSON responses only) — convenience for tests. */
    public function body(): array
    {
        return $this->json ?? [];
    }
}
