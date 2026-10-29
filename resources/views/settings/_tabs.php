<?php
/**
 * Tab-Leiste über dem eigentlichen Seiteninhalt jeder "/einstellungen/..."-
 * Unterseite, mit allen Geschwister-Seiten desselben Themenbereichs (siehe
 * App\Infrastructure\Http\SettingsSections) — wird zentral von layout.php
 * eingebunden ($activeNav und $settingsGroupKey sind zu diesem Zeitpunkt
 * bereits gesetzt), nicht von den einzelnen Einstellungsseiten selbst,
 * damit keine der ~17 Controller-Methoden das einzeln einbinden muss.
 */

/** @var string $activeNav */
/** @var string $settingsGroupKey */

use function App\Infrastructure\Http\e;

$settingsGroup = \App\Infrastructure\Http\SettingsSections::groups()[$settingsGroupKey];
?>
<nav data-settings-tabs="<?= e($settingsGroupKey) ?>" class="mb-6 overflow-x-auto border-b border-slate-200 dark:border-slate-800" aria-label="<?= e($settingsGroup['label']) ?>">
    <div class="flex gap-1 whitespace-nowrap">
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
