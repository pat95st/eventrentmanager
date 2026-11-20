<?php
/**
 * Travel costs of a document (migration 182): edit the copied section or
 * take it over (again) from the linked tour while the document is a draft.
 *
 * @var \App\Domain\TravelCost\TravelCostSection|null $travelCosts
 * @var \App\Domain\TravelCost\TravelCostDocumentType $travelCostDocType
 * @var string $travelCostDocId
 * @var bool $travelCostEditable
 * @var string|null $travelCostTourId rental id of the linked tour
 */

use function App\Infrastructure\Http\e;

$travelCostBase = '/' . $travelCostDocType->pathSegment() . '/' . $travelCostDocId . '/fahrtkosten';
?>
<?php if ($travelCosts !== null || ($travelCostEditable && $travelCostTourId !== null)): ?>
<div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-900">
    <span class="font-medium text-slate-700 dark:text-slate-300">Fahrtkosten</span>
    <?php if ($travelCosts !== null): ?>
        <span class="text-slate-600 dark:text-slate-400">
            <?= e($travelCosts->mode->label()) ?> · <?= number_format($travelCosts->totalKilometers(), 0, ',', '.') ?> km · <?= number_format($travelCosts->total(), 2, ',', '.') ?> €
        </span>
        <?php if ($travelCostEditable): ?>
            <a href="<?= e($travelCostBase) ?>" class="text-brand-700 hover:underline dark:text-brand-400">bearbeiten</a>
        <?php endif; ?>
    <?php else: ?>
        <span class="text-slate-500 dark:text-slate-400">Die verknüpfte Tour hat Fahrtkosten, die noch nicht übernommen sind.</span>
    <?php endif; ?>
    <?php if ($travelCostEditable && $travelCostTourId !== null): ?>
        <form method="post" action="<?= e($travelCostBase) ?>/aus-tour" class="inline" data-confirm-key="documents.travel_costs.import" data-confirm="Fahrtkosten aus der Tour übernehmen? Ein vorhandener Fahrtkosten-Abschnitt wird dabei ersetzt.">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= $travelCosts !== null ? 'Neu aus Tour übernehmen' : 'Fahrtkosten aus Tour übernehmen' ?></button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>
