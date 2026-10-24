<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\Permission;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

final class UpdateUserHandler implements CommandHandlerInterface
{
    use ValidatesUserPermissions;

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateUser);

        $user = $this->users->findById($command->id);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->id}\" wurde nicht gefunden.");
        }

        $errors = [];

        $email = trim($command->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        } elseif ($this->users->existsByEmail($email, excludingId: $user->id)) {
            $errors['email'] = 'Diese E-Mail-Adresse wird bereits verwendet.';
        }

        // Optional beim Bearbeiten (im Gegensatz zur Anlage): bestehende
        // Benutzer ohne hinterlegten Namen sollen nicht zum Nachtragen
        // gezwungen werden. Ein leeres Feld wird als "nicht gesetzt" (NULL)
        // interpretiert, kein Validierungsfehler.
        $firstName = trim($command->firstName);
        $lastName = trim($command->lastName);

        [$permissionError, $permissions] = $this->resolvePermissions($command->permissions);
        if ($permissionError !== null) {
            $errors['permissions'] = $permissionError;
        }

        // Löschsperre analog zum Deaktivieren: der letzte aktive Administrator
        // darf sein Administration-Recht nicht verlieren bzw. nicht
        // deaktiviert werden, sonst kann sich niemand mehr an der
        // Benutzerverwaltung anmelden.
        $willBeAdministrator = in_array(Permission::Administration, $permissions, true);
        $willBeActive = $command->isActive;
        if ($user->isAdministrator() && (!$willBeAdministrator || !$willBeActive) && $this->users->countActiveAdministrators(excludingId: $user->id) === 0) {
            $errors['permissions'] = 'Es muss mindestens ein aktiver Benutzer mit dem Recht "Administration" bestehen bleiben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user->email = $email;
        $user->firstName = $firstName !== '' ? $firstName : null;
        $user->lastName = $lastName !== '' ? $lastName : null;
        $user->permissions = $permissions;
        $user->isActive = $command->isActive;
        $user->updatedAt = new DateTimeImmutable();

        $this->users->save($user);

        return null;
    }
}
