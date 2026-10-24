<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use function App\Infrastructure\Http\t;

final class AuthenticatePasswordHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    /**
     * Liefert den Benutzer bei korrekten Zugangsdaten, sonst eine
     * ValidationException mit generischer Meldung — bewusst ohne
     * Unterscheidung "E-Mail unbekannt" vs. "Passwort falsch", damit sich
     * keine gültigen E-Mail-Adressen erraten lassen (User-Enumeration).
     */
    public function handle(CommandInterface $command): User
    {
        assert($command instanceof AuthenticatePassword);

        $email = trim($command->email);
        $user = $email !== '' ? $this->users->findByEmail($email) : null;

        // passwordHash ist NULL, wenn der Benutzer per E-Mail-Einladung
        // angelegt wurde und sein Passwort noch nicht über den Setup-Link
        // festgelegt hat (siehe App\Application\Auth\PasswordSetupTokenIssuer)
        // — password_verify() akzeptiert dafür keinen NULL-Hash (TypeError),
        // daher hier bewusst kontrolliert als "falsches Passwort" behandeln.
        if (
            $user === null
            || !$user->isActive
            || $user->passwordHash === null
            || !password_verify($command->password, $user->passwordHash)
        ) {
            throw new ValidationException(['credentials' => t('auth.login.error_invalid_credentials')]);
        }

        return $user;
    }
}
