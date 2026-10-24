<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\PasswordSetupTokenIssuer;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\User\UserRepositoryInterface;

/**
 * "Passwort vergessen": liefert absichtlich IMMER erfolgreich (void), egal
 * ob die E-Mail-Adresse einem Konto zugeordnet werden kann — die
 * Unterscheidung würde eine User-Enumeration erlauben (siehe
 * AuthController::forgotPassword(), das unabhängig vom Ergebnis dieselbe
 * generische Erfolgsmeldung anzeigt).
 */
final class RequestPasswordSetupHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordSetupTokenIssuer $passwordSetupTokenIssuer,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RequestPasswordSetup);

        $email = trim($command->email);
        if ($email === '') {
            return null;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || !$user->isActive) {
            return null;
        }

        $this->passwordSetupTokenIssuer->issueAndSend($user, $command->originUrl, isInvite: false);

        return null;
    }
}
