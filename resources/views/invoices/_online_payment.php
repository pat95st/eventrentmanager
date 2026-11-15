<?php
/**
 * Online payment (PayPal/SumUp, migration 161) on the invoice detail page.
 *
 * @var \App\Domain\Invoice\Invoice $invoice
 * @var string|null $paymentLink
 * @var \App\Domain\OnlinePayment\OnlinePayment[] $onlinePayments
 */

use App\Domain\OnlinePayment\OnlinePaymentStatus;

use function App\Infrastructure\Http\e;

if ($paymentLink === null && $onlinePayments === []) {
    return;
}
?>
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Online-Zahlung</h2>

    <?php if ($paymentLink !== null): ?>
        <p class="text-xs text-slate-500 dark:text-slate-400">Über diesen Link kann der Kunde die Rechnung per PayPal bzw. SumUp bezahlen. Er wird automatisch in die Rechnungs-E-Mail eingefügt (Platzhalter <code>{{payment_link_html}}</code>).</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <input type="text" readonly value="<?= e($paymentLink) ?>" onclick="this.select()"
                   class="min-w-0 flex-1 rounded-md border border-slate-300 px-2 py-1.5 font-mono text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
            <button type="button" data-copy="<?= e($paymentLink) ?>"
                    onclick="navigator.clipboard && navigator.clipboard.writeText(this.dataset.copy).then(() => { this.textContent = 'Kopiert'; })"
                    class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Link kopieren</button>
            <a href="<?= e($paymentLink) ?>" target="_blank" rel="noopener" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Öffnen</a>
        </div>
    <?php endif; ?>

    <?php if ($onlinePayments !== []): ?>
        <ul class="<?= $paymentLink !== null ? 'mt-4 border-t border-slate-100 pt-2 dark:border-slate-800' : '' ?> divide-y divide-slate-100 text-sm dark:divide-slate-800">
            <?php foreach ($onlinePayments as $payment): ?>
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-slate-700 dark:text-slate-300"><?= e($payment->provider->shortLabel()) ?></span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($payment->status->badgeClasses()) ?>"><?= e($payment->status->label()) ?></span>
                        <span class="text-xs text-slate-500 dark:text-slate-400"><?= e(($payment->completedAt ?? $payment->createdAt)->format('d.m.Y H:i')) ?></span>
                        <?php if ($payment->providerReference !== null): ?>
                            <span class="font-mono text-xs text-slate-400 dark:text-slate-500" title="Referenz beim Zahlungsanbieter"><?= e($payment->providerReference) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="text-slate-700 dark:text-slate-300" style="font-variant-numeric:tabular-nums"><?= e(number_format((float) $payment->amount, 2, ',', '.') . ' ' . ($payment->currency === 'EUR' ? '€' : $payment->currency)) ?></span>
                    <?php if ($payment->failureReason !== null && $payment->status !== OnlinePaymentStatus::Completed): ?>
                        <span class="w-full text-xs text-slate-500 dark:text-slate-400"><?= e($payment->failureReason) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
