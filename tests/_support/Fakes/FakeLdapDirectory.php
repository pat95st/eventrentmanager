<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Auth\ExternalIdentity;
use App\Domain\Auth\ExternalLoginException;
use App\Domain\Auth\LdapDirectoryInterface;
use App\Domain\Settings\ExternalLoginSettings;

/** Directory with fixed accounts: login name => [password, identity]. */
final class FakeLdapDirectory implements LdapDirectoryInterface
{
    public bool $available = true;
    public bool $unreachable = false;
    /** @var list<string> login names that reached the directory */
    public array $attempts = [];

    /** @param array<string, array{0: string, 1: ExternalIdentity}> $accounts */
    public function __construct(private readonly array $accounts = [])
    {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function authenticate(ExternalLoginSettings $settings, string $login, string $password): ?ExternalIdentity
    {
        $this->attempts[] = $login;
        if ($this->unreachable) {
            throw new ExternalLoginException('Can\'t contact LDAP server');
        }

        $account = $this->accounts[$login] ?? null;

        return $account !== null && $account[0] === $password ? $account[1] : null;
    }

    public function test(ExternalLoginSettings $settings): void
    {
        if ($this->unreachable) {
            throw new ExternalLoginException('Can\'t contact LDAP server');
        }
    }
}
