<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Category\Command\CreateCategory;
use App\Application\Category\Command\DeleteCategory;
use App\Application\Category\Command\UpdateCategory;
use App\Application\Category\Query\ListCategories;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class CategoryController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $categories = $this->queries->ask(new ListCategories());

        echo View::page('categories/index', [
            'categories' => $categories,
            'editingCategory' => null,
            'errors' => [],
            'old' => [],
        ], activeNav: 'categories', title: 'Kategorien');
    }

    public function edit(Request $request): void
    {
        $categories = $this->queries->ask(new ListCategories());
        $id = $request->param('id');
        $editingCategory = null;
        foreach ($categories as $category) {
            if ($category->id === $id) {
                $editingCategory = $category;
            }
        }

        if ($editingCategory === null) {
            Response::notFound('Diese Kategorie wurde nicht gefunden.');
        }

        echo View::page('categories/index', [
            'categories' => $categories,
            'editingCategory' => $editingCategory,
            'errors' => [],
            'old' => [],
        ], activeNav: 'categories', title: 'Kategorien');
    }

    public function store(Request $request): void
    {
        $name = $request->input('name', '') ?? '';
        $parentId = $this->nullIfEmpty($request->input('parent_id'));

        try {
            $this->commands->dispatch(new CreateCategory($name, $parentId));
            Flash::success('Kategorie wurde angelegt.');
            Response::redirect('/kategorien');
        } catch (ValidationException $e) {
            $this->renderWithErrors($e->errors(), ['name' => $name, 'parent_id' => $parentId]);
        }
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        $name = $request->input('name', '') ?? '';
        $parentId = $this->nullIfEmpty($request->input('parent_id'));

        try {
            $this->commands->dispatch(new UpdateCategory($id, $name, $parentId));
            Flash::success('Kategorie wurde aktualisiert.');
            Response::redirect('/kategorien');
        } catch (ValidationException $e) {
            $this->renderWithErrors($e->errors(), ['id' => $id, 'name' => $name, 'parent_id' => $parentId], $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteCategory($id));
            Flash::success('Kategorie wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/kategorien');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderWithErrors(array $errors, array $old, ?string $editingId = null): void
    {
        $categories = $this->queries->ask(new ListCategories());
        $editingCategory = null;
        if ($editingId !== null) {
            foreach ($categories as $category) {
                if ($category->id === $editingId) {
                    $editingCategory = $category;
                }
            }
        }

        echo View::page('categories/index', [
            'categories' => $categories,
            'editingCategory' => $editingCategory,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'categories', title: 'Kategorien');
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
