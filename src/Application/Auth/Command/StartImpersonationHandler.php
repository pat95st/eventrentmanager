<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\Impersonation;
use App\Domain\Auth\ImpersonationRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Legt einen neuen Impersonations-Datensatz an. Die eigentliche Umschaltung
 * der Session (Session::startImpersonation()) passiert erst danach im
 * Controller, da Session eine Infrastruktur-Angelegenheit ist — der Handler
 * selbst kennt nur das Protokoll, keine HTTP-Session.
 */
final class StartImpersonationHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ImpersonationRepositoryInterface $impersonations,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof StartImpersonation);

        if ($command->adminUserId === $command->targetUserId) {
            throw new ValidationException(['targetUserId' => 'Sie können sich nicht als sich selbst ausgeben.']);
        }

        $target = $this->users->findById($command->targetUserId);
        if ($target === null) {
            throw new NotFoundException('Der gewählte Benutzer wurde nicht gefunden.');
        }

        if (!$target->isActive) {
            throw new ValidationException(['targetUserId' => 'Ein deaktivierter Benutzer kann nicht imitiert werden.']);
        }

        // Mit dem Nutzer per AskUserQuestion bestätigt: ein Administrator
        // kann sich NICHT als ein anderer Administrator ausgeben, um
        // gegenseitigen Zugriff/Verwischen von Verantwortlichkeiten
        // zwischen Administratoren auszuschließen.
        if ($target->isAdministrator()) {
            throw new ValidationException(['targetUserId' => 'Als Administrator kann man sich nicht ausgeben.']);
        }

        $impersonation = new Impersonation(
            id: Uuid::v4(),
            adminUserId: $command->adminUserId,
            targetUserId: $command->targetUserId,
            startedAt: new DateTimeImmutable(),
            endedAt: null,
        );

        $this->impersonations->save($impersonation);

        return ['id' => $impersonation->id];
    }
}
