<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

final class EnableEmailOtpHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EnableEmailOtp);

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        // Kein Bestätigungsschritt nötig (anders als bei TOTP): die
        // E-Mail-Adresse ist bereits der verifizierte Anmeldename, ein neuer
        // Faktor wird hier nicht neu nachgewiesen, nur ausgewählt.
        $user->twoFactorMethod = TwoFactorMethod::Email;
        $user->totpSecret = null;
        $user->updatedAt = new DateTimeImmutable();

        $this->users->save($user);

        return null;
    }
}
