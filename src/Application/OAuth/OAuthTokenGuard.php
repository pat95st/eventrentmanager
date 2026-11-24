<?php

declare(strict_types=1);

namespace App\Application\OAuth;

use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;
use App\Domain\OAuth\OAuthScopePolicy;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

/**
 * Checks tokens issued via OAuth on every request (wired into ApiKernel):
 * expired → rejected; owner deleted or deactivated → rejected; scopes are
 * narrowed to what the owner's current permissions allow, so withdrawn
 * permissions take effect at once. Other tokens pass unchanged.
 */
final class OAuthTokenGuard
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function __invoke(ApiToken $token, DateTimeImmutable $now): ?ApiToken
    {
        if (!$token->isOAuth()) {
            return $token;
        }
        if ($token->isExpired($now) || $token->userId === null) {
            return null;
        }
        $user = $this->users->findById($token->userId);
        if ($user === null || !$user->isActive) {
            return null;
        }
        $allowed = OAuthScopePolicy::allowedScopes($user);

        return $token->withScopes(array_values(array_filter($token->scopes, static fn (ApiScope $s) => in_array($s, $allowed, true))));
    }
}
