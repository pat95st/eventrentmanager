<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\ArticleSet\Command\CreateArticleSet;
use App\Application\ArticleSet\Command\DeleteArticleSet;
use App\Application\ArticleSet\Command\UpdateArticleSet;
use App\Application\ArticleSet\Query\ArticleSetDetail;
use App\Application\ArticleSet\Query\GetArticleSet;
use App\Application\ArticleSet\Query\ListArticleSets;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Sets (Migration 113): Verwaltung unter "Artikel › Sets" sowie die
 * JSON-Auswahl für die Dokumentformulare (Set-/Upgrade-Positionen).
 */
final class ArticleSetController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        echo View::page('sets/index', [
            'sets' => $this->queries->ask(new ListArticleSets()),
        ], activeNav: 'sets', title: t('sets.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateArticleSet(
            name: $request->input('name', '') ?? '',
            description: $request->input('description'),
            price: $request->input('price'),
            deploymentDays: $request->input('deployment_days'),
            isActive: $request->input('is_active') !== null,
            items: $this->rows($request, 'items'),
            rules: $this->rows($request, 'rules'),
            upgrades: $this->rows($request, 'upgrades'),
        );

        try {
            $id = $this->commands->dispatch($command);
            Flash::success(t('sets.flash.created'));
            Response::redirect('/sets/' . $id);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $request->post);
        }
    }

    public function show(Request $request): void
    {
        try {
            $detail = $this->queries->ask(new GetArticleSet($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('sets/show', ['detail' => $detail], activeNav: 'sets', title: t('sets.title') . ': ' . $detail->set->name);
    }

    public function edit(Request $request): void
    {
        try {
            $detail = $this->queries->ask(new GetArticleSet($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($detail, [], []);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        $command = new UpdateArticleSet(
            id: $id,
            name: $request->input('name', '') ?? '',
            description: $request->input('description'),
            price: $request->input('price'),
            deploymentDays: $request->input('deployment_days'),
            isActive: $request->input('is_active') !== null,
            items: $this->rows($request, 'items'),
            rules: $this->rows($request, 'rules'),
            upgrades: $this->rows($request, 'upgrades'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('sets.flash.updated'));
            Response::redirect('/sets/' . $id);
        } catch (ValidationException $e) {
            try {
                $detail = $this->queries->ask(new GetArticleSet($id));
            } catch (NotFoundException) {
                $detail = null;
            }
            $this->renderForm($detail, $e->errors(), $request->post);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->commands->dispatch(new DeleteArticleSet($request->param('id')));
            Flash::success(t('sets.flash.deleted'));
            Response::redirect('/sets');
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/sets/' . $request->param('id'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    /**
     * JSON für die Set-Auswahl in Angebot/AB/Rechnung: aktive Sets mit
     * Festpreis, Einsatztagen, Inhalt, Regeln und Upgrades.
     */
    public function selection(Request $request): void
    {
        Response::json([
            'items' => array_map(static fn (ArticleSetDetail $detail) => [
                'id' => $detail->set->id,
                'name' => $detail->set->name,
                'price' => $detail->set->price,
                'deploymentDays' => $detail->set->deploymentDays,
                'contents' => $detail->contentsDescription(),
                'rules' => $detail->rulesDescription(),
                'upgrades' => array_map(static fn (array $upgrade) => [
                    'id' => $upgrade['id'],
                    'name' => $upgrade['name'],
                    'price' => $upgrade['price'],
                    'contents' => ArticleSetDetail::describe($upgrade['items']),
                ], $detail->upgrades),
            ], $this->queries->ask(new ListArticleSets(onlyActive: true))),
        ]);
    }

    /**
     * Zeilen aus dem Formular (name="items[3][article_id]" usw.); Indizes
     * sind beliebig (JS-Zähler) und werden hier nur in Reihenfolge übernommen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(Request $request, string $key): array
    {
        $raw = $request->post[$key] ?? [];

        return is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?ArticleSetDetail $detail, array $errors, array $old): void
    {
        echo View::page('sets/form', [
            'detail' => $detail,
            'errors' => $errors,
            'old' => $old,
            // Labels für Artikelzeilen nach einem Validierungsfehler.
            'labelFor' => function (string $articleId): string {
                try {
                    $articleDetail = $this->queries->ask(new \App\Application\Article\Query\GetArticle($articleId));

                    return \App\Domain\Article\ArticleDisplayName::of($articleDetail->article, $articleDetail->manufacturer);
                } catch (NotFoundException) {
                    return '';
                }
            },
        ], activeNav: 'sets', title: $detail !== null ? t('sets.edit') : t('sets.new'));
    }
}
