<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

final class CompleteOnboardingTourHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CompleteOnboardingTour);

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        // Keep the first timestamp: replaying the tour from the settings
        // must not look like a fresh first-time completion.
        if ($user->onboardingTourCompletedAt === null) {
            $now = new DateTimeImmutable();
            $user->onboardingTourCompletedAt = $now;
            $user->updatedAt = $now;
            $this->users->save($user);
        }

        return null;
    }
}
