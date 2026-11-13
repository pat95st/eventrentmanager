<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\DashboardTemplateRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

/**
 * Eigene Anordnung der Startseite speichern, aus einer Vorlage übernehmen
 * und zurücksetzen (Migration 147). Gespeichert werden immer nur Kacheln,
 * für die der Benutzer das nötige Recht hat.
 */
final class DashboardLayoutCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly DashboardTemplateRepositoryInterface $templates,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        if ($command instanceof UpdateDashboardLayout) {
            $user = $this->user($command->userId);
            $this->store($user, DashboardLayout::fromZones($command->zones));

            return null;
        }
        if ($command instanceof ApplyDashboardTemplate) {
            $user = $this->user($command->userId);
            $template = $this->templates->findById($command->templateId);
            // Eine Vorlage ohne für diesen Benutzer sichtbare Kachel wird
            // ihm gar nicht angeboten – also auch nicht auf direktem Weg.
            if ($template === null || $template->layout->only(array_keys(DashboardTileCatalog::permittedFor($user)))->isEmpty()) {
                throw new NotFoundException('Die Vorlage wurde nicht gefunden.');
            }
            $this->store($user, $template->layout);

            return null;
        }
        assert($command instanceof ResetDashboardLayout);

        $user = $this->user($command->userId);
        $user->dashboardLayout = null;
        $user->dashboardWidgets = null;
        $user->updatedAt = new DateTimeImmutable();
        $this->users->save($user);

        return null;
    }

    private function store(User $user, DashboardLayout $layout): void
    {
        $user->dashboardLayout = $layout->only(array_keys(DashboardTileCatalog::permittedFor($user)));
        $user->updatedAt = new DateTimeImmutable();
        $this->users->save($user);
    }

    private function user(string $id): User
    {
        return $this->users->findById($id) ?? throw new NotFoundException("Benutzer mit ID \"{$id}\" wurde nicht gefunden.");
    }
}
