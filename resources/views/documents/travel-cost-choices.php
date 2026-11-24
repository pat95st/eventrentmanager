<?php
/**
 * Choice of the travel cost basis on a draft document (migration 187),
 * loaded into the travel cost panel. Empty without a linked event.
 *
 * @var \App\Domain\TravelCost\TravelCostDocumentType $type
 * @var string $documentId
 * @var bool $editable
 * @var array{rental: ?\App\Domain\Rental\Rental, enabled: bool, planned: ?\App\Application\TravelCost\TourTravelCostProposal, actual: ?\App\Application\TravelCost\TourTravelCostProposal, preselect: string, current: ?string} $choices
 * @var \App\Domain\TravelCost\TravelCostSection|null $section
 * @var bool $logbookChanged
 */

use App\Domain\TravelCost\TravelCostDocumentType;

use function App\Infrastructure\Http\e;

$rental = $choices['rental'];
if ($rental === null || !$editable) {
    return;
}
$base = '/' . $type->pathSegment() . '/' . $documentId . '/fahrtkosten';
$summary = static function (?\App\Application\TravelCost\TourTravelCostProposal $p): string {
    if ($p === null || $p->trips === []) {
        return 'keine Fahrten';
    }
    $km = number_format($p->totalKilometers(), 0, ',', '.') . ' km';

    return $p->vehicles !== [] || $p->basis === \App\Domain\TravelCost\TravelCostBasis::Actual
        ? $km . ' · ' . number_format($p->total(), 2, ',', '.') . ' €'
        : $km . ' · keine Fahrzeuge geplant';
};
$selected = $choices['current'] ?? $choices['preselect'];
$options = [];
if ($type === TravelCostDocumentType::Invoice) {
    $options['actual'] = ['Tatsächlich gefahrene Kilometer', $summary($choices['actual']), $choices['actual'] !== null && $choices['actual']->isBillable()];
}
$options['planned'] = [$rental->isTour ? 'Geplante Kilometer (Route der Tour)' : 'Geplante Kilometer', $summary($choices['planned']), $choices['planned'] !== null && $choices['planned']->isBillable()];
$options['none'] = ['Keine Fahrtkosten', '', true];
$planningUrl = '/veranstaltungen/' . $rental->id . ($rental->isTour ? '/tour/route#fahrtkosten' : '/fahrten');
?>
<?php if ($logbookChanged): ?>
    <p class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">Das Fahrtenbuch dieser Veranstaltung hat sich seit der Übernahme geändert. Zum Aktualisieren die gefahrenen Kilometer neu übernehmen.</p>
<?php endif; ?>
<?php if (!$choices['enabled']): ?>
    <p class="mb-2">Für die Veranstaltung „<?= e($rental->eventName) ?>“ ist „Fahrtkosten abrechnen“ ausgeschaltet (<a href="<?= e($planningUrl) ?>" class="underline">ändern</a>).</p>
<?php else: ?>
<form method="post" action="<?= e($base) ?>/uebernehmen" class="flex flex-wrap items-center gap-x-4 gap-y-2" data-confirm-key="documents.travel_costs.import" data-confirm="Fahrtkosten übernehmen? Ein vorhandener Fahrtkosten-Abschnitt wird dabei ersetzt.">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <span>Abrechnen aus „<a href="<?= e($planningUrl) ?>" class="underline"><?= e($rental->eventName) ?></a>“:</span>
    <?php foreach ($options as $value => [$label, $detail, $available]): ?>
        <label class="inline-flex items-center gap-1.5 <?= $available ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500' ?>">
            <input type="radio" name="basis" value="<?= e($value) ?>" <?= $selected === $value && $available ? 'checked' : '' ?> <?= $available ? '' : 'disabled' ?>>
            <?= e($label) ?><?= $detail !== '' ? ' <span class="text-slate-500">(' . e($detail) . ')</span>' : '' ?>
        </label>
    <?php endforeach; ?>
    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= $section !== null ? 'Neu übernehmen' : 'Übernehmen' ?></button>
</form>
<?php if ($type !== TravelCostDocumentType::Invoice): ?>
    <p class="mt-1">In Angeboten und Auftragsbestätigungen werden die geplanten Kilometer abgerechnet; die Rechnung kann später die tatsächlich gefahrenen übernehmen.</p>
<?php endif; ?>
<?php endif; ?>
