<?php
/** @var \App\Application\OnlinePayment\Query\InvoicePaymentPage $page */
/** @var string $token */
/** @var string|null $notice */

use App\Domain\Invoice\InvoiceStatus;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\Flash;

use function App\Infrastructure\Http\e;

$invoice = $page->invoice;
$isPaid = $invoice->status === InvoiceStatus::Paid;
$amount = number_format((float) $invoice->amountDue, 2, ',', '.') . ' ' . ($page->currency === 'EUR' ? '€' : $page->currency);
$title = 'Rechnung ' . ($invoice->invoiceNumber ?? '');
$flashError = Flash::consumeError();
$noticeText = match ($notice) {
    'bezahlt' => ['green', 'Vielen Dank! Ihre Zahlung ist bei uns eingegangen.'],
    'in-bearbeitung' => ['amber', 'Ihre Zahlung wird noch vom Zahlungsanbieter bearbeitet. Sobald sie bestätigt ist, wird die Rechnung automatisch als bezahlt markiert.'],
    'abgebrochen' => ['slate', 'Die Zahlung wurde abgebrochen. Sie können es jederzeit erneut versuchen.'],
    'fehlgeschlagen' => ['red', 'Die Zahlung ist leider fehlgeschlagen. Bitte versuchen Sie es erneut oder wählen Sie eine andere Zahlungsart.'],
    default => null,
};
$noticeClasses = [
    'green' => 'border-green-200 bg-green-50 text-green-800',
    'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
    'slate' => 'border-slate-200 bg-slate-50 text-slate-700',
    'red' => 'border-red-200 bg-red-50 text-red-800',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?> bezahlen</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-slate-50 antialiased">
    <div class="mx-auto max-w-xl px-4 py-10">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-6">
                <?php if ($page->companyName !== ''): ?>
                    <p class="text-xs font-medium uppercase tracking-wide text-brand-600"><?= e($page->companyName) ?></p>
                <?php endif; ?>
                <h1 class="mt-1 text-xl font-semibold text-slate-900"><?= e($title) ?></h1>
                <p class="mt-1 text-sm text-slate-500">
                    <?= $invoice->customerName !== null ? e($invoice->customerName) . ' · ' : '' ?>
                    <?= $invoice->invoiceDate !== null ? 'vom ' . e($invoice->invoiceDate->format('d.m.Y')) : '' ?>
                    <?= $invoice->dueDate !== null && !$isPaid ? ' · fällig am ' . e($invoice->dueDate->format('d.m.Y')) : '' ?>
                </p>
            </div>

            <div class="space-y-4 p-6">
                <?php if ($noticeText !== null): ?>
                    <div class="rounded-md border p-3 text-sm <?= $noticeClasses[$noticeText[0]] ?>"><?= e($noticeText[1]) ?></div>
                <?php endif; ?>
                <?php if ($flashError !== null): ?>
                    <div class="rounded-md border p-3 text-sm <?= $noticeClasses['red'] ?>"><?= e($flashError) ?></div>
                <?php endif; ?>

                <?php if ($isPaid): ?>
                    <div class="text-center">
                        <p class="text-lg font-semibold text-green-700">Diese Rechnung ist bezahlt.</p>
                        <?php if ($invoice->paidAt !== null): ?>
                            <p class="mt-1 text-sm text-slate-500">Zahlungseingang am <?= e($invoice->paidAt->format('d.m.Y')) ?><?= $page->completedPayment !== null ? ' per ' . e($page->completedPayment->provider->shortLabel()) : '' ?>.</p>
                        <?php endif; ?>
                    </div>
                <?php elseif (!$invoice->isPayable()): ?>
                    <p class="text-center text-sm text-slate-600">Für diese Rechnung ist kein Betrag offen.</p>
                <?php else: ?>
                    <div class="text-center">
                        <p class="text-sm text-slate-500">Offener Betrag</p>
                        <p class="mt-1 text-3xl font-semibold text-slate-900" style="font-variant-numeric:tabular-nums"><?= e($amount) ?></p>
                    </div>

                    <?php if ($page->providers === []): ?>
                        <p class="text-center text-sm text-slate-600">Online-Zahlung steht derzeit nicht zur Verfügung. Bitte nutzen Sie die auf der Rechnung angegebene Bankverbindung.</p>
                    <?php else: ?>
                        <p class="text-center text-sm text-slate-600">Bitte wählen Sie eine Zahlungsart. Sie werden zum Zahlungsanbieter weitergeleitet.</p>
                        <div class="space-y-3">
                            <?php foreach ($page->providers as $provider): ?>
                                <form method="post" action="/zahlung/<?= e(rawurlencode($token)) ?>/<?= e($provider->value) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                                    <?php if ($provider === OnlinePaymentProvider::PayPal): ?>
                                        <button type="submit" class="w-full rounded-md px-4 py-3 text-sm font-semibold text-slate-900 hover:opacity-90" style="background-color:#ffc439">
                                            Mit PayPal bezahlen
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700">
                                            Mit Karte, Apple Pay oder Google Pay bezahlen (SumUp)
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-center text-xs text-slate-400">Die Zahlung wird sicher über den jeweiligen Anbieter abgewickelt. Wir erhalten keine Karten- oder Kontodaten.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
