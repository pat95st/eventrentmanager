<?php
/**
 * Rows of the travel cost section of a document (migration 182) inside the
 * <tfoot> of the item table of offers, order confirmations and invoices,
 * after labor time like the other amounts added after the discount.
 *
 * @var \App\Domain\TravelCost\TravelCostSection|null $travelCosts
 * @var int $travelCostLabelColumns number of columns before the amount column
 */

use function App\Infrastructure\Http\e;

$travelCostLabelColumns ??= 3;
?>
<?php if ($travelCosts !== null && $travelCosts->lines !== []): ?>
    <tr>
        <td class="px-2 pt-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500" colspan="<?= $travelCostLabelColumns + 1 ?>">Fahrtkosten</td>
    </tr>
    <?php foreach ($travelCosts->lines as $line): ?>
        <tr>
            <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="<?= $travelCostLabelColumns ?>">
                <?= $line->tripDate !== null ? e($line->tripDate->format('d.m.Y')) . ' · ' : '' ?><?= e($line->description) ?>:
                <?= number_format((float) $line->kilometers, (float) $line->kilometers == floor((float) $line->kilometers) ? 0 : 2, ',', '.') ?> km × <?= number_format((float) $line->ratePerKm, 2, ',', '.') ?> €
                <?php if ($line->detail !== null && $line->detail !== ''): ?>
                    <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e($line->detail) ?></span>
                <?php endif; ?>
            </td>
            <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($line->lineTotal(), 2, ',', '.') ?> €</td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
