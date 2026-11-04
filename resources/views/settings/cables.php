<?php
/** @var \App\Application\Cable\Query\CableSettings $settings */

use App\Domain\Article\ProtectionClass;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$input = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$btn = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';

/** Formularfelder eines Leitungstyps (bearbeiten oder neu). */
$typeFields = function (?\App\Domain\Cable\CableType $type) use ($settings, $input): void {
    $prefix = $type?->id ?? 'neu';
    ?>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="name-<?= e($prefix) ?>">Bezeichnung *</label>
            <input id="name-<?= e($prefix) ?>" name="name" required maxlength="150" value="<?= e($type?->name ?? '') ?>" placeholder="z. B. H07RN-F 5G6" class="<?= $input ?>">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="desc-<?= e($prefix) ?>">Beschreibung</label>
            <input id="desc-<?= e($prefix) ?>" name="description" maxlength="500" value="<?= e($type?->description ?? '') ?>" placeholder="optional" class="<?= $input ?>">
        </div>
    </div>
    <div class="mt-3 flex flex-wrap items-end gap-4">
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="requires_electrical_inspection" value="1" <?= $type?->requiresElectricalInspection ? 'checked' : '' ?>>
            Elektroprüfung erforderlich (Vorauswahl im Artikel)
        </label>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="pc-<?= e($prefix) ?>">Schutzklasse (Vorgabe)</label>
            <select id="pc-<?= e($prefix) ?>" name="protection_class" class="<?= $input ?>">
                <option value="">–</option>
                <?php foreach (ProtectionClass::all() as $pc): ?>
                    <option value="<?= e($pc->value) ?>" <?= $type?->protectionClass === $pc ? 'selected' : '' ?>><?= e($pc->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="sort-<?= e($prefix) ?>">Reihenfolge</label>
            <input id="sort-<?= e($prefix) ?>" name="sort_order" type="number" value="<?= e((string) ($type?->sortOrder ?? '')) ?>" class="<?= $input ?>" style="width:6rem">
        </div>
    </div>
    <fieldset class="mt-3">
        <legend class="text-xs font-medium text-slate-600 dark:text-slate-400">Passende Steckverbinder</legend>
        <?php if ($settings->connectors === []): ?>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Lege zuerst unten Steckverbinder an.</p>
        <?php else: ?>
            <div class="mt-1 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($settings->connectors as $connector): ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="connector_ids[]" value="<?= e($connector->id) ?>" <?= $type !== null && $type->allowsConnector($connector->id) ? 'checked' : '' ?>>
                        <?= e($connector->name) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </fieldset>
    <?php
};
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Leitungen</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Leitungstypen und Steckverbinder für Leitungs-Artikel (Kategorie „Leitungen“). Je Leitungstyp legst du fest, welche Steckverbinder sinnvoll sind –
        nur diese stehen beim Anlegen eines Leitungs-Artikels zur Auswahl – und ob eine Elektroprüfung vorausgewählt wird.
    </p>
</div>

<section id="leitungstypen" class="mb-6 space-y-4">
    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Leitungstypen</h2>
    <?php foreach ($settings->types as $type): ?>
        <?php $used = $settings->usage[$type->id] ?? 0; ?>
        <div id="typ-<?= e($type->id) ?>" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <form method="post" action="/einstellungen/leitungen/typen/<?= e($type->id) ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <?php $typeFields($type); ?>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <span class="text-xs text-slate-500 dark:text-slate-400"><?= $used ?> Artikel</span>
                    <button type="submit" class="<?= $btn ?>">Speichern</button>
                </div>
            </form>
            <?php if ($used === 0): ?>
                <form method="post" action="/einstellungen/leitungen/typen/<?= e($type->id) ?>/loeschen" class="mt-2 text-right" data-confirm="Leitungstyp „<?= e($type->name) ?>“ löschen?" data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Neuer Leitungstyp</h3>
        <form method="post" action="/einstellungen/leitungen/typen">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <?php $typeFields(null); ?>
            <div class="mt-4 text-right">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Leitungstyp anlegen</button>
            </div>
        </form>
    </div>
</section>

<section id="steckverbinder" class="space-y-3">
    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Steckverbinder</h2>
    <div class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($settings->connectors === []): ?>
            <p class="p-4 text-sm text-slate-500 dark:text-slate-400">Noch keine Steckverbinder angelegt.</p>
        <?php endif; ?>
        <?php foreach ($settings->connectors as $connector): ?>
            <?php $used = $settings->usage[$connector->id] ?? 0; ?>
            <div class="flex flex-wrap items-end gap-3 p-3">
                <form method="post" action="/einstellungen/leitungen/steckverbinder/<?= e($connector->id) ?>" class="flex min-w-0 flex-1 flex-wrap items-end gap-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="con-<?= e($connector->id) ?>">Bezeichnung</label>
                        <input id="con-<?= e($connector->id) ?>" name="connector_name" required maxlength="150" value="<?= e($connector->name) ?>" class="<?= $input ?>">
                    </div>
                    <input name="sort_order" type="number" value="<?= e((string) $connector->sortOrder) ?>" class="<?= $input ?>" style="width:5.5rem" aria-label="Reihenfolge">
                    <button type="submit" class="<?= $btn ?>">Speichern</button>
                </form>
                <span class="text-xs text-slate-500 dark:text-slate-400" style="min-width:4.5rem"><?= $used ?> Artikel</span>
                <?php if ($used === 0): ?>
                    <form method="post" action="/einstellungen/leitungen/steckverbinder/<?= e($connector->id) ?>/loeschen" data-confirm="Steckverbinder „<?= e($connector->name) ?>“ löschen?" data-confirm-style="danger">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="/einstellungen/leitungen/steckverbinder" class="rounded-lg border border-dashed border-slate-300 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Neuer Steckverbinder</h3>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="connector_name_new">Bezeichnung *</label>
                <input id="connector_name_new" name="connector_name" required maxlength="150" placeholder="z. B. CEE 63 A Stecker rot" class="<?= $input ?>">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="connector_sort_new">Reihenfolge</label>
                <input id="connector_sort_new" name="sort_order" type="number" class="<?= $input ?>" style="width:5.5rem">
            </div>
        </div>
        <?php if ($settings->types !== []): ?>
            <fieldset class="mt-3">
                <legend class="text-xs font-medium text-slate-600 dark:text-slate-400">Gleich bei diesen Leitungstypen erlauben</legend>
                <div class="mt-1 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($settings->types as $type): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="checkbox" name="cable_type_ids[]" value="<?= e($type->id) ?>"> <?= e($type->name) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>
        <div class="mt-4 text-right">
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Steckverbinder anlegen</button>
        </div>
    </form>
</section>
