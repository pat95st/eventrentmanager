<?php

declare(strict_types=1);

namespace App\Application\OAuth;

use RuntimeException;

/**
 * OAuth error response (RFC 6749 section 4.1.2.1 / 5.2, RFC 7591 section 3.2.2).
 * With $redirectUri (only set once client and redirect URI are verified) the
 * error is sent back to the client; otherwise it is shown to the user.
 */
final class OAuthError extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly int $status = 400,
        public readonly ?string $redirectUri = null,
        public readonly ?string $state = null,
    ) {
        parent::__construct($error . ': ' . $description);
    }

    /** Redirect back to the client with the error, or null when it must be shown to the user. */
    public function redirectUrl(string $issuer): ?string
    {
        return $this->redirectUri !== null
            ? AuthorizationRequest::buildRedirectUrl($this->redirectUri, $this->state, ['error' => $this->error, 'error_description' => $this->description, 'iss' => $issuer])
            : null;
    }

    /** @return array{error: string, error_description: string} */
    public function body(): array
    {
        return ['error' => $this->error, 'error_description' => $this->description];
    }
}
