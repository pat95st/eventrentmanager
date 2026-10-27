<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\LaborTime\Command\CreateLaborTimeArticle;
use App\Application\LaborTime\Command\DeleteLaborTimeArticle;
use App\Application\LaborTime\Command\UpdateLaborTimeArticle;
use App\Application\LaborTime\Query\ListLaborTimeArticles;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class LaborTimeArticleController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $laborTimeArticles = $this->queries->ask(new ListLaborTimeArticles());

        echo View::page('labor-time-articles/index', [
            'laborTimeArticles' => $laborTimeArticles,
            'editingLaborTimeArticle' => null,
            'errors' => [],
            'old' => [],
        ], activeNav: 'arbeitszeit', title: 'Arbeitszeit-Artikel');
    }

    public function edit(Request $request): void
    {
        $laborTimeArticles = $this->queries->ask(new ListLaborTimeArticles());
        $id = $request->param('id');
        $editingLaborTimeArticle = null;
        foreach ($laborTimeArticles as $laborTimeArticle) {
            if ($laborTimeArticle->id === $id) {
                $editingLaborTimeArticle = $laborTimeArticle;
            }
        }

        if ($editingLaborTimeArticle === null) {
            Response::notFound('Dieser Arbeitszeit-Artikel wurde nicht gefunden.');
        }

        echo View::page('labor-time-articles/index', [
            'laborTimeArticles' => $laborTimeArticles,
            'editingLaborTimeArticle' => $editingLaborTimeArticle,
            'errors' => [],
            'old' => [],
        ], activeNav: 'arbeitszeit', title: 'Arbeitszeit-Artikel');
    }

    public function store(Request $request): void
    {
        $name = $request->input('name', '') ?? '';
        $hourlyRate = $request->input('hourly_rate', '') ?? '';
        $dailyRate = $request->input('daily_rate', '') ?? '';

        try {
            $this->commands->dispatch(new CreateLaborTimeArticle($name, $hourlyRate, $dailyRate));
            Flash::success('Arbeitszeit-Artikel wurde angelegt.');
            Response::redirect('/arbeitszeit-artikel');
        } catch (ValidationException $e) {
            $this->renderWithErrors($e->errors(), ['name' => $name, 'hourly_rate' => $hourlyRate, 'daily_rate' => $dailyRate]);
        }
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        $name = $request->input('name', '') ?? '';
        $hourlyRate = $request->input('hourly_rate', '') ?? '';
        $dailyRate = $request->input('daily_rate', '') ?? '';

        try {
            $this->commands->dispatch(new UpdateLaborTimeArticle($id, $name, $hourlyRate, $dailyRate));
            Flash::success('Arbeitszeit-Artikel wurde aktualisiert.');
            Response::redirect('/arbeitszeit-artikel');
        } catch (ValidationException $e) {
            $this->renderWithErrors($e->errors(), ['name' => $name, 'hourly_rate' => $hourlyRate, 'daily_rate' => $dailyRate], $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteLaborTimeArticle($id));
            Flash::success('Arbeitszeit-Artikel wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/arbeitszeit-artikel');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderWithErrors(array $errors, array $old, ?string $editingId = null): void
    {
        $laborTimeArticles = $this->queries->ask(new ListLaborTimeArticles());
        $editingLaborTimeArticle = null;
        if ($editingId !== null) {
            foreach ($laborTimeArticles as $laborTimeArticle) {
                if ($laborTimeArticle->id === $editingId) {
                    $editingLaborTimeArticle = $laborTimeArticle;
                }
            }
        }

        echo View::page('labor-time-articles/index', [
            'laborTimeArticles' => $laborTimeArticles,
            'editingLaborTimeArticle' => $editingLaborTimeArticle,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'arbeitszeit', title: 'Arbeitszeit-Artikel');
    }
}
