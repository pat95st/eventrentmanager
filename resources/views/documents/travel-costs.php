<?php
/**
 * Travel cost section of a draft offer, order confirmation or invoice
 * (migration 182): edit lines, switch between summary and single trips,
 * take over again from the tour or remove.
 *
 * @var \App\Domain\TravelCost\TravelCostDocumentType $type
 * @var string $documentId
 * @var array{number: string, editable: bool, tourRentalId: ?string} $document
 * @var \App\Domain\TravelCost\TravelCostSection $section
 * @var array<string, string> $errors
 * @var list<array{date: string, description: string, detail: string, km: string, rate: string}>|null $old
 */

use App\Domain\TravelCost\TravelCostMode;
use function App\Infrastructure\Http\e;

$base = '/' . $type->pathSegment() . '/' . $documentId;
$csrf = '<input type="hidden" name="csrf_token" value="' . e(\App\Infrastructure\Http\Csrf::token()) . '">';
$decimal = static fn (string $v, int $digits): string => number_format((float) $v, $digits, ',', '');
$rows = $old ?? array_map(static fn ($line) => [
    'date' => $line->tripDate?->format('Y-m-d') ?? '',
    'description' => $line->description,
    'detail' => $line->detail ?? '',
    'km' => $decimal($line->kilometers, (float) $line->kilometers == floor((float) $line->kilometers) ? 0 : 2),
    'rate' => $decimal($line->ratePerKm, 2),
], $section->lines);
$rows[] = ['date' => '', 'description' => '', 'detail' => '', 'km' => '', 'rate' => $decimal($section->ratePerKm, 2)];
$input = 'w-full rounded-md border border-slate-300 px-2 py-1 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$secondary = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
?>
<div class="mb-6">
    <a href="<?= e($base) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zu <?= e($type->label()) ?> <?= e($document['number']) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Fahrtkosten</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= $section->vehiclesLabel !== null ? e($section->vehiclesLabel) . ' · ' : '' ?><?= number_format((float) $section->ratePerKm, 2, ',', '.') ?> € pro km beim Übernehmen
        · <?= count($section->trips) ?> <?= count($section->trips) === 1 ? 'Fahrt' : 'Fahrten' ?>
    </p>
</div>

<?php if ($errors !== []): ?>
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
        <?php foreach (array_unique($errors) as $message): ?><p><?= e($message) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-900">
    <span class="font-medium text-slate-700 dark:text-slate-300">Darstellung:</span>
    <?php foreach (TravelCostMode::cases() as $mode): ?>
        <?php if ($mode === $section->mode): ?>
            <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-medium text-brand-800 dark:bg-brand-950 dark:text-brand-300"><?= e($mode->label()) ?></span>
        <?php else: ?>
            <form method="post" action="<?= e($base) ?>/fahrtkosten/darstellung" class="inline" data-confirm-key="documents.travel_costs.mode" data-confirm="Die Zeilen werden aus den gespeicherten Fahrten neu aufgebaut; Änderungen an den Zeilen gehen verloren.">
                <?= $csrf ?><input type="hidden" name="mode" value="<?= e($mode->value) ?>">
                <button type="submit" class="<?= $secondary ?>">Als „<?= e($mode->label()) ?>“ darstellen</button>
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($document['tourRentalId'] !== null): ?>
        <form method="post" action="<?= e($base) ?>/fahrtkosten/aus-tour" class="inline" data-confirm-key="documents.travel_costs.import" data-confirm="Fahrtkosten aus der Tour übernehmen? Ein vorhandener Fahrtkosten-Abschnitt wird dabei ersetzt.">
            <?= $csrf ?><button type="submit" class="<?= $secondary ?>">Neu aus Tour übernehmen</button>
        </form>
    <?php endif; ?>
    <form method="post" action="<?= e($base) ?>/fahrtkosten/entfernen" class="ml-auto inline" data-confirm-key="documents.travel_costs.remove" data-confirm="Die Fahrtkosten aus diesem Dokument entfernen?" data-confirm-style="danger">
        <?= $csrf ?><button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">Fahrtkosten entfernen</button>
    </form>
</div>

<form method="post" action="<?= e($base) ?>/fahrtkosten" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <?= $csrf ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-500">
                <tr>
                    <th class="py-1 pr-2 font-medium">Datum</th>
                    <th class="py-1 pr-2 font-medium">Beschreibung / Zusatzzeile</th>
                    <th class="py-1 pr-2 text-right font-medium">km</th>
                    <th class="py-1 pr-2 text-right font-medium">€ pro km</th>
                    <th class="py-1 text-right font-medium">Betrag</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800" data-travel-cost-rows>
                <?php foreach ($rows as $row): ?>
                    <tr class="align-top">
                        <td class="py-1.5 pr-2"><input type="date" name="line_date[]" value="<?= e($row['date']) ?>" class="<?= $input ?> w-36"></td>
                        <td class="py-1.5 pr-2">
                            <input type="text" name="line_description[]" value="<?= e($row['description']) ?>" maxlength="255" placeholder="z. B. Hamburg → Kassel" class="<?= $input ?>">
                            <input type="text" name="line_detail[]" value="<?= e($row['detail']) ?>" maxlength="1000" placeholder="Zusatzzeile (optional)" class="<?= $input ?> mt-1 text-xs">
                        </td>
                        <td class="py-1.5 pr-2"><input type="text" name="line_km[]" value="<?= e($row['km']) ?>" inputmode="decimal" class="<?= $input ?> w-24 text-right" data-km></td>
                        <td class="py-1.5 pr-2"><input type="text" name="line_rate[]" value="<?= e($row['rate']) ?>" inputmode="decimal" class="<?= $input ?> w-24 text-right" data-rate></td>
                        <td class="whitespace-nowrap py-1.5 text-right tabular-nums" data-line-total></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="border-t border-slate-200 font-medium dark:border-slate-700">
                <tr><td class="py-1.5 pr-2" colspan="4">Summe Fahrtkosten</td><td class="py-1.5 text-right tabular-nums" data-travel-cost-total></td></tr>
            </tfoot>
        </table>
    </div>
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
        Leere Zeilen werden ignoriert, die letzte Zeile ist für eine zusätzliche Fahrt. Ohne Zeilen wird der Abschnitt entfernt.
        Beim Umschalten der Darstellung oder neuen Übernehmen aus der Tour werden die Zeilen neu aufgebaut.
    </p>
    <div class="mt-4 flex gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
        <a href="<?= e($base) ?>" class="<?= $secondary ?> py-2">Abbrechen</a>
    </div>
</form>

<script>
(() => {
    const parse = (v) => { v = String(v).replace(/[€\s]|km/g, ''); if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.'); const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
    const euro = (n) => n.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
    const update = () => {
        let total = 0;
        document.querySelectorAll('[data-travel-cost-rows] tr').forEach((row) => {
            const sum = Math.round(parse(row.querySelector('[data-km]').value) * parse(row.querySelector('[data-rate]').value) * 100) / 100;
            total += sum;
            row.querySelector('[data-line-total]').textContent = row.querySelector('[data-km]').value.trim() === '' ? '' : euro(sum);
        });
        document.querySelector('[data-travel-cost-total]').textContent = euro(total);
    };
    document.querySelector('[data-travel-cost-rows]').addEventListener('input', update);
    update();
})();
</script>
