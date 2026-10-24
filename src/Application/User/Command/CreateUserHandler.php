<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Auth\PasswordSetupTokenIssuer;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\PasswordPolicy;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateUserHandler implements CommandHandlerInterface
{
    use ValidatesUserPermissions;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordSetupTokenIssuer $passwordSetupTokenIssuer,
    ) {
    }

    /**
     * @return array{id: string, inviteEmailSent: bool|null} inviteEmailSent
     *         ist null, wenn ein Erstpasswort gesetzt wurde (keine
     *         Einladungs-Mail nötig), sonst true/false je nach Mailversand —
     *         siehe UserController::store() für die Flash-Meldung.
     */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof CreateUser);

        $errors = [];

        $email = trim($command->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        } elseif ($this->users->existsByEmail($email)) {
            $errors['email'] = 'Diese E-Mail-Adresse wird bereits verwendet.';
        }

        $firstName = trim($command->firstName);
        if ($firstName === '') {
            $errors['first_name'] = 'Bitte einen Vornamen angeben.';
        }

        $lastName = trim($command->lastName);
        if ($lastName === '') {
            $errors['last_name'] = 'Bitte einen Nachnamen angeben.';
        }

        // Leeres Passwort = "Per E-Mail einladen" (kein Erstpasswort durch
        // den Administrator, siehe Klassendoku bei CreateUser). Nur wenn
        // tatsächlich ein Passwort übermittelt wurde, greift die übliche
        // Passwortrichtlinie.
        $isInvite = $command->password === '';
        if (!$isInvite) {
            $passwordError = PasswordPolicy::validate($command->password);
            if ($passwordError !== null) {
                $errors['password'] = $passwordError;
            }
        }

        [$permissionError, $permissions] = $this->resolvePermissions($command->permissions);
        if ($permissionError !== null) {
            $errors['permissions'] = $permissionError;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $user = new User(
            id: Uuid::v4(),
            email: $email,
            passwordHash: $isInvite ? null : password_hash($command->password, PASSWORD_DEFAULT),
            firstName: $firstName,
            lastName: $lastName,
            permissions: $permissions,
            twoFactorMethod: TwoFactorMethod::None,
            totpSecret: null,
            // Der Administrator vergibt ein Erstpasswort — die/der Angelegte
            // muss es beim ersten Login durch ein eigenes ersetzen. Bei einer
            // Einladung (kein Erstpasswort) entfällt das: das erste Login
            // *ist* bereits das Festlegen eines eigenen Passworts über den
            // Setup-Link (siehe SetupPasswordController).
            mustChangePassword: !$isInvite,
            mustSetupTwoFactor: $command->mustSetupTwoFactor,
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->users->save($user);

        if (!$isInvite) {
            return ['id' => $user->id, 'inviteEmailSent' => null];
        }

        $sent = $this->passwordSetupTokenIssuer->issueAndSend($user, $command->originUrl, isInvite: true);

        return ['id' => $user->id, 'inviteEmailSent' => $sent];
    }
}
