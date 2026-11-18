<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use RuntimeException;

/** Thrown by API controllers to answer with a specific status and message. */
final class ApiException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly int $status, string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public static function notFound(string $message = 'Nicht gefunden.'): self
    {
        return new self(404, $message);
    }

    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }
}
