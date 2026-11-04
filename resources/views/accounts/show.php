<?php
/** @var \App\Application\Banking\Query\AccountLedger $ledger */
/** @var list<array{id: string, label: string, amount: string}> $openReceipts */
/** @var list<array{id: string, label: string, amount: string}> $openInvoices */

use App\Application\Banking\Query\AccountPeriod;
use App\Domain\Banking\AccountAmount;
use App\Domain\Banking\AccountTransactionKind;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$period = $ledger->period;
$account = $ledger->account;
$base = '/konten/' . $account->id;
$url = fn (AccountPeriod $p) => $base . '?' . http_build_query($p->query());
$money = fn (?int $cents, bool $sign = false) => $cents === null ? '–' : AccountAmount::format($cents, $sign);
$csrf = e(Csrf::token());
$hidden = '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
    . '<input type="hidden" name="zeitraum" value="' . e($period->mode) . '">'
    . '<input type="hidden" name="datum" value="' . e($period->anchor->format('Y-m-d')) . '">';
$input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$label = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$btn = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$defaultDate = $today >= $period->from->format('Y-m-d') && $today <= $period->to->format('Y-m-d') ? $today : $period->to->format('Y-m-d');
$chart = [
    'labels' => $ledger->chartLabels,
    'values' => $ledger->chartValues,
    'label' => 'Kontostand ' . $account->label,
];
$rows = array_reverse($ledger->rows); // neueste zuerst
$modeLabels = [AccountPeriod::MONTH => 'Monat', AccountPeriod::YEAR => 'Jahr', AccountPeriod::YEARS => AccountPeriod::YEARS_SPAN . ' Jahre'];
?>
<style>
  .acct-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
  @media (min-width: 768px) { .acct-tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
  .acct-in { color: #047857; } html.dark .acct-in { color: #34d399; }
  .acct-out { color: #dc2626; } html.dark .acct-out { color: #f87171; }
  .acct-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .acct-seg a { padding: .375rem .75rem; font-size: .875rem; font-weight: 500; }
  .acct-seg a + a { border-left: 1px solid rgb(203 213 225); }
  html.dark .acct-seg a + a { border-left-color: rgb(51 65 85); }
  .acct-chart { position: relative; height: 18rem; }
  @media (max-width: 640px) { .acct-chart { height: 14rem; } }
</style>

<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Kontoführung</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($account->label) ?> · <?= e($account->formattedIban()) ?></p>
    </div>
    <?php if (count($ledger->accounts) > 1): ?>
        <form method="get" action="/konten" onsubmit="event.preventDefault(); location.href = '/konten/' + this.konto.value + '?<?= e(http_build_query($period->query())) ?>';">
            <label for="konto" class="<?= $label ?>">Konto</label>
            <select id="konto" name="konto" class="<?= $input ?>" onchange="this.form.requestSubmit()">
                <?php foreach ($ledger->accounts as $option): ?>
                    <option value="<?= e($option->id) ?>" <?= $option->id === $account->id ? 'selected' : '' ?>><?= e($option->label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<!-- Zeitraum: Monat / Jahr / mehrere Jahre, mit Vor/Zurück -->
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="acct-seg inline-flex overflow-hidden rounded-md border border-slate-300 dark:border-slate-700">
        <?php foreach ($modeLabels as $mode => $modeLabel): ?>
            <a href="<?= e($url($period->withMode($mode))) ?>" class="<?= $mode === $period->mode ? 'bg-brand-600 text-white' : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800' ?>"><?= e($modeLabel) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= e($url($period->previous())) ?>" class="<?= $btn ?>" title="Zurück" aria-label="Vorheriger Zeitraum">&larr;</a>
        <span class="text-sm font-semibold text-slate-800 dark:text-slate-200" style="min-width:9rem;text-align:center"><?= e($period->label()) ?></span>
        <a href="<?= e($url($period->next())) ?>" class="<?= $btn ?>" title="Weiter" aria-label="Nächster Zeitraum">&rarr;</a>
        <a href="<?= e($url($period->current())) ?>" class="<?= $btn ?>">Heute</a>
    </div>
</div>

<div class="mb-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="acct-chart"><canvas id="account-chart" data-account-chart="<?= e((string) json_encode($chart, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Verlauf des Kontosaldos"></canvas></div>
    <?php if ($ledger->openingDate !== null && $ledger->openingDate > $period->from): ?>
        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Der Anfangsbestand gilt ab <?= e($ledger->openingDate->format('d.m.Y')) ?> – davor wird kein Saldo angezeigt.</p>
    <?php endif; ?>
</div>

<div class="acct-tiles mb-4">
    <?php foreach ([
        ['Saldo zu Beginn', $money($ledger->startBalanceCents), ''],
        ['Einnahmen', $money($ledger->incomeCents), 'acct-in'],
        ['Ausgaben', $money(-$ledger->expenseCents), 'acct-out'],
        ['Saldo am Ende', $money($ledger->endBalanceCents), ($ledger->endBalanceCents ?? 0) < 0 ? 'acct-out' : ''],
    ] as [$tileLabel, $tileValue, $tileClass]): ?>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs text-slate-500 dark:text-slate-400"><?= e($tileLabel) ?></p>
            <p class="acct-num mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100 <?= $tileClass ?>"><?= e($tileValue) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="mb-3 flex flex-wrap gap-2">
    <button type="button" data-dialog-open="dlg-manual" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">+ Buchung ohne Beleg</button>
    <button type="button" data-dialog-open="dlg-receipt" class="<?= $btn ?>">Beleg abbuchen</button>
    <button type="button" data-dialog-open="dlg-invoice" class="<?= $btn ?>">Rechnungszahlung</button>
    <button type="button" data-dialog-open="dlg-opening" class="<?= $btn ?>">Anfangsbestand: <?= e($money($ledger->openingCents)) ?><?= $ledger->openingDate !== null ? ' ab ' . e($ledger->openingDate->format('d.m.Y')) : '' ?></button>
    <a href="/zahlungsabgleich" class="<?= $btn ?>">Kontoauszug importieren</a>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="overflow-x-auto" style="position:relative">
        <table class="w-full text-sm" style="min-width:40rem">
            <thead class="bg-slate-50 dark:bg-slate-800 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2">Datum</th>
                    <th class="px-4 py-2">Buchung</th>
                    <th class="px-4 py-2">Art</th>
                    <th class="px-4 py-2 text-right">Betrag</th>
                    <th class="px-4 py-2 text-right">Saldo</th>
                    <th class="px-4 py-2" aria-label="Aktionen"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">Keine Buchungen im Zeitraum <?= e($period->label()) ?>.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): $t = $row['transaction']; ?>
                    <tr>
                        <td class="acct-num px-4 py-2 text-slate-600 dark:text-slate-400"><?= e($t->bookingDate->format('d.m.Y')) ?></td>
                        <td class="px-4 py-2">
                            <span class="text-slate-900 dark:text-slate-100">
                                <?php if ($t->receiptId !== null): ?><a href="/belege/<?= e($t->receiptId) ?>" class="hover:underline"><?= e($t->description) ?></a>
                                <?php elseif ($t->invoiceId !== null): ?><a href="/rechnungen/<?= e($t->invoiceId) ?>" class="hover:underline"><?= e($t->description) ?></a>
                                <?php else: ?><?= e($t->description) ?><?php endif; ?>
                            </span>
                            <?php if ($t->counterparty !== null): ?><span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($t->counterparty) ?></span><?php endif; ?>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400"><?= e($t->kind->label()) ?></td>
                        <td class="acct-num px-4 py-2 text-right font-medium <?= $t->amountCents >= 0 ? 'acct-in' : 'acct-out' ?>"><?= e($money($t->amountCents, true)) ?></td>
                        <td class="acct-num px-4 py-2 text-right text-slate-600 dark:text-slate-400"><?= e($money($row['balanceCents'])) ?></td>
                        <td class="px-4 py-2 text-right">
                            <form method="post" action="/konten/buchungen/<?= e($t->id) ?>/loeschen" data-confirm="Buchung „<?= e($t->description) ?>“ löschen?<?= $t->kind !== AccountTransactionKind::Manual && ($t->receiptId !== null || $t->invoiceId !== null) ? ' Der zugehörige Beleg bzw. die Rechnung wird wieder auf offen gesetzt.' : '' ?>" data-confirm-style="danger">
                                <?= $hidden ?>
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Dialoge -->
<dialog id="dlg-manual" class="modal-panel modal-panel-sm rounded-lg border border-slate-200 bg-white p-0 dark:border-slate-700 dark:bg-slate-900">
    <form method="post" action="<?= e($base) ?>/buchungen" class="space-y-4 p-4">
        <?= $hidden ?>
        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Buchung ohne Beleg</h2>
        <div class="flex gap-4 text-sm text-slate-700 dark:text-slate-300">
            <label class="flex items-center gap-2"><input type="radio" name="direction" value="out" checked> Ausgang</label>
            <label class="flex items-center gap-2"><input type="radio" name="direction" value="in"> Eingang</label>
        </div>
        <div><label class="<?= $label ?>" for="m-desc">Buchungstext *</label><input id="m-desc" name="description" required maxlength="255" placeholder="z. B. Kontoführungsgebühr" class="<?= $input ?>"></div>
        <div><label class="<?= $label ?>" for="m-cp">Empfänger / Auftraggeber</label><input id="m-cp" name="counterparty" maxlength="255" class="<?= $input ?>"></div>
        <div class="flex gap-3">
            <div class="flex-1"><label class="<?= $label ?>" for="m-amount">Betrag in € *</label><input id="m-amount" name="amount" required inputmode="decimal" placeholder="0,00" class="<?= $input ?>"></div>
            <div class="flex-1"><label class="<?= $label ?>" for="m-date">Datum *</label><input id="m-date" type="date" name="booking_date" required value="<?= e($defaultDate) ?>" class="<?= $input ?>"></div>
        </div>
        <div class="flex justify-end gap-2"><button type="button" data-dialog-close class="<?= $btn ?>">Abbrechen</button><button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Buchen</button></div>
    </form>
</dialog>

<dialog id="dlg-receipt" class="modal-panel modal-panel-sm rounded-lg border border-slate-200 bg-white p-0 dark:border-slate-700 dark:bg-slate-900">
    <form method="post" action="<?= e($base) ?>/beleg" class="space-y-4 p-4">
        <?= $hidden ?>
        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Beleg vom Konto abbuchen</h2>
        <?php if ($openReceipts === []): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">Es gibt keine offenen Belege.</p>
        <?php else: ?>
            <div><label class="<?= $label ?>" for="r-id">Offener Beleg *</label>
                <select id="r-id" name="receipt_id" required class="<?= $input ?>">
                    <option value="">Bitte wählen…</option>
                    <?php foreach ($openReceipts as $receipt): ?><option value="<?= e($receipt['id']) ?>"><?= e($receipt['label']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="<?= $label ?>" for="r-date">Abbuchungsdatum *</label><input id="r-date" type="date" name="booking_date" required value="<?= e($defaultDate) ?>" class="<?= $input ?>" style="max-width:12rem"></div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Der Belegbetrag wird abgebucht und der Beleg als bezahlt markiert.</p>
        <?php endif; ?>
        <div class="flex justify-end gap-2"><button type="button" data-dialog-close class="<?= $btn ?>">Abbrechen</button><?php if ($openReceipts !== []): ?><button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Abbuchen</button><?php endif; ?></div>
    </form>
</dialog>

<dialog id="dlg-invoice" class="modal-panel modal-panel-sm rounded-lg border border-slate-200 bg-white p-0 dark:border-slate-700 dark:bg-slate-900">
    <form method="post" action="<?= e($base) ?>/rechnung" class="space-y-4 p-4">
        <?= $hidden ?>
        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Rechnungszahlung verbuchen</h2>
        <?php if ($openInvoices === []): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">Es gibt keine offenen (versendeten) Rechnungen.</p>
        <?php else: ?>
            <div><label class="<?= $label ?>" for="i-id">Offene Rechnung *</label>
                <select id="i-id" name="invoice_id" required class="<?= $input ?>" onchange="var o=this.options[this.selectedIndex]; if (o.dataset.amount) { document.getElementById('i-amount').value = o.dataset.amount; }">
                    <option value="">Bitte wählen…</option>
                    <?php foreach ($openInvoices as $invoice): ?><option value="<?= e($invoice['id']) ?>" data-amount="<?= e($invoice['amount']) ?>"><?= e($invoice['label']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-3">
                <div class="flex-1"><label class="<?= $label ?>" for="i-amount">Gezahlter Betrag in € *</label><input id="i-amount" name="amount" required inputmode="decimal" class="<?= $input ?>"></div>
                <div class="flex-1"><label class="<?= $label ?>" for="i-date">Zahlungsdatum *</label><input id="i-date" type="date" name="booking_date" required value="<?= e($defaultDate) ?>" class="<?= $input ?>"></div>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Die Rechnung wird als bezahlt markiert.</p>
        <?php endif; ?>
        <div class="flex justify-end gap-2"><button type="button" data-dialog-close class="<?= $btn ?>">Abbrechen</button><?php if ($openInvoices !== []): ?><button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Verbuchen</button><?php endif; ?></div>
    </form>
</dialog>

<dialog id="dlg-opening" class="modal-panel modal-panel-sm rounded-lg border border-slate-200 bg-white p-0 dark:border-slate-700 dark:bg-slate-900">
    <form method="post" action="<?= e($base) ?>/anfangsbestand" class="space-y-4 p-4">
        <?= $hidden ?>
        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Anfangsbestand</h2>
        <div class="flex gap-3">
            <div class="flex-1"><label class="<?= $label ?>" for="o-amount">Betrag in €</label><input id="o-amount" name="amount" inputmode="decimal" value="<?= e(number_format($ledger->openingCents / 100, 2, ',', '.')) ?>" class="<?= $input ?>"></div>
            <div class="flex-1"><label class="<?= $label ?>" for="o-date">Stand am (Tagesbeginn)</label><input id="o-date" type="date" name="date" value="<?= e($ledger->openingDate?->format('Y-m-d') ?? '') ?>" class="<?= $input ?>"></div>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">Kontostand zu Beginn des Tages; Buchungen davor werden für den Saldo nicht berücksichtigt. Ohne Datum gilt er für alle Buchungen. Negative Beträge mit „-“.</p>
        <div class="flex justify-end gap-2"><button type="button" data-dialog-close class="<?= $btn ?>">Abbrechen</button><button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Speichern</button></div>
    </form>
</dialog>

<script>
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-dialog-open]');
        if (opener) {
            var dialog = document.getElementById(opener.getAttribute('data-dialog-open'));
            if (dialog) { dialog.showModal(); var first = dialog.querySelector('select, input:not([type=hidden]):not([type=radio])'); if (first) { first.focus(); } }
            return;
        }
        var closer = event.target.closest('[data-dialog-close]');
        if (closer) { closer.closest('dialog').close(); }
    });
</script>
<script src="/assets/js/account-chart.js" defer></script>
