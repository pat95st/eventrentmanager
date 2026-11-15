<?php

declare(strict_types=1);

namespace App\Application\Federation;

use RuntimeException;

/** Calling a partner installation failed (network error or error response). */
final class FederationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 0)
    {
        parent::__construct($message);
    }
}
