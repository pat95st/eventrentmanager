<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Dashboard\DashboardWidget;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

final class UpdateDashboardWidgetsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateDashboardWidgets);

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        // Unbekannte/veraltete Schlüssel (z. B. aus einem alten, noch
        // geöffneten Formular nach Entfernen eines Bereichs) werden
        // stillschweigend verworfen statt einen Fehler zu werfen — eine
        // leere Auswahl ist ausdrücklich erlaubt (siehe Klassen-Doc
        // User::$dashboardWidgets).
        $valid = array_values(array_unique(array_filter(array_map(
            static fn (string $value) => DashboardWidget::tryFrom($value)?->value,
            $command->widgets,
        ))));

        $user->dashboardWidgets = $valid;
        $user->updatedAt = new DateTimeImmutable();

        $this->users->save($user);

        return null;
    }
}
