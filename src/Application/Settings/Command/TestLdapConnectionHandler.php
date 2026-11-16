<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\ExternalLoginException;
use App\Domain\Auth\LdapDirectoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ExternalLoginSettingsRepositoryInterface;

final class TestLdapConnectionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ExternalLoginSettingsRepositoryInterface $settings,
        private readonly LdapDirectoryInterface $directory,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof TestLdapConnection);

        $settings = $this->settings->get();
        if ($settings->ldapHost === null || $settings->ldapBaseDn === null) {
            throw new ValidationException(['ldap' => 'Bitte zuerst Server und Basis-DN speichern.']);
        }

        try {
            $this->directory->test($settings);
        } catch (ExternalLoginException $e) {
            throw new ValidationException(['ldap' => $e->getMessage()]);
        }

        return null;
    }
}
