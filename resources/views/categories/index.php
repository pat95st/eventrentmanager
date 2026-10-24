<?php
/** @var \App\Domain\Category\Category[] $categories */
/** @var \App\Domain\Category\Category|null $editingCategory */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;

$byParent = [];
foreach ($categories as $category) {
    $byParent[$category->parentId ?? ''][] = $category;
}

$countByCategory = []; // Kategorie-ID => Anzahl Artikel wird hier nicht geladen (siehe Artikel-Übersicht für Filter mit Zahl)

$renderNode = function (?string $parentId, int $depth) use (&$renderNode, &$byParent, $editingCategory): void {
    foreach ($byParent[$parentId ?? ''] ?? [] as $category) {
        $isEditing = $editingCategory !== null && $editingCategory->id === $category->id;
        ?>
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-md px-3 py-2 <?= $isEditing ? 'bg-brand-50 ring-1 ring-brand-200 dark:bg-brand-950/40 dark:ring-brand-800' : 'hover:bg-slate-50 dark:hover:bg-slate-800' ?>" style="margin-left: <?= $depth * 20 ?>px">
            <span class="text-sm text-slate-800 dark:text-slate-200"><?= e($category->name) ?></span>
            <span class="flex items-center gap-3">
                <a href="/kategorien/<?= e($category->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                <form method="post" action="/kategorien/<?= e($category->id) ?>/loeschen" data-confirm="Diese Kategorie wirklich löschen?" data-confirm-style="danger">
                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                </form>
            </span>
        </div>
        <?php
        $renderNode($category->id, $depth + 1);
    }
};
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Kategorien</h1>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($categories === []): ?>
            <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Noch keine Kategorien angelegt.</p>
        <?php else: ?>
            <?php $renderNode(null, 0); ?>
        <?php endif; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
            <?= $editingCategory !== null ? 'Kategorie bearbeiten' : 'Neue Kategorie' ?>
        </h2>
        <form
            method="post"
            action="<?= $editingCategory !== null ? '/kategorien/' . e($editingCategory->id) : '/kategorien' ?>"
            class="space-y-4"
        >
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name *</label>
                <input
                    type="text" id="name" name="name" required
                    value="<?= e($old['name'] ?? $editingCategory?->name ?? '') ?>"
                    class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                <?php if (isset($errors['name'])): ?>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="parent_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Elternkategorie</label>
                <select id="parent_id" name="parent_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                    <option value="">Keine (Hauptkategorie)</option>
                    <?php
                    $selectedParent = $old['parent_id'] ?? $editingCategory?->parentId;
                    $renderOptions = function (?string $parentId, int $depth) use (&$renderOptions, &$byParent, $selectedParent, $editingCategory) {
                        foreach ($byParent[$parentId ?? ''] ?? [] as $category) {
                            if ($editingCategory !== null && $category->id === $editingCategory->id) {
                                continue; // eigene Kategorie und ihre Nachfahren nicht als Eltern anbietbar
                            }
                            $selected = $selectedParent === $category->id ? 'selected' : '';
                            echo '<option value="' . e($category->id) . '" ' . $selected . '>' . str_repeat('— ', $depth) . e($category->name) . '</option>';
                            $renderOptions($category->id, $depth + 1);
                        }
                    };
                    $renderOptions(null, 0);
                    ?>
                </select>
                <?php if (isset($errors['parentId'])): ?>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['parentId']) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Speichern
                </button>
                <?php if ($editingCategory !== null): ?>
                    <a href="/kategorien" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
