<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\DashboardTemplate;
use App\Domain\Dashboard\DashboardTemplateRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;

/** Vorlagen für die Startseite anlegen, ändern und löschen (Migration 147). */
final class DashboardTemplateCommandHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DashboardTemplateRepositoryInterface $templates)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        if ($command instanceof DeleteDashboardTemplate) {
            $this->templates->delete($command->id);

            return null;
        }
        assert($command instanceof SaveDashboardTemplate);

        $name = trim($command->name);
        $description = trim($command->description);
        // Eine Vorlage darf jede Kachel enthalten, unabhängig von den Rechten
        // des Administrators – gefiltert wird beim Übernehmen je Benutzer.
        $layout = DashboardLayout::fromZones($command->zones)->only(array_keys(DashboardTileCatalog::all()));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Der Name darf höchstens 100 Zeichen lang sein.';
        }
        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Die Beschreibung darf höchstens 255 Zeichen lang sein.';
        }
        if ($layout->isEmpty()) {
            $errors['layout'] = 'Die Vorlage braucht mindestens einen sichtbaren Bereich.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($command->id === null) {
            $template = new DashboardTemplate(Uuid::v4(), $name, $description !== '' ? $description : null, $layout);
        } else {
            $template = $this->templates->findById($command->id) ?? throw new NotFoundException('Die Vorlage wurde nicht gefunden.');
            $template->name = $name;
            $template->description = $description !== '' ? $description : null;
            $template->layout = $layout;
        }
        $this->templates->save($template);

        return $template->id;
    }
}
