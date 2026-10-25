<?php
/** @var \App\Application\CaseItem\Query\CaseItemDetail $detail */
/** @var string|null $editInventoryId */
/** @var \App\Domain\Article\Article[] $articles */
/** @var array<string, string> $articleDisplayNames Artikel-ID => "Hersteller Artikelbezeichnung" */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;

$case = $detail->case;
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/cases" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Alle Cases</a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($case->name) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            <?= e($detail->manufacturer->name) ?>
            <?php if ($detail->manufacturer->prefix !== null && $case->prefix !== null): ?>
                · <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e($detail->manufacturer->prefix) ?>-<?= e($case->prefix) ?>-…</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-3">
        <a href="/cases/<?= e($case->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Bearbeiten
        </a>
        <form method="post" action="/cases/<?= e($case->id) ?>/loeschen" data-confirm="Diesen Case inklusive aller Exemplare und deren Inhalt wirklich löschen?" data-confirm-style="danger">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                Löschen
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <!-- Stammdaten -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Details</h2>
            <?php if ($case->description !== null): ?>
                <dd class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= e($case->description) ?></dd>
            <?php else: ?>
                <p class="text-sm text-slate-400 dark:text-slate-500">Keine Beschreibung hinterlegt.</p>
            <?php endif; ?>
        </div>

        <!-- Case-Exemplare -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Case-Exemplare</h2>

            <?php if ($detail->inventoryNumbers === []): ?>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Noch kein Exemplar angelegt.</p>
            <?php else: ?>
                <div class="mb-4 space-y-4">
                    <?php foreach ($detail->inventoryNumbers as $entry): ?>
                        <?php $inv = $entry->inventoryNumber; ?>
                        <div class="rounded-md border border-slate-100 dark:border-slate-800">
                            <?php if ($editInventoryId === $inv->id): ?>
                                <form method="post" action="/case-inventurnummern/<?= e($inv->id) ?>" class="flex flex-wrap items-end gap-3 p-3">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400">Inventurnummer</label>
                                        <p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 font-mono text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-400"><?= e($inv->number) ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400">Anschaffungszeitpunkt</label>
                                        <input type="date" name="acquisition_date" value="<?= e($inv->acquisitionDate?->format('Y-m-d')) ?>"
                                               class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                    </div>
                                    <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
                                    <a href="/cases/<?= e($case->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
                                </form>
                            <?php else: ?>
                                <div class="flex flex-wrap items-center justify-between gap-2 p-3">
                                    <div>
                                        <p class="font-mono text-sm text-slate-800 dark:text-slate-200"><?= e($inv->number) ?></p>
                                        <p class="text-xs text-slate-400 dark:text-slate-500">
                                            Angeschafft: <?= $inv->acquisitionDate !== null ? e($inv->acquisitionDate->format('d.m.Y')) : '–' ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <a href="/cases/<?= e($case->id) ?>?edit_inventory=<?= e($inv->id) ?>" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                                        <form method="post" action="/case-inventurnummern/<?= e($inv->id) ?>/loeschen" data-confirm="Dieses Case-Exemplar inklusive Inhalt löschen?" data-confirm-style="danger">
                                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                            <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Inhalt dieses Exemplars -->
                            <div class="border-t border-slate-100 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Inhalt</p>
                                <?php if ($entry->content === []): ?>
                                    <p class="mb-2 text-xs text-slate-400 dark:text-slate-500">Noch keine Artikel zugeordnet.</p>
                                <?php else: ?>
                                    <div class="mb-2 divide-y divide-slate-200 dark:divide-slate-700">
                                        <?php foreach ($entry->content as $contentEntry): ?>
                                            <div class="flex flex-wrap items-center justify-between gap-2 py-1.5 text-sm">
                                                <span class="text-slate-700 dark:text-slate-300"><?= e($contentEntry->displayName()) ?></span>
                                                <div class="flex items-center gap-3">
                                                    <form method="post" action="/case-inhalt/<?= e($contentEntry->item->id) ?>" class="flex items-center gap-1">
                                                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                                        <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                                        <input type="number" name="quantity" min="1" value="<?= e((string) $contentEntry->item->quantity) ?>"
                                                               class="w-16 rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                                                        <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Ändern</button>
                                                    </form>
                                                    <form method="post" action="/case-inhalt/<?= e($contentEntry->item->id) ?>/loeschen" data-confirm="Artikel aus diesem Case entfernen?" data-confirm-style="danger">
                                                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                                        <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="post" action="/case-inventurnummern/<?= e($inv->id) ?>/inhalt" class="flex flex-wrap items-end gap-2">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                    <div>
                                        <label class="block text-[11px] text-slate-500 dark:text-slate-400">Artikel</label>
                                        <select name="article_id" required class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700">
                                            <option value="">Bitte wählen…</option>
                                            <?php foreach ($articles as $article): ?>
                                                <option value="<?= e($article->id) ?>"><?= e($articleDisplayNames[$article->id] ?? $article->name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-slate-500 dark:text-slate-400">Menge</label>
                                        <input type="number" name="quantity" min="1" value="1" class="mt-1 w-16 rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700">
                                    </div>
                                    <button type="submit" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                        Hinzufügen
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($detail->nextInventoryNumberPreview === null): ?>
                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
                        Um ein neues Case-Exemplar anzulegen, müssen zuerst ein Hersteller-Präfix
                        (unter „Hersteller“) und ein Case-Präfix (<a href="/cases/<?= e($case->id) ?>/bearbeiten" class="underline">Case bearbeiten</a>) hinterlegt werden.
                    </p>
                </div>
            <?php else: ?>
                <form method="post" action="/cases/<?= e($case->id) ?>/inventurnummern" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400">Neue Inventurnummer</label>
                        <p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 font-mono text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-400"><?= e($detail->nextInventoryNumberPreview) ?></p>
                        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Wird automatisch vergeben.</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400">Anschaffungszeitpunkt</label>
                        <input type="date" name="acquisition_date" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        Hinzufügen
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Hinweis</h2>
            <p class="text-slate-500 dark:text-slate-400">
                Jedes Case-Exemplar hat eine eigene Inventurnummer. Artikel werden pro Exemplar
                mit Menge zugeordnet, unabhängig von der konkreten Inventurnummer der Artikel selbst.
            </p>
        </div>
    </div>
</div>
