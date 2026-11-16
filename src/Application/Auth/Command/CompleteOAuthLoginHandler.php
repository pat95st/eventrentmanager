<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\ExternalUserProvisioner;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\ExternalIdentity;
use App\Domain\Auth\ExternalLoginException;
use App\Domain\Auth\OAuthClientInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ExternalLoginSettingsRepositoryInterface;
use App\Domain\User\User;

use function App\Infrastructure\Http\t;

final class CompleteOAuthLoginHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ExternalLoginSettingsRepositoryInterface $settings,
        private readonly OAuthClientInterface $client,
        private readonly ExternalUserProvisioner $provisioner,
    ) {
    }

    public function handle(CommandInterface $command): User
    {
        assert($command instanceof CompleteOAuthLogin);

        $settings = $this->settings->get();
        if (!$settings->oauthActive() || $command->code === '' || $command->codeVerifier === '') {
            throw new ValidationException(['credentials' => t('auth.login.error_oauth_failed')]);
        }

        try {
            $claims = $this->client->fetchClaims($settings, $command->code, $command->redirectUri, $command->codeVerifier);
        } catch (ExternalLoginException $e) {
            error_log('OAuth login failed: ' . $e->getMessage());

            throw new ValidationException(['credentials' => t('auth.login.error_oauth_failed')]);
        }

        $email = self::claim($claims, $settings->oauthEmailClaim);
        if ($email === null) {
            error_log('OAuth login failed: claim "' . $settings->oauthEmailClaim . '" is missing in the userinfo response.');

            throw new ValidationException(['credentials' => t('auth.login.error_external_no_email')]);
        }

        // Accounts are linked by e-mail address, so an address the provider
        // explicitly marks as unverified must not be trusted.
        $verified = $claims['email_verified'] ?? null;
        if ($verified === false || $verified === 'false' || $verified === 0 || $verified === '0') {
            throw new ValidationException(['credentials' => t('auth.login.error_oauth_email_unverified')]);
        }

        return $this->provisioner->resolve(new ExternalIdentity(
            email: $email,
            firstName: self::claim($claims, $settings->oauthFirstNameClaim),
            lastName: self::claim($claims, $settings->oauthLastNameClaim),
        ), $settings);
    }

    /** @param array<string, mixed> $claims */
    private static function claim(array $claims, string $name): ?string
    {
        $value = $claims[$name] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
