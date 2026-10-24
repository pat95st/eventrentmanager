<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\PasswordSetupTokenRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\PasswordPolicy;
use DateTimeImmutable;

/**
 * Prüft den Setup-Token und setzt bei Erfolg ein neues Passwort. Wirft
 * bewusst zwei unterschiedliche Exception-Typen, damit der Controller
 * zwischen "Link ist ungültig/abgelaufen" (freundliche Fehlerseite, siehe
 * SetupPasswordController) und "Formularfehler" (Formular mit
 * Fehlermeldung erneut anzeigen) unterscheiden kann:
 *  - NotFoundException: Token existiert nicht, ist abgelaufen oder wurde
 *    bereits verwendet (Replay).
 *  - ValidationException: Token ist gültig, aber die eingegebenen Passwörter
 *    erfüllen die Richtlinie nicht bzw. stimmen nicht überein.
 */
final class CompletePasswordSetupHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly PasswordSetupTokenRepositoryInterface $tokens,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CompletePasswordSetup);

        $rawToken = trim($command->token);
        $now = new DateTimeImmutable();

        $token = $rawToken !== '' ? $this->tokens->findByTokenHash(hash('sha256', $rawToken)) : null;
        if ($token === null || !$token->isUsable($now)) {
            throw new NotFoundException('Dieser Link ist ungültig oder abgelaufen.');
        }

        $errors = [];

        $passwordError = PasswordPolicy::validate($command->newPassword);
        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        } elseif ($command->newPassword !== $command->newPasswordConfirmation) {
            $errors['password_confirmation'] = 'Die Passwörter stimmen nicht überein.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user = $this->users->findById($token->userId);
        if ($user === null || !$user->isActive) {
            throw new NotFoundException('Dieser Link ist ungültig oder abgelaufen.');
        }

        $user->passwordHash = password_hash($command->newPassword, PASSWORD_DEFAULT);
        $user->mustChangePassword = false;
        $user->updatedAt = $now;
        $this->users->save($user);

        // Erst NACH dem erfolgreichen Speichern verbrauchen — bei einem
        // Fehler dazwischen bleibt der Link nutzbar, statt den Benutzer
        // auszusperren.
        $this->tokens->markUsed($token->id);

        return $user->id;
    }
}
