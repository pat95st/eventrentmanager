<?php
/**
 * Bestandteil-Editor eines Bundles inkl. Alternativartikeln (Migration 107/111),
 * eingebunden von articles/bundle-form.php.
 *
 * @var list<array{article_id: string, label: string, quantity: int, alternatives?: list<array{article_id: string, label: string}>}> $bundleComponentRows
 * @var array<string, string> $errors
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\renderLiveSearchField;
use function App\Infrastructure\Http\t;
?>
        <div data-bundle-section class="rounded-md border border-slate-200 p-4 dark:border-slate-700">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('articles.form.bundle_components')) ?></h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.bundle_components_hint')) ?></p>
                </div>
                <button type="button" data-bundle-add class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <?= e(t('articles.form.bundle_component_add')) ?>
                </button>
            </div>
            <?php if (isset($errors['bundleComponents'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['bundleComponents']) ?></p><?php endif; ?>
            <?php
            // Eine Bestandteil-Zeile inkl. optionaler Alternativartikel
            // (Migration 111): Chips + Live-Suche zum Hinzufügen, die IDs
            // stehen kommagetrennt in bundle_component_alternatives[].
            $renderBundleRow = function (?array $componentRow): void {
                $alternatives = $componentRow['alternatives'] ?? [];
                ?>
                <div data-bundle-row class="rounded-md border border-slate-200 p-2 dark:border-slate-700">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-[220px] flex-1">
                            <?php renderLiveSearchField('bundle_component_article_id[]', '/artikel/suche', $componentRow['article_id'] ?? null, $componentRow['label'] ?? null, t('articles.form.bundle_component_placeholder'), null, 'select', false, false); ?>
                        </div>
                        <input type="number" name="bundle_component_quantity[]" min="1" max="999" value="<?= e((string) ($componentRow['quantity'] ?? 1)) ?>" title="<?= e(t('articles.form.bundle_component_quantity')) ?>"
                               class="w-20 rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700">
                        <button type="button" data-bundle-remove class="px-2 py-2 text-sm text-red-600 hover:underline dark:text-red-400" title="<?= e(t('common.delete')) ?>">&times;</button>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.form.bundle_alternatives')) ?>:</span>
                        <span data-alt-list class="flex flex-wrap gap-1">
                            <?php foreach ($alternatives as $alternative): ?>
                                <span data-alt-chip data-id="<?= e($alternative['article_id']) ?>" class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    <?= e($alternative['label']) ?>
                                    <button type="button" data-alt-remove class="text-slate-400 hover:text-red-600" title="<?= e(t('common.delete')) ?>">&times;</button>
                                </span>
                            <?php endforeach; ?>
                        </span>
                        <div data-alt-search class="min-w-[220px] flex-1">
                            <?php renderLiveSearchField('alternative_search[]', '/artikel/suche', null, null, t('articles.form.bundle_alternative_placeholder'), null, 'select', false, false); ?>
                        </div>
                    </div>
                    <input type="hidden" name="bundle_component_alternatives[]" data-alt-ids value="<?= e(implode(',', array_column($alternatives, 'article_id'))) ?>">
                </div>
                <?php
            };
            ?>
            <div data-bundle-rows class="space-y-2">
                <?php foreach ($bundleComponentRows as $componentRow): ?>
                    <?php $renderBundleRow($componentRow); ?>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.bundle_alternatives_hint')) ?></p>
            <template data-bundle-template>
                <?php $renderBundleRow(null); ?>
            </template>
            <template data-alt-search-template>
                <div data-alt-search class="min-w-[220px] flex-1">
                    <?php renderLiveSearchField('alternative_search[]', '/artikel/suche', null, null, t('articles.form.bundle_alternative_placeholder'), null, 'select', false, false); ?>
                </div>
            </template>
        </div>
        <script>
            (function () {
                var rows = document.querySelector('[data-bundle-rows]');
                var template = document.querySelector('[data-bundle-template]');
                var addButton = document.querySelector('[data-bundle-add]');

                function addRow() {
                    var fragment = template.content.cloneNode(true);
                    var row = fragment.querySelector('[data-bundle-row]');
                    rows.appendChild(fragment);
                    if (window.LiveSearch) { window.LiveSearch.init(row); }
                }

                var altTemplate = document.querySelector('[data-alt-search-template]');

                function altIds(row) {
                    return Array.prototype.map.call(row.querySelectorAll('[data-alt-chip]'), function (chip) { return chip.getAttribute('data-id'); });
                }

                function writeAltIds(row) {
                    row.querySelector('[data-alt-ids]').value = altIds(row).join(',');
                }

                function addAlternative(row, id, label) {
                    if (!id || altIds(row).indexOf(id) !== -1) { return; }
                    var chip = document.createElement('span');
                    chip.setAttribute('data-alt-chip', '');
                    chip.setAttribute('data-id', id);
                    chip.className = 'inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300';
                    chip.appendChild(document.createTextNode(label + ' '));
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.setAttribute('data-alt-remove', '');
                    remove.className = 'text-slate-400 hover:text-red-600';
                    remove.textContent = '×';
                    chip.appendChild(remove);
                    row.querySelector('[data-alt-list]').appendChild(chip);
                    writeAltIds(row);
                }

                // Auswahl in der Alternativen-Suche: als Chip übernehmen und
                // das Suchfeld durch ein frisches ersetzen.
                rows.addEventListener('change', function (event) {
                    var search = event.target.closest('[data-alt-search]');
                    if (!search || !event.target.matches('[data-live-search-value]') || event.target.value === '') { return; }
                    var row = search.closest('[data-bundle-row]');
                    var label = search.querySelector('[data-live-search-input]').value;
                    addAlternative(row, event.target.value, label);
                    var fresh = altTemplate.content.cloneNode(true).querySelector('[data-alt-search]');
                    search.replaceWith(fresh);
                    if (window.LiveSearch) { window.LiveSearch.init(fresh); }
                });

                addButton.addEventListener('click', addRow);
                rows.addEventListener('click', function (event) {
                    var remove = event.target.closest('[data-bundle-remove]');
                    if (remove) { remove.closest('[data-bundle-row]').remove(); return; }
                    var altRemove = event.target.closest('[data-alt-remove]');
                    if (altRemove) {
                        var row = altRemove.closest('[data-bundle-row]');
                        altRemove.closest('[data-alt-chip]').remove();
                        writeAltIds(row);
                    }
                });
                if (rows.querySelectorAll('[data-bundle-row]').length === 0) { addRow(); }
            })();
        </script>
