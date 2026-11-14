<?php
/** @var array<string, array{label: string, desc?: string, pages: list<array{key: string, href: string, label: string, desc?: string}>}> $groups */

use function App\Infrastructure\Http\e;
?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Einstellungen</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Alle Einstellungen nach Themen. Tippe zum Suchen, z. B. „USt“, „Logo“ oder „Mahnung“.</p>
    </div>
</div>
<div class="mb-6">
    <input type="search" id="settings-search" placeholder="Einstellung suchen…" autocomplete="off"
           class="w-full rounded-md border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
</div>

<style>
  .settings-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
  @media (min-width: 768px) { .settings-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (min-width: 1280px) { .settings-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
</style>

<div class="settings-grid">
    <?php foreach ($groups as $groupKey => $group): ?>
        <section data-settings-group class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e($group['label']) ?></h2>
            <?php if (!empty($group['desc'])): ?><p class="text-xs text-slate-500 dark:text-slate-400"><?= e($group['desc']) ?></p><?php endif; ?>
            <ul class="mt-3 space-y-1">
                <?php foreach ($group['pages'] as $settingsPage): ?>
                    <li data-settings-item data-search="<?= e(mb_strtolower($settingsPage['label'] . ' ' . ($settingsPage['desc'] ?? ''))) ?>">
                        <a href="<?= e($settingsPage['href']) ?>" class="block rounded-md px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <span class="block text-sm font-medium text-brand-700 dark:text-brand-400"><?= e($settingsPage['label']) ?></span>
                            <?php if (!empty($settingsPage['desc'])): ?><span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($settingsPage['desc']) ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
<p id="settings-search-empty" class="mt-4 text-sm text-slate-500 dark:text-slate-400" hidden>Keine passende Einstellung gefunden.</p>

<script>
(function () {
    var input = document.getElementById('settings-search');
    var empty = document.getElementById('settings-search-empty');
    // Häufige Begriffe auf die Seitennamen abbilden.
    var SYNONYMS = { 'ust': 'umsatzsteuer', 'mwst': 'umsatzsteuer', 'iban': 'bankverbindungen konten',
        'konto': 'bankverbindungen', 'mail': 'e-mail smtp', 'agb': 'vermietbedingungen rechtliches',
        'urlaub': 'betriebsurlaub', 'mahnung': 'mahnwesen', 'schrift': 'schriften', 'font': 'schriften', 'farbe': 'akzentfarbe pdf-design',
        'backup': 'sicherung export', 'whatsapp': 'messenger integrationen', 'drucker': 'etikettendrucker', 'kabel': 'leitungen',
        'stagebox': 'digitale protokolle', 'dante': 'digitale protokolle', 'madi': 'digitale protokolle' };
    function run() {
        var term = input.value.trim().toLowerCase();
        var terms = term === '' ? [] : [term].concat((SYNONYMS[term] || '').split(' ').filter(Boolean));
        var visibleTotal = 0;
        document.querySelectorAll('[data-settings-group]').forEach(function (group) {
            var visible = 0;
            group.querySelectorAll('[data-settings-item]').forEach(function (item) {
                var hay = item.getAttribute('data-search');
                // Wortanfänge vergleichen (sonst findet „schrift“ auch „Anschrift“).
                var words = hay.split(/[\s,()\/&.\-–]+/);
                var match = terms.length === 0 || terms.some(function (t) {
                    return t.indexOf(' ') !== -1 ? hay.indexOf(t) !== -1 : words.some(function (w) { return w.indexOf(t) === 0; });
                });
                item.hidden = !match;
                if (match) { visible++; }
            });
            group.hidden = visible === 0;
            visibleTotal += visible;
        });
        empty.hidden = visibleTotal !== 0;
    }
    input.addEventListener('input', run);
    input.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') { return; }
        var first = document.querySelector('[data-settings-item]:not([hidden]) a');
        if (first) { event.preventDefault(); location.href = first.href; }
    });
    input.focus();
})();
</script>
