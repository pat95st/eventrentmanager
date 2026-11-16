<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/** A person authenticated by LDAP or by the OAuth identity provider. */
final class ExternalIdentity
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
    ) {
    }
}
