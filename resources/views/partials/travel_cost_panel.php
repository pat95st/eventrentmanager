<?php
/**
 * Travel costs of a document (migrations 182/187): summary of the copied
 * section and, while the document is a draft, the choice which kilometres
 * of the linked event are billed (driven ones for invoices only, planned
 * ones, none). The choice is loaded in the background because the route of
 * a tour may take a moment to calculate.
 *
 * @var \App\Domain\TravelCost\TravelCostSection|null $travelCosts
 * @var \App\Domain\TravelCost\TravelCostDocumentType $travelCostDocType
 * @var string $travelCostDocId
 * @var bool $travelCostEditable
 * @var string|null $travelCostTourId rental id of a linked tour (legacy, unused)
 */

use function App\Infrastructure\Http\e;

$travelCostBase = '/' . $travelCostDocType->pathSegment() . '/' . $travelCostDocId . '/fahrtkosten';
?>
<?php if ($travelCosts !== null || $travelCostEditable): ?>
<div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-900" data-travel-cost-panel data-has-section="<?= $travelCosts !== null ? '1' : '0' ?>">
    <div class="flex flex-wrap items-center gap-3">
        <span class="font-medium text-slate-700 dark:text-slate-300">Fahrtkosten</span>
        <?php if ($travelCosts !== null): ?>
            <span class="text-slate-600 dark:text-slate-400">
                <?= e($travelCosts->mode->label()) ?> · <?= number_format($travelCosts->totalKilometers(), 0, ',', '.') ?> km · <?= number_format($travelCosts->total(), 2, ',', '.') ?> € · <?= e($travelCosts->basis->label()) ?>
            </span>
            <?php if ($travelCostEditable): ?>
                <a href="<?= e($travelCostBase) ?>" class="text-brand-700 hover:underline dark:text-brand-400">bearbeiten</a>
            <?php endif; ?>
        <?php else: ?>
            <span class="text-slate-500 dark:text-slate-400">keine</span>
        <?php endif; ?>
    </div>
    <?php if ($travelCosts !== null && $travelCostDocType !== \App\Domain\TravelCost\TravelCostDocumentType::Invoice && \App\Infrastructure\Pdf\TravelCostPdfRows::estimateNote($travelCosts) !== null): ?>
        <p class="mt-1 text-xs italic text-slate-500 dark:text-slate-400"><?= e((string) \App\Infrastructure\Pdf\TravelCostPdfRows::estimateNote($travelCosts)) ?></p>
    <?php endif; ?>
    <?php if ($travelCostEditable): ?>
        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400" data-travel-cost-choices data-url="<?= e($travelCostBase) ?>/auswahl">
            <span class="loading-spinner" aria-hidden="true"></span> Fahrten der Veranstaltung werden geladen …
        </div>
        <script>
        (function () {
            var box = document.currentScript.previousElementSibling;
            fetch(box.getAttribute('data-url'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.text() : ''; })
                .then(function (html) {
                    box.innerHTML = html;
                    if (html.trim() === '') {
                        box.hidden = true;
                        // Without a linked event and without travel costs the panel has nothing to show.
                        var panel = box.closest('[data-travel-cost-panel]');
                        if (panel && panel.getAttribute('data-has-section') === '0') { panel.hidden = true; }
                    }
                })
                .catch(function () { box.hidden = true; });
        })();
        </script>
    <?php endif; ?>
</div>
<?php endif; ?>
