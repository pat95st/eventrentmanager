<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\GetArticle;
use App\Application\Command\CommandBus;
use App\Application\PowerDistributor\Command\RemovePowerDistributor;
use App\Application\PowerDistributor\Command\SavePowerDistributor;
use App\Application\PowerDistributor\Query\GetPowerDistributor;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Stromverteiler-Angaben eines Artikels (Migration 117): /artikel/{id}/stromverteiler */
final class PowerDistributorController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render($request->param('id'), [], null);
    }

    public function update(Request $request): void
    {
        $articleId = $request->param('id');
        $old = [
            'inlet_type' => (string) ($request->input('inlet_type') ?? ''),
            'notes' => (string) ($request->input('notes') ?? ''),
            'protection_class' => (string) ($request->input('protection_class') ?? ''),
            'devices' => $this->rows($request, 'devices'),
            'outlets' => $this->rows($request, 'outlets'),
        ];

        try {
            $this->commands->dispatch(new SavePowerDistributor($articleId, $old['inlet_type'], $old['notes'], $old['devices'], $old['outlets'], $old['protection_class']));
            Flash::success('Die Stromverteiler-Angaben wurden gespeichert.');
            Response::redirect('/artikel/' . $articleId . '#stromverteiler');
        } catch (ValidationException $e) {
            $this->render($articleId, $e->errors(), $old);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $articleId = $request->param('id');
        $this->commands->dispatch(new RemovePowerDistributor($articleId));
        Flash::success('Der Artikel ist kein Stromverteiler mehr.');
        Response::redirect('/artikel/' . $articleId);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>|null $old
     */
    private function render(string $articleId, array $errors, ?array $old): void
    {
        try {
            $detail = $this->queries->ask(new GetArticle($articleId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('power-distributors/form', [
            'detail' => $detail,
            'distributor' => $this->queries->ask(new GetPowerDistributor($articleId)),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'articles', title: 'Stromverteiler: ' . $detail->article->name);
    }

    /** @return array<int, array<string, string>> */
    private function rows(Request $request, string $key): array
    {
        $raw = $request->post[$key] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $rows = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                $rows[] = array_map(static fn ($value) => is_string($value) ? $value : '', $row);
            }
        }

        return $rows;
    }
}
