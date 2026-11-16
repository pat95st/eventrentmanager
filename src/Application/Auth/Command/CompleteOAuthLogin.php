<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/** Second half of the OAuth login: the identity provider redirected back with a code. */
final class CompleteOAuthLogin implements CommandInterface
{
    public function __construct(
        public readonly string $code,
        public readonly string $redirectUri,
        public readonly string $codeVerifier,
    ) {
    }
}
