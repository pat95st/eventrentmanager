<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ForbiddenException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Logbook\LogbookAccess;
use App\Domain\Logbook\LogbookEntryChange;
use App\Domain\Logbook\LogbookEntryRepositoryInterface;
use App\Domain\Logbook\LogbookSettingsRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Deleting (soft delete, same rules as editing) and restoring (logbook
 * managers only) trips; both are written to the audit trail.
 */
final class LogbookEntryLifecycleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly LogbookEntryRepositoryInterface $entries,
        private readonly LogbookSettingsRepositoryInterface $settings,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteLogbookEntry || $command instanceof RestoreLogbookEntry);

        $actor = $this->users->findById($command->actorUserId);
        $entry = $this->entries->findById($command->entryId);
        if ($entry === null) {
            throw new NotFoundException('Die Fahrt wurde nicht gefunden.');
        }
        $now = new DateTimeImmutable();

        if ($command instanceof DeleteLogbookEntry) {
            if ($entry->isDeleted()) {
                return null;
            }
            if (!LogbookAccess::canEdit($actor, $entry, $this->settings->get(), $now)) {
                throw new ForbiddenException('Diese Fahrt kann nicht mehr gelöscht werden.');
            }
            $entry->deletedAt = $now;
            $entry->deletedByUserId = $actor?->id;
            $action = LogbookEntryChange::DELETED;
        } else {
            if (!LogbookAccess::canManage($actor)) {
                throw new ForbiddenException('Nur Fahrtenbuch-Verwalter können Fahrten wiederherstellen.');
            }
            if (!$entry->isDeleted()) {
                return null;
            }
            $entry->deletedAt = null;
            $entry->deletedByUserId = null;
            $action = LogbookEntryChange::RESTORED;
        }
        $entry->updatedAt = $now;
        $this->entries->save($entry);
        $this->entries->addChange(new LogbookEntryChange(Uuid::v4(), $entry->id, $actor?->id, $actor?->displayName(), $action, [], $now));

        return null;
    }
}
