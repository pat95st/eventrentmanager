<?php
/** @var \App\Domain\Voucher\Voucher[] $vouchers */
/** @var array<string, \App\Domain\Voucher\VoucherRedemption> $redemptionsByVoucherId */
/** @var array<string, string[]> $customerIdsByVoucherId Nur Gutscheine MIT Kunden-Einschränkung */

use App\Domain\Voucher\VoucherDiscountType;
use function App\Infrastructure\Http\e;

$now = new DateTimeImmutable();

$discountLabel = static function (\App\Domain\Voucher\Voucher $voucher): string {
    $formatted = str_replace('.', ',', $voucher->discountValue);

    return $voucher->discountType === VoucherDiscountType::Percentage ? $formatted . ' %' : $formatted . ' €';
};
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($vouchers === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">Keine Gutscheine gefunden.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 20%">
                <col style="width: 14%">
                <col style="width: 20%">
                <col style="width: 20%">
                <col style="width: 16%">
                <col style="width: 10%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5">Code</th>
                    <th class="break-words px-4 py-2.5">Rabatt</th>
                    <th class="break-words px-4 py-2.5">Gültig</th>
                    <th class="break-words px-4 py-2.5">Status</th>
                    <th class="break-words px-4 py-2.5">Notiz</th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($vouchers as $voucher): ?>
                    <?php $redemption = $redemptionsByVoucherId[$voucher->id] ?? null; ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="break-words px-4 py-3 font-mono font-medium text-slate-900 dark:text-slate-100">
                            <?= e($voucher->code) ?>
                            <?php $restrictedCount = count($customerIdsByVoucherId[$voucher->id] ?? []); ?>
                            <?php if ($restrictedCount > 0): ?>
                                <span class="mt-0.5 block font-sans text-[11px] font-normal text-slate-400 dark:text-slate-500">Nur <?= $restrictedCount ?> Kunde<?= $restrictedCount === 1 ? '' : 'n' ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= e($discountLabel($voucher)) ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= e($voucher->validFrom->format('d.m.Y')) ?> – <?= e($voucher->validUntil->format('d.m.Y')) ?>
                        </td>
                        <td class="break-words px-4 py-3">
                            <?php if ($redemption !== null): ?>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600">Eingelöst</span>
                                <a href="/veranstaltungen/<?= e($redemption->rentalId) ?>" class="ml-1 text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Vermietung ansehen</a>
                            <?php elseif (!$voucher->hasStarted($now)): ?>
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:ring-amber-800">Noch nicht gültig</span>
                            <?php elseif ($voucher->isExpired($now)): ?>
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-200 dark:bg-red-950/40 dark:text-red-400 dark:ring-red-800">Abgelaufen</span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-800">Aktiv</span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-500 dark:text-slate-400">
                            <?= $voucher->notes !== null ? e($voucher->notes) : '–' ?>
                        </td>
                        <td class="break-words px-4 py-3 text-right">
                            <a href="/gutscheine/<?= e($voucher->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                            <?php if ($redemption === null): ?>
                                <form method="post" action="/gutscheine/<?= e($voucher->id) ?>/loeschen" data-confirm="Diesen Gutschein wirklich löschen?" data-confirm-style="danger" class="mt-1">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
