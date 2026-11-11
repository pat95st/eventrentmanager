<?php
/**
 * Navigation jeder "/einstellungen/..."-Unterseite mit allen
 * Geschwister-Seiten desselben Themenbereichs: ab lg als Seitenleiste links
 * neben dem Inhalt (alle Unterseiten bleiben sichtbar, statt rechts aus der
 * Tab-Leiste herauszulaufen), darunter als horizontale Tab-Leiste (siehe
 * App\Infrastructure\Http\SettingsSections) — wird zentral von layout.php
 * eingebunden ($activeNav und $settingsGroupKey sind zu diesem Zeitpunkt
 * bereits gesetzt), nicht von den einzelnen Einstellungsseiten selbst,
 * damit keine der ~17 Controller-Methoden das einzeln einbinden muss.
 */

/** @var string $activeNav */
/** @var string $settingsGroupKey */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$settingsGroup = \App\Infrastructure\Http\SettingsSections::groups()[$settingsGroupKey];
?>
<aside class="hidden lg:sticky lg:top-24 lg:block" aria-label="<?= e($settingsGroup['label']) ?>">
    <a href="/einstellungen" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; <?= e(t('settings.nav.all_settings')) ?></a>
    <p class="mt-4 px-3 text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e($settingsGroup['label']) ?></p>
    <ul class="mt-2 space-y-0.5">
        <?php foreach ($settingsGroup['pages'] as $settingsPage): ?>
            <?php $isActiveSettingsTab = $settingsPage['key'] === $activeNav; ?>
            <li>
                <a
                    href="<?= e($settingsPage['href']) ?>"
                    class="block rounded-md px-3 py-1.5 text-sm transition-colors <?= $isActiveSettingsTab
                        ? 'bg-brand-50 font-medium text-brand-700 shadow-[inset_3px_0_0_rgb(var(--color-brand-600))] dark:bg-brand-950/40 dark:text-brand-300'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200' ?>"
                    <?= $isActiveSettingsTab ? 'aria-current="page"' : '' ?>
                ><?= e($settingsPage['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>
<nav data-settings-tabs="<?= e($settingsGroupKey) ?>" class="mb-6 overflow-x-auto border-b lg:hidden border-slate-200 dark:border-slate-800" aria-label="<?= e($settingsGroup['label']) ?>">
    <div class="flex gap-1 whitespace-nowrap">
        <a href="/einstellungen" title="Alle Einstellungen" class="border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">&larr; <?= e($settingsGroup['label']) ?></a>
        <?php foreach ($settingsGroup['pages'] as $settingsPage): ?>
            <?php $isActiveSettingsTab = $settingsPage['key'] === $activeNav; ?>
            <a
                href="<?= e($settingsPage['href']) ?>"
                class="border-b-2 px-3 py-2.5 text-sm font-medium transition-colors <?= $isActiveSettingsTab
                    ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-400'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:border-slate-700 dark:hover:text-slate-200' ?>"
                <?= $isActiveSettingsTab ? 'aria-current="page"' : '' ?>
            ><?= e($settingsPage['label']) ?></a>
        <?php endforeach; ?>
    </div>
</nav>
<script>
    // Scrollposition der Tab-Leiste über Seitenwechsel erhalten (Nutzerwunsch):
    // beim Klick merken, beim Laden wiederherstellen; der aktive Tab bleibt
    // in jedem Fall sichtbar.
    (function () {
        var nav = document.currentScript.previousElementSibling;
        if (!nav || !nav.hasAttribute('data-settings-tabs')) { return; }
        var key = 'settingsTabsScroll:' + nav.getAttribute('data-settings-tabs');
        try {
            var saved = window.sessionStorage.getItem(key);
            if (saved !== null) { nav.scrollLeft = parseInt(saved, 10) || 0; }
        } catch (e) {}
        var active = nav.querySelector('[aria-current="page"]');
        if (active) {
            var left = active.getBoundingClientRect().left - nav.getBoundingClientRect().left + nav.scrollLeft;
            var right = left + active.offsetWidth;
            if (left < nav.scrollLeft) {
                nav.scrollLeft = Math.max(0, left - 16);
            } else if (right > nav.scrollLeft + nav.clientWidth) {
                nav.scrollLeft = right - nav.clientWidth + 16;
            }
        }
        function remember() {
            try { window.sessionStorage.setItem(key, String(nav.scrollLeft)); } catch (e) {}
        }
        nav.addEventListener('click', remember);
        nav.addEventListener('scroll', remember, { passive: true });
    })();
</script>
