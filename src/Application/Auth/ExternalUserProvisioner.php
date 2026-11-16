<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\ExternalIdentity;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

use function App\Infrastructure\Http\t;

/**
 * Maps a person authenticated by LDAP or OAuth to a local user (by e-mail
 * address) and, if the administrator allowed it, creates the user on the
 * first login. Only called AFTER the external system confirmed the identity,
 * so the specific error messages do not allow guessing accounts.
 */
final class ExternalUserProvisioner
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function resolve(ExternalIdentity $identity, ExternalLoginSettings $settings): User
    {
        $email = trim($identity->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['credentials' => t('auth.login.error_external_no_email')]);
        }

        $user = $this->users->findByEmail($email);
        if ($user !== null) {
            if (!$user->isActive) {
                throw new ValidationException(['credentials' => t('auth.login.error_account_disabled')]);
            }

            return $user;
        }

        if (!$settings->autoCreateUsers) {
            throw new ValidationException(['credentials' => t('auth.login.error_no_local_account')]);
        }

        $now = new DateTimeImmutable();
        $user = new User(
            id: Uuid::v4(),
            email: $email,
            // No local password: the account can only be used through the
            // external login until a password is set via "forgot password".
            passwordHash: null,
            firstName: self::name($identity->firstName),
            lastName: self::name($identity->lastName),
            // Never hand out the administration right automatically, even
            // if a stored setting should contain it.
            permissions: array_values(array_filter(
                $settings->defaultPermissions,
                static fn (Permission $p): bool => $p !== Permission::Administration,
            )),
            twoFactorMethod: TwoFactorMethod::None,
            totpSecret: null,
            mustChangePassword: false,
            mustSetupTwoFactor: false,
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        );
        $this->users->save($user);

        return $user;
    }

    private static function name(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_substr($value, 0, 100) : null;
    }
}
