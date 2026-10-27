<?php
/** @var \App\Application\Article\Query\InventoryNumberContext $context */
/** @var \App\Domain\ElectricalInspection\ElectricalInspection[] $inspections */
/** @var \App\Domain\ElectricalInspection\ElectricalInspection|null $editingInspection */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use App\Domain\Article\ArticleDisplayName;
use function App\Infrastructure\Http\e;

$inv = $context->inventoryNumber;
$basePath = '/inventurnummern/' . $inv->id . '/elektropruefungen';

$formAction = $editingInspection !== null ? $basePath . '/' . $editingInspection->id : $basePath;

$val = fn (string $field, ?string $fallback = null) => $old[$field] ?? $fallback ?? '';
$validityYearsValue = $val('validity_years', $editingInspection !== null ? (string) $editingInspection->validityYears : '1');
$passedValue = array_key_exists('passed', $old) ? (bool) $old['passed'] : ($editingInspection?->passed ?? true);
?>

<div class="mb-6">
    <a href="/artikel/<?= e($context->article->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; <?= e(ArticleDisplayName::of($context->article, $context->manufacturer)) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Elektroprüfungen (DGUV V3)</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Inventurnummer <span class="font-mono"><?= e($inv->number) ?></span>
    </p>
    <?php if (!$context->article->requiresElectricalInspection): ?>
        <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
            Hinweis: Für diesen Artikel ist aktuell keine Elektroprüfung als nötig markiert. Erfasste Prüfungen bleiben trotzdem erhalten.
        </p>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        <?php if ($inspections === []): ?>
            <div class="rounded-lg border border-dashed border-slate-300 bg-white py-12 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                Für diese Inventurnummer wurde noch keine Elektroprüfung erfasst.
            </div>
        <?php endif; ?>

        <?php foreach ($inspections as $inspection): ?>
            <?php $status = $inspection->status(); ?>
            <div class="rounded-lg border <?= $editingInspection?->id === $inspection->id ? 'border-brand-300 ring-1 ring-brand-200 dark:border-brand-600 dark:ring-brand-800' : 'border-slate-200 dark:border-slate-800' ?> bg-white p-4 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <span class="inline-flex items-center rounded-full <?= $status->badgeClasses() ?> px-2.5 py-0.5 text-xs font-medium">
                            <?= e($status->label()) ?>
                        </span>
                        <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">
                            Geprüft am <?= e($inspection->inspectedAt->format('d.m.Y')) ?>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Gültig bis <?= e($inspection->validUntil()->format('d.m.Y')) ?>
                            (<?= $inspection->validityYears ?> Jahr<?= $inspection->validityYears > 1 ? 'e' : '' ?>)
                            <?php if ($inspection->inspector !== null): ?> · Prüfer: <?= e($inspection->inspector) ?><?php endif; ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <a href="<?= e($basePath) ?>/<?= e($inspection->id) ?>/bearbeiten" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                        <form method="post" action="<?= e($basePath) ?>/<?= e($inspection->id) ?>/loeschen" data-confirm="Diese Elektroprüfung wirklich löschen?" data-confirm-style="danger">
                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <button type="submit" class="font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                        </form>
                    </div>
                </div>

                <?php
                    $measurementRows = [
                        ['label' => 'Schutzleiterwiderstand', 'value' => $inspection->protectiveConductorResistanceOhm, 'unit' => 'Ω'],
                        ['label' => 'Isolationswiderstand', 'value' => $inspection->insulationResistanceMohm, 'unit' => 'MΩ'],
                        ['label' => 'Schutzleiterstrom/Ableitstrom', 'value' => $inspection->protectiveConductorCurrentMa, 'unit' => 'mA'],
                        ['label' => 'Berührungsstrom', 'value' => $inspection->touchCurrentMa, 'unit' => 'mA'],
                    ];
                    $measurementRows = array_filter($measurementRows, static fn (array $row) => $row['value'] !== null);
                    $measurementWarnings = $inspection->measurementWarnings($context->article->protectionClass);
                ?>
                <?php if ($measurementRows !== []): ?>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                        <?php foreach ($measurementRows as $row): ?>
                            <div>
                                <dt class="text-slate-400 dark:text-slate-500"><?= e($row['label']) ?></dt>
                                <dd class="font-mono text-slate-700 dark:text-slate-300"><?= e($row['value']) ?> <?= e($row['unit']) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>

                <?php if ($measurementWarnings !== []): ?>
                    <ul class="mt-2 space-y-0.5">
                        <?php foreach ($measurementWarnings as $warning): ?>
                            <li class="text-xs text-red-600 dark:text-red-400">⚠ <?= e($warning) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($inspection->notes !== null): ?>
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-600 dark:text-slate-400"><?= e($inspection->notes) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
            <?= $editingInspection !== null ? 'Elektroprüfung bearbeiten' : 'Neue Elektroprüfung' ?>
        </h2>

        <form method="post" action="<?= e($formAction) ?>" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Prüfdatum</label>
                <input type="date" name="inspected_at" value="<?= e($val('inspected_at', $editingInspection?->inspectedAt->format('Y-m-d'))) ?>"
                       class="mt-1 w-full rounded-md border <?= isset($errors['inspectedAt']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['inspectedAt'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['inspectedAt']) ?></p><?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Gültigkeitsdauer</label>
                <select name="validity_years"
                        class="mt-1 w-full rounded-md border <?= isset($errors['validityYears']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <option value="1" <?= $validityYearsValue === '1' ? 'selected' : '' ?>>1 Jahr (Standard)</option>
                    <option value="2" <?= $validityYearsValue === '2' ? 'selected' : '' ?>>2 Jahre</option>
                </select>
                <?php if (isset($errors['validityYears'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['validityYears']) ?></p><?php endif; ?>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="passed" value="1" <?= $passedValue ? 'checked' : '' ?>>
                    Prüfung bestanden
                </label>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Prüfer / Prüffirma</label>
                <input type="text" name="inspector" value="<?= e($val('inspector', $editingInspection?->inspector)) ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>

            <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Messwerte</h3>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Schutzleiterwiderstand (Ω) — nur Schutzklasse I</label>
                        <input type="number" step="0.001" min="0" name="protective_conductor_resistance_ohm"
                               value="<?= e($val('protective_conductor_resistance_ohm', $editingInspection?->protectiveConductorResistanceOhm)) ?>"
                               class="mt-1 w-full rounded-md border <?= isset($errors['protectiveConductorResistanceOhm']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <?php if (isset($errors['protectiveConductorResistanceOhm'])): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['protectiveConductorResistanceOhm']) ?></p>
                        <?php else: ?>
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Grenzwert: bis 5 m Leitungslänge 0,3 Ω, je weitere 7,5 m +0,1 Ω, absolut max. 1,0 Ω.</p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Isolationswiderstand (MΩ)</label>
                        <input type="number" step="0.01" min="0" name="insulation_resistance_mohm"
                               value="<?= e($val('insulation_resistance_mohm', $editingInspection?->insulationResistanceMohm)) ?>"
                               class="mt-1 w-full rounded-md border <?= isset($errors['insulationResistanceMohm']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <?php if (isset($errors['insulationResistanceMohm'])): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['insulationResistanceMohm']) ?></p>
                        <?php else: ?>
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Mindestwert: SK I ≥ 1,0 MΩ (Heizelemente ≥ 0,3 MΩ), SK II ≥ 2,0 MΩ, SK III ≥ 0,25 MΩ.</p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Schutzleiterstrom/Ableitstrom (mA) — nur Schutzklasse I</label>
                        <input type="number" step="0.01" min="0" name="protective_conductor_current_ma"
                               value="<?= e($val('protective_conductor_current_ma', $editingInspection?->protectiveConductorCurrentMa)) ?>"
                               class="mt-1 w-full rounded-md border <?= isset($errors['protectiveConductorCurrentMa']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <?php if (isset($errors['protectiveConductorCurrentMa'])): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['protectiveConductorCurrentMa']) ?></p>
                        <?php else: ?>
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Grenzwert: max. 3,5 mA (Ausnahme: Heizleistung &gt; 3,5 kW → max. 1,0 mA/kW).</p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Berührungsstrom (mA) — SK II bzw. isolierte Teile bei SK I</label>
                        <input type="number" step="0.01" min="0" name="touch_current_ma"
                               value="<?= e($val('touch_current_ma', $editingInspection?->touchCurrentMa)) ?>"
                               class="mt-1 w-full rounded-md border <?= isset($errors['touchCurrentMa']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <?php if (isset($errors['touchCurrentMa'])): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['touchCurrentMa']) ?></p>
                        <?php else: ?>
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Grenzwert: max. 0,5 mA (Ersatzableitstromverfahren: max. 0,25 mA).</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notizen (sonstige Auffälligkeiten, …)</label>
                <textarea name="notes" rows="3"
                          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
                ><?= e($val('notes', $editingInspection?->notes)) ?></textarea>
            </div>

            <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Speichern
                </button>
                <?php if ($editingInspection !== null): ?>
                    <a href="<?= e($basePath) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
