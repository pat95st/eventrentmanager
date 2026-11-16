<?php

declare(strict_types=1);

namespace App\Infrastructure\Ldap;

use App\Domain\Auth\ExternalIdentity;
use App\Domain\Auth\ExternalLoginException;
use App\Domain\Auth\LdapDirectoryInterface;
use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\Settings\LdapEncryption;
use LDAP\Connection;

/**
 * LDAP access through PHP's "ldap" extension ("search, then bind"): the
 * service account looks up the entry of the login name, afterwards the
 * password is verified by binding as that entry.
 */
final class ExtLdapDirectory implements LdapDirectoryInterface
{
    private const TIMEOUT_SECONDS = 5;

    public function isAvailable(): bool
    {
        return function_exists('ldap_connect');
    }

    public function authenticate(ExternalLoginSettings $settings, string $login, string $password): ?ExternalIdentity
    {
        // See AuthenticateLdapHandler: never bind with an empty password.
        if ($login === '' || $password === '') {
            return null;
        }

        $connection = $this->connect($settings);

        try {
            $filter = str_replace('%s', ldap_escape($login, '', LDAP_ESCAPE_FILTER), $settings->ldapUserFilter);
            $attributes = [
                $settings->ldapEmailAttribute,
                $settings->ldapFirstNameAttribute,
                $settings->ldapLastNameAttribute,
            ];

            // Size limit 2: enough to notice an ambiguous login name.
            $result = @ldap_search($connection, (string) $settings->ldapBaseDn, $filter, $attributes, 0, 2, self::TIMEOUT_SECONDS);
            if ($result === false) {
                throw new ExternalLoginException('Die Suche im Verzeichnis ist fehlgeschlagen: ' . ldap_error($connection));
            }

            $entry = ldap_first_entry($connection, $result);
            if ($entry === false || ldap_count_entries($connection, $result) !== 1) {
                return null;
            }

            $dn = ldap_get_dn($connection, $entry);
            if (!is_string($dn) || $dn === '') {
                return null;
            }

            $email = $this->firstValue($connection, $entry, $settings->ldapEmailAttribute);
            $firstName = $this->firstValue($connection, $entry, $settings->ldapFirstNameAttribute);
            $lastName = $this->firstValue($connection, $entry, $settings->ldapLastNameAttribute);

            if (!@ldap_bind($connection, $dn, $password)) {
                return null;
            }

            // An entry without e-mail address cannot be mapped to a user;
            // ExternalUserProvisioner rejects it with a fitting message.
            return new ExternalIdentity($email ?? '', $firstName, $lastName);
        } finally {
            @ldap_unbind($connection);
        }
    }

    public function test(ExternalLoginSettings $settings): void
    {
        $connection = $this->connect($settings);

        try {
            $result = @ldap_read($connection, (string) $settings->ldapBaseDn, '(objectClass=*)', ['dn'], 0, 1, self::TIMEOUT_SECONDS);
            if ($result === false) {
                throw new ExternalLoginException('Die Basis-DN konnte nicht gelesen werden: ' . ldap_error($connection));
            }
        } finally {
            @ldap_unbind($connection);
        }
    }

    /** Opens the connection and binds with the service account (or anonymously). */
    private function connect(ExternalLoginSettings $settings): Connection
    {
        if (!$this->isAvailable()) {
            throw new ExternalLoginException('Die PHP-Erweiterung „ldap“ ist auf diesem Server nicht installiert.');
        }

        $scheme = $settings->ldapEncryption === LdapEncryption::Ldaps ? 'ldaps' : 'ldap';
        $connection = @ldap_connect(sprintf('%s://%s:%d', $scheme, (string) $settings->ldapHost, $settings->ldapPort));
        if ($connection === false) {
            throw new ExternalLoginException('Die LDAP-Adresse ist ungültig.');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, self::TIMEOUT_SECONDS);
        ldap_set_option($connection, LDAP_OPT_TIMELIMIT, self::TIMEOUT_SECONDS);

        if ($settings->ldapEncryption === LdapEncryption::StartTls && !@ldap_start_tls($connection)) {
            throw new ExternalLoginException('STARTTLS ist fehlgeschlagen: ' . ldap_error($connection));
        }

        $bound = $settings->ldapBindDn !== null
            ? @ldap_bind($connection, $settings->ldapBindDn, (string) $settings->ldapBindPassword)
            : @ldap_bind($connection);
        if (!$bound) {
            $error = ldap_error($connection);
            @ldap_unbind($connection);

            throw new ExternalLoginException('Die Anmeldung am LDAP-Server ist fehlgeschlagen: ' . $error);
        }

        return $connection;
    }

    private function firstValue(Connection $connection, \LDAP\ResultEntry $entry, string $attribute): ?string
    {
        $values = @ldap_get_values($connection, $entry, $attribute);
        if (!is_array($values) || !isset($values[0]) || !is_string($values[0]) || trim($values[0]) === '') {
            return null;
        }

        return trim($values[0]);
    }
}
