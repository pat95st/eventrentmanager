<?php
/**
 * Defekt melden (RMA) durch einen Techniker (Migration 134) – nur für Geräte,
 * die in dieser Veranstaltung ausgegeben wurden, ohne Kosten/Belege.
 *
 * @var \App\Application\Rental\Query\RentalDetail $detail
 */

use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$rental = $detail->rental;
$input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
?>
<div class="mb-4">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; <?= e($rental->eventName) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Defekt melden</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Es wird ein RMA-Vorgang angelegt; die Werkstatt kümmert sich um alles Weitere.</p>
</div>

<?php if ($detail->issuedItems === []): ?>
    <p class="max-w-2xl rounded-lg border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">In dieser Veranstaltung wurden noch keine Geräte ausgegeben.</p>
<?php else: ?>
    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/rma" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <div>
            <label for="rma-device" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Gerät *</label>
            <select id="rma-device" name="inventory_number_id" required class="<?= $input ?>">
                <option value="">Bitte wählen…</option>
                <?php foreach ($detail->issuedItems as $issued): ?>
                    <option value="<?= e($issued->inventoryNumber->id) ?>"><?= e($issued->inventoryNumber->number . ' · ' . $issued->displayName()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="rma-reason" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Was ist defekt? *</label>
            <textarea id="rma-reason" name="reason" rows="3" required class="<?= $input ?>" placeholder="z. B. Kanal 2 brummt, Stecker locker"></textarea>
        </div>
        <div>
            <label for="rma-notes" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Weitere Hinweise</label>
            <textarea id="rma-notes" name="notes" rows="2" class="<?= $input ?>"></textarea>
        </div>
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Defekt melden</button>
    </form>
<?php endif; ?>
