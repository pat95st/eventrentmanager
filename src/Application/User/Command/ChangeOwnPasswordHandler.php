<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\PasswordPolicy;
use DateTimeImmutable;

final class ChangeOwnPasswordHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ChangeOwnPassword);

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        if ($user->passwordHash === null || !password_verify($command->currentPassword, $user->passwordHash)) {
            throw new ValidationException(['currentPassword' => 'Das aktuelle Passwort ist nicht korrekt.']);
        }

        $passwordError = PasswordPolicy::validate($command->newPassword);
        if ($passwordError !== null) {
            throw new ValidationException(['newPassword' => $passwordError]);
        }

        $user->passwordHash = password_hash($command->newPassword, PASSWORD_DEFAULT);
        $user->mustChangePassword = false;
        $user->updatedAt = new DateTimeImmutable();

        $this->users->save($user);

        return null;
    }
}
