<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Auth\ExternalLoginException;
use App\Domain\Auth\OAuthClientInterface;
use App\Domain\Settings\ExternalLoginSettings;

/** Identity provider that accepts exactly one authorization code. */
final class FakeOAuthClient implements OAuthClientInterface
{
    public const VALID_CODE = 'valid-code';

    /** @var list<string> issuers asked for their discovery document */
    public array $discovered = [];

    /** @param array<string, mixed> $claims */
    public function __construct(public array $claims = [], public bool $discoveryFails = false)
    {
    }

    public function discover(string $issuerUrl): array
    {
        $this->discovered[] = $issuerUrl;
        if ($this->discoveryFails) {
            throw new ExternalLoginException('HTTP 404');
        }

        return [
            'authorization' => $issuerUrl . '/authorize',
            'token' => $issuerUrl . '/token',
            'userinfo' => $issuerUrl . '/userinfo',
        ];
    }

    public function fetchClaims(ExternalLoginSettings $settings, string $code, string $redirectUri, string $codeVerifier): array
    {
        if ($code !== self::VALID_CODE) {
            throw new ExternalLoginException('invalid_grant');
        }

        return $this->claims;
    }
}
