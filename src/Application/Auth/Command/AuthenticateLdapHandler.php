<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\ExternalUserProvisioner;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\ExternalLoginException;
use App\Domain\Auth\LdapDirectoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ExternalLoginSettingsRepositoryInterface;
use App\Domain\User\User;

use function App\Infrastructure\Http\t;

final class AuthenticateLdapHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ExternalLoginSettingsRepositoryInterface $settings,
        private readonly LdapDirectoryInterface $directory,
        private readonly ExternalUserProvisioner $provisioner,
    ) {
    }

    public function handle(CommandInterface $command): User
    {
        assert($command instanceof AuthenticateLdap);

        $settings = $this->settings->get();
        $login = trim($command->login);

        // An empty password must never reach the directory: many servers
        // treat a bind with a DN but without password as a successful
        // "unauthenticated bind".
        if (!$settings->ldapActive() || $login === '' || $command->password === '') {
            throw new ValidationException(['credentials' => t('auth.login.error_invalid_credentials')]);
        }

        try {
            $identity = $this->directory->authenticate($settings, $login, $command->password);
        } catch (ExternalLoginException $e) {
            error_log('LDAP login failed: ' . $e->getMessage());

            throw new ValidationException(['credentials' => t('auth.login.error_ldap_unavailable')]);
        }

        if ($identity === null) {
            throw new ValidationException(['credentials' => t('auth.login.error_invalid_credentials')]);
        }

        return $this->provisioner->resolve($identity, $settings);
    }
}
