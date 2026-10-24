<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\Totp;
use DateTimeImmutable;

final class EnableTotpHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EnableTotp);

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        if (!Totp::verify($command->secret, $command->confirmationCode)) {
            throw new ValidationException(['code' => 'Der eingegebene Code ist nicht korrekt oder abgelaufen. Bitte erneut versuchen.']);
        }

        $user->twoFactorMethod = TwoFactorMethod::Totp;
        $user->totpSecret = $command->secret;
        $user->updatedAt = new DateTimeImmutable();

        $this->users->save($user);

        return null;
    }
}
