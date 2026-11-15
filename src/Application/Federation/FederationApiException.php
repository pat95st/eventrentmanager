<?php

declare(strict_types=1);

namespace App\Application\Federation;

use RuntimeException;

/** Error answered to a calling partner installation with the given HTTP status. */
final class FederationApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 400)
    {
        parent::__construct($message);
    }
}
