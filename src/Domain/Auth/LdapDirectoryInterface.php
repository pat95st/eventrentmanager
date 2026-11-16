<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Settings\ExternalLoginSettings;

interface LdapDirectoryInterface
{
    /** Whether LDAP can be used on this server at all (PHP extension "ldap"). */
    public function isAvailable(): bool;

    /**
     * Looks the login name up in the directory and verifies the password by
     * binding as the found entry.
     *
     * @return ExternalIdentity|null null = unknown user or wrong password
     * @throws ExternalLoginException directory unreachable or misconfigured
     */
    public function authenticate(ExternalLoginSettings $settings, string $login, string $password): ?ExternalIdentity;

    /**
     * Connects, binds with the service account and searches the base DN.
     *
     * @throws ExternalLoginException with the reason why it does not work
     */
    public function test(ExternalLoginSettings $settings): void;
}
