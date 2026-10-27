<?php
/** @var \App\Domain\LaborTime\LaborTimeArticle[] $laborTimeArticles */
/** @var \App\Domain\LaborTime\LaborTimeArticle|null $editingLaborTimeArticle */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Arbeitszeit-Artikel</h1>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($laborTimeArticles === []): ?>
            <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Noch keine Arbeitszeit-Artikel angelegt.</p>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($laborTimeArticles as $laborTimeArticle): ?>
                    <?php $isEditing = $editingLaborTimeArticle !== null && $editingLaborTimeArticle->id === $laborTimeArticle->id; ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 px-2 py-2.5 <?= $isEditing ? 'bg-brand-50 rounded-md ring-1 ring-brand-200 dark:bg-brand-950/40 dark:ring-brand-800' : '' ?>">
                        <span class="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                            <?= e($laborTimeArticle->name) ?>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e(number_format((float) $laborTimeArticle->hourlyRate, 2, ',', '.')) ?> €/Std.</span>
                            <?php if ($laborTimeArticle->dailyRate !== null): ?>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e(number_format((float) $laborTimeArticle->dailyRate, 2, ',', '.')) ?> €/Tag</span>
                            <?php endif; ?>
                        </span>
                        <span class="flex items-center gap-3">
                            <a href="/arbeitszeit-artikel/<?= e($laborTimeArticle->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                            <form method="post" action="/arbeitszeit-artikel/<?= e($laborTimeArticle->id) ?>/loeschen" data-confirm="Diesen Arbeitszeit-Artikel wirklich löschen?" data-confirm-style="danger">
                              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                            </form>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
            <?= $editingLaborTimeArticle !== null ? 'Arbeitszeit-Artikel bearbeiten' : 'Neuer Arbeitszeit-Artikel' ?>
        </h2>
        <form
            method="post"
            action="<?= $editingLaborTimeArticle !== null ? '/arbeitszeit-artikel/' . e($editingLaborTimeArticle->id) : '/arbeitszeit-artikel' ?>"
            class="space-y-4"
        >
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name *</label>
                <input
                    type="text" id="name" name="name" required
                    value="<?= e($old['name'] ?? $editingLaborTimeArticle?->name ?? '') ?>"
                    class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                <?php if (isset($errors['name'])): ?>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="hourly_rate" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Stundensatz (€) *</label>
                <input
                    type="text" id="hourly_rate" name="hourly_rate" inputmode="decimal" required
                    placeholder="z. B. 45,00"
                    value="<?= e($old['hourly_rate'] ?? $editingLaborTimeArticle?->hourlyRate ?? '') ?>"
                    class="mt-1 w-full rounded-md border <?= isset($errors['hourlyRate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                <?php if (isset($errors['hourlyRate'])): ?>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['hourlyRate']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="daily_rate" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tagessatz (€)</label>
                <input
                    type="text" id="daily_rate" name="daily_rate" inputmode="decimal"
                    placeholder="optional, z. B. 320,00"
                    value="<?= e($old['daily_rate'] ?? $editingLaborTimeArticle?->dailyRate ?? '') ?>"
                    class="mt-1 w-full rounded-md border <?= isset($errors['dailyRate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Optional — wenn hinterlegt, kann bei Angeboten/Rechnungen je Position zwischen Stunden- und Tagesabrechnung gewählt werden.</p>
                <?php if (isset($errors['dailyRate'])): ?>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['dailyRate']) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Speichern
                </button>
                <?php if ($editingLaborTimeArticle !== null): ?>
                    <a href="/arbeitszeit-artikel" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
