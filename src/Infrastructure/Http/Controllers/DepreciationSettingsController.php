<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Depreciation\GetDepreciationSettings;
use App\Application\Depreciation\UpdateDepreciationSettings;
use App\Application\Query\QueryBus;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\t;

/** Einstellungsseite der Abschreibungen (AfA, Migration 146): Regeln der Berechnung und Nutzungsdauer je Kategorie. */
final class DepreciationSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([], []);
    }

    public function update(Request $request): void
    {
        $categoryYears = [];
        foreach (is_array($request->post['category_years'] ?? null) ? $request->post['category_years'] : [] as $id => $value) {
            if (is_scalar($value)) {
                $categoryYears[(string) $id] = (string) $value;
            }
        }

        try {
            $this->commands->dispatch(new UpdateDepreciationSettings(
                defaultUsefulLifeYears: $request->input('default_useful_life_years', '') ?? '',
                lowValueThreshold: $request->input('low_value_threshold', '') ?? '',
                firstYearMode: $request->input('first_year_mode', '') ?? '',
                residualValue: $request->input('residual_value', '') ?? '',
                categoryYears: $categoryYears,
            ));
            Flash::success(t('settings.depreciation.flash.saved'));
            Response::redirect('/einstellungen/abschreibungen');
        } catch (ValidationException $e) {
            $this->render($e->errors(), $request->post);
        }
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function render(array $errors, array $old): void
    {
        $data = $this->queries->ask(new GetDepreciationSettings());

        echo View::page('settings/depreciation', [
            'settings' => $data['settings'],
            'categoryYears' => $data['categoryYears'],
            'categories' => $this->categoryTree(),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-abschreibungen', title: t('settings.depreciation.title'));
    }

    /**
     * Kategorien in Baumreihenfolge (Eltern vor Kindern, je Ebene alphabetisch).
     *
     * @return list<array{id: string, name: string, depth: int, parentId: string|null}>
     */
    private function categoryTree(): array
    {
        $children = [];
        $ids = [];
        foreach ($this->categories->findAll() as $category) {
            $ids[$category->id] = true;
            $children[$category->parentId ?? ''][] = $category;
        }
        // Kategorien, deren Elternkategorie fehlt, wie Hauptkategorien behandeln.
        foreach ($children as $parentId => $list) {
            if ($parentId !== '' && !isset($ids[$parentId])) {
                $children[''] = array_merge($children[''] ?? [], $list);
                unset($children[$parentId]);
            }
        }

        $tree = [];
        $walk = static function (string $parentId, int $depth) use (&$walk, &$tree, $children): void {
            $list = $children[$parentId] ?? [];
            usort($list, static fn ($a, $b) => strcasecmp($a->name, $b->name));
            foreach ($list as $category) {
                $tree[] = ['id' => $category->id, 'name' => $category->name, 'depth' => $depth, 'parentId' => $depth > 0 ? $parentId : null];
                if ($depth < 20) {
                    $walk($category->id, $depth + 1);
                }
            }
        };
        $walk('', 0);

        return $tree;
    }
}
