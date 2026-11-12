<?php
/** @var \App\Application\Insurance\Query\RentalInsuranceList $list */
/** @var bool $includeUnissued */
/** @var list<\App\Domain\Insurance\InsuranceField> $selectedFields */
/** @var array<string, \App\Domain\Insurance\CurrentValue> $itemValues */
/** @var array<string, array{name: string, category: string|null, count: int}> $articles */
/** @var array<string, string> $fixedValues */
/** @var bool $aiAvailable */

use App\Domain\Insurance\CurrentValueResolver;
use App\Domain\Insurance\InsuranceField;
use function App\Infrastructure\Http\e;

$rental = $list->rental;
$money = static fn (?string $v): string => $v === null ? '' : number_format((float) $v, 2, ',', '.') . ' €';
$inputValue = static fn (?string $v): string => $v === null ? '' : number_format((float) $v, 2, ',', '');
$selected = array_map(static fn (InsuranceField $f) => $f->value, $selectedFields);
$back = '/veranstaltungen/' . $rental->id . '/versicherungsliste' . ($includeUnissued ? '' : '?unissued=0');
$issuedCount = count(array_filter($list->rows, static fn ($r) => $r->isIssued()));
$missingIssued = count(array_filter($list->rows, static fn ($r) => $r->isIssued() && $r->currentValue === null));
$inputClass = 'w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800';
$secondaryButtonClass = 'inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800';
$originBadge = static function (?string $origin, ?string $note = null): string {
    return match ($origin) {
        CurrentValueResolver::ORIGIN_AI => '<span class="ml-1 inline-flex items-center rounded-full bg-violet-50 px-1.5 py-0.5 text-[10px] font-medium text-violet-700 ring-1 ring-inset ring-violet-200 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-800" title="' . e($note ?? 'Per KI geschätzt') . '">KI</span>',
        CurrentValueResolver::ORIGIN_ARTICLE => '<span class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 ring-1 ring-inset ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700" title="Fester Zeitwert des Artikels">fest</span>',
        default => '',
    };
};
?>

<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück zur Veranstaltung</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Versicherungsliste: <?= e($rental->eventName) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Alle auf der Veranstaltung eingesetzten Geräte mit Anschaffungs- und Zeitwert, als CSV oder PDF für die Versicherung.
        Fehlende Werte bleiben im Export leer.
    </p>
</div>

<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs text-slate-500 dark:text-slate-400">Geräte</p>
        <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100"><?= count($list->rows) ?></p>
        <p class="text-xs text-slate-400 dark:text-slate-500"><?= $issuedCount ?> ausgegeben<?= $includeUnissued && $issuedCount < count($list->rows) ? ', ' . (count($list->rows) - $issuedCount) . ' nur geplant' : '' ?></p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs text-slate-500 dark:text-slate-400">Summe Zeitwert</p>
        <p class="mt-1 font-mono text-lg font-semibold text-slate-900 dark:text-slate-100"><?= e($money($list->totalCurrentValue())) ?></p>
        <p class="text-xs <?= $list->countMissingCurrentValues() > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500' ?>"><?= $list->countMissingCurrentValues() ?> ohne Zeitwert</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs text-slate-500 dark:text-slate-400">Summe Anschaffungspreise</p>
        <p class="mt-1 font-mono text-lg font-semibold text-slate-900 dark:text-slate-100"><?= e($money($list->totalPurchasePrice())) ?></p>
        <p class="text-xs <?= $list->countMissingPurchasePrices() > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500' ?>"><?= $list->countMissingPurchasePrices() ?> ohne Anschaffungspreis</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs text-slate-500 dark:text-slate-400">Ansicht</p>
        <p class="mt-1 text-sm text-slate-800 dark:text-slate-200"><?= $includeUnissued ? 'Inkl. noch nicht ausgegebener Geräte' : 'Nur ausgegebene Geräte' ?></p>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/versicherungsliste<?= $includeUnissued ? '?unissued=0' : '' ?>" class="text-xs text-brand-700 hover:underline dark:text-brand-400">
            <?= $includeUnissued ? 'Nur ausgegebene anzeigen' : 'Geplante Geräte einbeziehen' ?>
        </a>
    </div>
</div>

<form method="get" action="/veranstaltungen/<?= e($rental->id) ?>/versicherungsliste.csv" class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Export</h2>
    <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Spalten</p>
    <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach (InsuranceField::cases() as $field): ?>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="fields[]" value="<?= e($field->value) ?>" <?= in_array($field->value, $selected, true) ? 'checked' : '' ?>>
                <?= e($field->label()) ?>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="mb-4 space-y-2">
        <input type="hidden" name="unissued" value="0">
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="unissued" value="1" <?= $includeUnissued ? 'checked' : '' ?>>
            Noch nicht ausgegebene (nur geplante) Geräte einbeziehen <span class="text-xs text-slate-400">(ohne Inventarnummer)</span>
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="remember" value="1" checked>
            Spaltenauswahl als Standard merken
        </label>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">CSV herunterladen</button>
        <button type="submit" formaction="/veranstaltungen/<?= e($rental->id) ?>/versicherungsliste.pdf" formtarget="_blank" class="<?= $secondaryButtonClass ?>">PDF erstellen</button>
    </div>
    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Das PDF enthält unabhängig von der Spaltenauswahl immer die Gesamtsummen von Zeitwert und Anschaffungspreisen.</p>
</form>

<form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/versicherungsliste/zeitwerte" data-insurance-form>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <input type="hidden" name="unissued" value="<?= $includeUnissued ? '1' : '0' ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">

    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Zeitwerte je Gerät</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Eigener Zeitwert je Inventarnummer. Leer = fester Zeitwert des Artikels (falls hinterlegt, als Platzhalter angezeigt), sonst bleibt das Feld leer.
                </p>
            </div>
            <?php if ($aiAvailable): ?>
                <button type="submit" formaction="/veranstaltungen/<?= e($rental->id) ?>/versicherungsliste/ki-schaetzen" data-busy-label="KI schätzt …"
                        class="<?= $secondaryButtonClass ?>" <?= $missingIssued === 0 ? 'disabled title="Alle ausgegebenen Geräte haben bereits einen Zeitwert."' : '' ?>>
                    Fehlende Zeitwerte per KI schätzen (<?= $missingIssued ?>)
                </button>
            <?php else: ?>
                <p class="max-w-xs text-xs text-slate-400 dark:text-slate-500">
                    Für die KI-Schätzung einen OpenAI-API-Schlüssel unter <a href="/einstellungen/ki-werbebilder" class="underline">Einstellungen › KI-Werbebilder</a> hinterlegen.
                </p>
            <?php endif; ?>
        </div>

        <?php if ($list->rows === []): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">Für diese Veranstaltung sind noch keine Geräte geplant oder ausgegeben.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <th class="py-2 pr-3 font-medium">Kategorie</th>
                            <th class="py-2 pr-3 font-medium">Hersteller und Modell</th>
                            <th class="py-2 pr-3 font-medium">Inventarnummer</th>
                            <th class="py-2 pr-3 font-medium">Jahr</th>
                            <th class="py-2 pr-3 text-right font-medium">Anschaffungspreis</th>
                            <th class="py-2 pr-3 text-right font-medium">Zeitwert</th>
                            <?php if ($aiAvailable): ?><th class="py-2 font-medium"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($list->rows as $row): ?>
                            <tr>
                                <td class="py-1.5 pr-3 text-slate-600 dark:text-slate-400"><?= e($row->category ?? '') ?></td>
                                <td class="py-1.5 pr-3 text-slate-800 dark:text-slate-200"><?= e($row->manufacturerModel) ?></td>
                                <td class="py-1.5 pr-3 font-mono text-xs">
                                    <?php if ($row->isIssued()): ?>
                                        <a href="/inventurnummern/<?= e($row->inventoryNumberId) ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($row->inventoryNumber) ?></a>
                                    <?php else: ?>
                                        <span class="font-sans text-xs italic text-slate-400 dark:text-slate-500">noch nicht ausgegeben</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-1.5 pr-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= $row->acquisitionYear !== null ? (int) $row->acquisitionYear : '' ?></td>
                                <td class="py-1.5 pr-3 text-right font-mono text-xs text-slate-600 dark:text-slate-400"><?= e($money($row->purchasePrice)) ?></td>
                                <td class="whitespace-nowrap py-1.5 pr-3 text-right">
                                    <?php if ($row->isIssued()): ?>
                                        <?php $own = $itemValues[$row->inventoryNumberId] ?? null; ?>
                                        <input type="hidden" name="values_orig[<?= e($row->inventoryNumberId) ?>]" value="<?= e($inputValue($own?->value)) ?>">
                                        <input type="text" inputmode="decimal" name="values[<?= e($row->inventoryNumberId) ?>]" value="<?= e($inputValue($own?->value)) ?>"
                                               placeholder="<?= e(isset($fixedValues[$row->articleId]) ? $inputValue($fixedValues[$row->articleId]) : '') ?>"
                                               aria-label="Zeitwert <?= e($row->inventoryNumber) ?>" class="<?= $inputClass ?>">
                                        <span class="inline-block w-8 text-left"><?= $originBadge($row->currentValueOrigin, $own?->note) ?></span>
                                    <?php else: ?>
                                        <span class="font-mono text-xs text-slate-600 dark:text-slate-400"><?= e($money($row->currentValue)) ?></span>
                                        <span class="inline-block w-8 text-left"><?= $originBadge($row->currentValueOrigin) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($aiAvailable): ?>
                                    <td class="py-1.5 text-right">
                                        <?php if ($row->isIssued()): ?>
                                            <button type="submit" formaction="/inventurnummern/<?= e($row->inventoryNumberId) ?>/zeitwert/ki" data-busy-label="…"
                                                    class="rounded px-2 py-1 text-xs text-violet-700 hover:bg-violet-50 dark:text-violet-300 dark:hover:bg-violet-950/40"
                                                    title="Zeitwert dieses Geräts per KI (neu) schätzen">KI</button>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($articles !== []): ?>
        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Feste Zeitwerte je Artikel</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                Gilt für alle Exemplare des Artikels ohne eigenen Zeitwert – praktisch z. B. für Kabel. Auch im Artikelformular pflegbar.
            </p>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($articles as $articleId => $info): ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                        <div class="text-sm">
                            <a href="/artikel/<?= e($articleId) ?>" class="text-slate-800 hover:underline dark:text-slate-200"><?= e($info['name']) ?></a>
                            <span class="text-xs text-slate-400 dark:text-slate-500"><?= $info['category'] !== null ? e($info['category']) . ' · ' : '' ?><?= $info['count'] ?>×</span>
                        </div>
                        <div class="flex items-center gap-1 text-sm text-slate-500">
                            <input type="hidden" name="fixed_orig[<?= e($articleId) ?>]" value="<?= e($inputValue($fixedValues[$articleId] ?? null)) ?>">
                            <input type="text" inputmode="decimal" name="fixed[<?= e($articleId) ?>]" value="<?= e($inputValue($fixedValues[$articleId] ?? null)) ?>"
                                   aria-label="Fester Zeitwert <?= e($info['name']) ?>" class="<?= $inputClass ?>"> €
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($list->rows !== []): ?>
        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Zeitwerte speichern</button>
        </div>
    <?php endif; ?>
</form>

<script>
    // KI-Schätzungen dauern einige Sekunden: Button sperren und Status zeigen,
    // damit nicht mehrfach abgeschickt wird.
    document.querySelectorAll('[data-insurance-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var button = event.submitter;
            if (!button || !button.hasAttribute('data-busy-label')) {
                return;
            }
            setTimeout(function () {
                form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
                button.textContent = button.getAttribute('data-busy-label');
            }, 0);
        });
    });
</script>
