<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Technician\TechnicianProfile;
use App\Domain\Technician\TechnicianProfileRepositoryInterface;
use DateTimeImmutable;

final class SaveTechnicianProfileHandler implements CommandHandlerInterface
{
    public function __construct(private readonly TechnicianProfileRepositoryInterface $profiles)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveTechnicianProfile);
        $phone = trim((string) $command->phone);
        $phone = $phone === '' ? null : preg_replace('/[\s\/()-]+/', '', $phone);
        if ($phone !== null && preg_match('/^\+?\d{6,20}$/', $phone) !== 1) {
            throw new ValidationException(['technicianPhone' => 'Bitte die Mobilnummer im internationalen Format angeben, z. B. +491701234567.']);
        }
        $telegram = trim((string) $command->telegramChatId);
        $this->profiles->save(new TechnicianProfile(
            $command->userId,
            $command->requiresConfirmation,
            $phone,
            $telegram === '' ? null : mb_substr($telegram, 0, 100),
            new DateTimeImmutable(),
        ));

        return null;
    }
}
