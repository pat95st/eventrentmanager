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
<nav class="mb-6 overflow-x-auto border-b border-slate-200 dark:border-slate-800" aria-label="<?= e($settingsGroup['label']) ?>">
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
