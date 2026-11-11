<?php
/**
 * Einstellungen › Dry-Hire-Staffel (Migration 142).
 *
 * @var list<array{0: string, 1: string}> $tiers [ab Tag, Prozent]
 * @var ?string $error
 * @var int $dryHireArticleCount
 * @var int $dryHireCustomerCount
 */

use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$input = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$card = 'rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900';
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Dry-Hire-Staffel</h1>
    <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
        Preise für Dry-Hire-Kunden (Miete ohne Personal). Jeder Artikel kann zusätzlich zum normalen Tagesmietsatz einen Dry-Hire-Tagesmietsatz haben;
        Kunden werden in den Stammdaten als Dry-Hire-Kunde markiert. Die Staffel legt fest, wie viel jeder weitere Miettag kostet – für beliebig lange Mietdauer.
    </p>
    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= (int) $dryHireArticleCount ?> Artikel mit Dry-Hire-Preis · <?= (int) $dryHireCustomerCount ?> Dry-Hire-Kunden</p>
</div>

<div class="grid gap-6" style="grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr));">
    <section class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Stufen</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">„Ab Tag X kostet jeder Tag P % des Dry-Hire-Tagesmietsatzes“ – bis zur nächsten Stufe. Die letzte Stufe gilt für alle weiteren Tage.</p>
        <?php if ($error !== null): ?>
            <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/einstellungen/dry-hire-staffel" id="dh-form">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr><th class="pb-2 text-left" style="padding-right:.5rem">ab Tag</th><th class="pb-2 text-left" style="padding-right:.5rem">% je Tag</th><th></th></tr>
                </thead>
                <tbody id="dh-rows">
                    <?php foreach ($tiers as [$day, $pct]): ?>
                        <tr data-row>
                            <td class="py-1" style="padding-right:.5rem"><input type="number" name="from_day[]" value="<?= e($day) ?>" min="1" max="3650" required class="<?= $input ?>" <?= $day === '1' ? 'readonly' : '' ?>></td>
                            <td class="py-1" style="padding-right:.5rem"><input type="text" name="percentage[]" value="<?= e(str_contains((string) $pct, '.') ? rtrim(rtrim((string) $pct, '0'), '.') : (string) $pct) ?>" inputmode="decimal" required class="<?= $input ?>"></td>
                            <td class="py-1 text-right"><?php if ($day !== '1'): ?><button type="button" data-remove class="text-sm text-slate-400 hover:text-red-600" aria-label="Stufe entfernen">✕</button><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-3 flex items-center gap-3">
                <button type="button" id="dh-add" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">+ Stufe</button>
                <span class="flex-1"></span>
                <button type="submit" class="inline-flex items-center rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
            </div>
        </form>
    </section>

    <section class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Vorschau</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Beispiel für einen Dry-Hire-Tagesmietsatz von
            <input type="text" id="dh-example" value="100" inputmode="decimal" class="inline-block rounded border border-slate-300 px-2 py-0.5 text-xs dark:border-slate-700" style="width: 5rem"> €
            (ohne Preisrundung).</p>
        <table class="w-full text-sm">
            <thead class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><tr><th class="pb-2 text-left">Miettage</th><th class="pb-2 text-right">Faktor</th><th class="pb-2 text-right">Mietpreis</th><th class="pb-2 text-right">je Tag</th></tr></thead>
            <tbody id="dh-preview" class="divide-y divide-slate-100 dark:divide-slate-800"></tbody>
        </table>
    </section>
</div>
<template id="dh-row-tpl">
    <tr data-row>
        <td class="py-1" style="padding-right:.5rem"><input type="number" name="from_day[]" min="2" max="3650" required class="<?= $input ?>"></td>
        <td class="py-1" style="padding-right:.5rem"><input type="text" name="percentage[]" inputmode="decimal" required class="<?= $input ?>"></td>
        <td class="py-1 text-right"><button type="button" data-remove class="text-sm text-slate-400 hover:text-red-600" aria-label="Stufe entfernen">✕</button></td>
    </tr>
</template>
<script>
(function () {
    var rows = document.getElementById('dh-rows'), tpl = document.getElementById('dh-row-tpl');
    var days = [1, 2, 3, 4, 5, 7, 10, 14, 21, 30, 60];
    function tiers() {
        var list = [];
        rows.querySelectorAll('[data-row]').forEach(function (r) {
            var d = parseInt(r.querySelector('[name="from_day[]"]').value, 10), p = parseFloat(String(r.querySelector('[name="percentage[]"]').value).replace(',', '.'));
            if (d >= 1 && !isNaN(p)) { list.push({ from: d, pct: p }); }
        });
        list.sort(function (a, b) { return a.from - b.from; });
        return list;
    }
    function factor(n, list) {
        var total = 0;
        for (var d = 1; d <= n; d++) {
            var pct = 100;
            list.forEach(function (t) { if (t.from <= d) { pct = t.pct; } });
            total += pct / 100;
        }
        return total;
    }
    function fmt(x) { return x.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function render() {
        var list = tiers(), base = parseFloat(String(document.getElementById('dh-example').value).replace(',', '.')) || 0;
        document.getElementById('dh-preview').innerHTML = days.map(function (n) {
            var f = factor(n, list);
            return '<tr><td class="py-1.5">' + n + ' ' + (n === 1 ? 'Tag' : 'Tage') + '</td><td class="py-1.5 text-right tabular-nums">' + f.toLocaleString('de-DE', { maximumFractionDigits: 3 }) + '</td><td class="py-1.5 text-right tabular-nums">' + fmt(base * f) + ' €</td><td class="py-1.5 text-right tabular-nums text-slate-500">' + fmt(base * f / n) + ' €</td></tr>';
        }).join('');
    }
    document.getElementById('dh-add').addEventListener('click', function () {
        var r = tpl.content.firstElementChild.cloneNode(true);
        var last = tiers().pop();
        r.querySelector('[name="from_day[]"]').value = last ? last.from + 7 : 2;
        r.querySelector('[name="percentage[]"]').value = last ? Math.max(0, Math.round(last.pct / 2)) : 50;
        rows.appendChild(r); render();
    });
    rows.addEventListener('click', function (e) { var b = e.target.closest('[data-remove]'); if (b) { b.closest('[data-row]').remove(); render(); } });
    document.getElementById('dh-form').addEventListener('input', render);
    document.getElementById('dh-example').addEventListener('input', render);
    render();
})();
</script>
