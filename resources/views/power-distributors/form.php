<?php
/**
 * Stromverteiler-Angaben eines Artikels bearbeiten (Migration 117).
 *
 * @var \App\Application\Article\Query\ArticleDetail $detail
 * @var \App\Domain\PowerDistributor\PowerDistributor|null $distributor
 * @var array<string, string> $errors
 * @var array<string, mixed>|null $old
 */

use App\Domain\PowerDistributor\ConnectorType;
use App\Domain\PowerDistributor\ProtectiveDevice;
use App\Domain\PowerDistributor\ProtectiveDeviceType;
use function App\Infrastructure\Http\e;

$article = $detail->article;
$input = 'w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900';
$label = 'block text-[11px] text-slate-500 dark:text-slate-400';

// Zeilen: nach Validierungsfehler aus $old, sonst aus den gespeicherten Daten.
if ($old !== null) {
    $inletType = $old['inlet_type'];
    $protectionClass = ($old['protection_class'] ?? '') !== '' ? $old['protection_class'] : 'I';
    $notes = $old['notes'];
    $deviceRows = $old['devices'];
    $outletRows = $old['outlets'];
} else {
    $inletType = $distributor?->inletType->value ?? '';
    // Standard Schutzklasse I; bei einem bereits eingerichteten Verteiler die gespeicherte.
    $protectionClass = $distributor !== null ? ($article->protectionClass?->value ?? 'I') : 'I';
    $notes = $distributor?->notes ?? '';
    $deviceRows = array_map(static fn ($d) => [
        'key' => $d->id, 'label' => $d->label, 'type' => $d->type->value, 'poles' => (string) ($d->poles ?? ''),
        'rcd_type' => (string) ($d->rcdType ?? ''), 'residual_current_ma' => (string) ($d->residualCurrentMa ?? ''),
        'rated_current_a' => (string) ($d->ratedCurrentA ?? ''), 'characteristic' => (string) ($d->characteristic ?? ''),
        'upstream_key' => (string) ($d->upstreamDeviceId ?? ''),
    ], $distributor?->devices ?? []);
    $outletRows = array_map(static fn ($o) => [
        'connector_type' => $o->connectorType->value, 'quantity' => (string) $o->quantity, 'label' => (string) ($o->label ?? ''),
        'device_key' => (string) ($o->deviceId ?? ''),
    ], $distributor?->outlets ?? []);
}

$options = static function (array $values, string $selected, string $empty = '', ?callable $text = null): string {
    $html = $empty !== '' ? '<option value="">' . e($empty) . '</option>' : '';
    foreach ($values as $value => $caption) {
        $value = (string) $value;
        $html .= '<option value="' . e($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . e($caption) . '</option>';
    }

    return $html;
};
$connectorOptions = [];
foreach (ConnectorType::cases() as $case) {
    $connectorOptions[$case->value] = $case->label();
}
$typeOptions = [];
foreach (ProtectiveDeviceType::cases() as $case) {
    $typeOptions[$case->value] = $case->label();
}
$assoc = static fn (array $values, string $suffix = '') => array_combine(array_map('strval', $values), array_map(static fn ($v) => $v . $suffix, $values));

$renderDeviceRow = static function (string $index, array $row) use ($input, $label, $options, $typeOptions, $assoc): void {
    $name = static fn (string $field) => 'devices[' . $index . '][' . $field . ']';
    ?>
    <div data-device-row class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <input type="hidden" name="<?= $name('key') ?>" value="<?= e($row['key'] ?? '') ?>" data-device-key>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div>
                <label class="<?= $label ?>">Bezeichnung</label>
                <input name="<?= $name('label') ?>" value="<?= e($row['label'] ?? '') ?>" placeholder="z. B. F1" maxlength="50" class="<?= $input ?>" data-device-label>
            </div>
            <div>
                <label class="<?= $label ?>">Art</label>
                <select name="<?= $name('type') ?>" class="<?= $input ?>" data-device-type><?= $options($typeOptions, $row['type'] ?? 'mcb') ?></select>
            </div>
            <div>
                <label class="<?= $label ?>">Polzahl</label>
                <select name="<?= $name('poles') ?>" class="<?= $input ?>"><?= $options($assoc(ProtectiveDevice::POLES, '-polig'), $row['poles'] ?? '', '–') ?></select>
            </div>
            <div class="flex items-end justify-end">
                <button type="button" data-remove-row class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
            </div>
            <div data-rcd-field>
                <label class="<?= $label ?>">RCD-Typ</label>
                <select name="<?= $name('rcd_type') ?>" class="<?= $input ?>"><?= $options(array_combine(ProtectiveDevice::RCD_TYPES, array_map(static fn ($t) => 'Typ ' . $t, ProtectiveDevice::RCD_TYPES)), $row['rcd_type'] ?? 'A', '–') ?></select>
            </div>
            <div data-rcd-field>
                <label class="<?= $label ?>">Auslösestrom</label>
                <select name="<?= $name('residual_current_ma') ?>" class="<?= $input ?>"><?= $options($assoc(ProtectiveDevice::RESIDUAL_CURRENTS_MA, ' mA'), $row['residual_current_ma'] ?? '30', '–') ?></select>
            </div>
            <div data-mcb-field>
                <label class="<?= $label ?>">Charakteristik</label>
                <select name="<?= $name('characteristic') ?>" class="<?= $input ?>"><?= $options(array_combine(ProtectiveDevice::CHARACTERISTICS, ProtectiveDevice::CHARACTERISTICS), $row['characteristic'] ?? 'B', '–') ?></select>
            </div>
            <div>
                <label class="<?= $label ?>" data-rated-label>Sicherungsgröße</label>
                <select name="<?= $name('rated_current_a') ?>" class="<?= $input ?>"><?= $options($assoc(ProtectiveDevice::RATED_CURRENTS_A, ' A'), $row['rated_current_a'] ?? '', '–') ?></select>
            </div>
            <div data-upstream-field class="col-span-2">
                <label class="<?= $label ?>">Vorgeschaltet</label>
                <select name="<?= $name('upstream_key') ?>" class="<?= $input ?>" data-upstream-select data-selected="<?= e($row['upstream_key'] ?? '') ?>">
                    <option value="">kein FI (LS direkt an der Zuleitung)</option>
                </select>
            </div>
        </div>
    </div>
    <?php
};

$renderOutletRow = static function (string $index, array $row) use ($input, $label, $options, $connectorOptions): void {
    $name = static fn (string $field) => 'outlets[' . $index . '][' . $field . ']';
    ?>
    <div data-outlet-row class="flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <div style="flex:3 1 12rem">
            <label class="<?= $label ?>">Steckverbindung</label>
            <select name="<?= $name('connector_type') ?>" class="<?= $input ?>"><?= $options($connectorOptions, $row['connector_type'] ?? 'schuko') ?></select>
        </div>
        <div style="flex:0 0 5rem">
            <label class="<?= $label ?>">Anzahl</label>
            <input type="number" min="1" max="99" name="<?= $name('quantity') ?>" value="<?= e($row['quantity'] ?? '1') ?>" class="<?= $input ?>">
        </div>
        <div style="flex:1 1 7rem">
            <label class="<?= $label ?>">Bezeichnung</label>
            <input name="<?= $name('label') ?>" value="<?= e($row['label'] ?? '') ?>" maxlength="50" placeholder="optional" class="<?= $input ?>">
        </div>
        <div style="flex:3 1 14rem">
            <label class="<?= $label ?>">Absicherung</label>
            <select name="<?= $name('device_key') ?>" class="<?= $input ?>" data-outlet-device data-selected="<?= e($row['device_key'] ?? '') ?>">
                <option value="">direkt von der Zuleitung</option>
            </select>
        </div>
        <div class="flex justify-end" style="flex:0 0 auto">
            <button type="button" data-remove-row class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
        </div>
    </div>
    <?php
};
?>
<div class="mb-6">
    <a href="/artikel/<?= e($article->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück zum Artikel</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Stromverteiler: <?= e($article->name) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Zuleitung, Schutzorgane (FI, LS, FI/LS) und Abgänge mit ihrer Absicherung.</p>
</div>

<?php if ($errors !== []): ?>
    <div class="mb-4 max-w-4xl rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800">
        <?php foreach ($errors as $message): ?><p><?= e($message) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/artikel/<?= e($article->id) ?>/stromverteiler" class="max-w-4xl space-y-6" data-distributor-form>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Zuleitung</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label for="inlet_type" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Art der Zuleitung *</label>
                <select id="inlet_type" name="inlet_type" required class="mt-1 <?= $input ?>"><?= $options($connectorOptions, $inletType, 'Bitte wählen …') ?></select>
            </div>
            <div>
                <label for="protection_class" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Schutzklasse *</label>
                <select id="protection_class" name="protection_class" required class="mt-1 <?= $input ?>">
                    <?php foreach (\App\Domain\Article\ProtectionClass::cases() as $case): ?>
                        <option value="<?= e($case->value) ?>" <?= $protectionClass === $case->value ? 'selected' : '' ?>><?= e($case->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Stromverteiler sind immer elektrisch zu prüfen; der Artikel wird beim Speichern entsprechend markiert.</p>
            </div>
            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Bemerkung</label>
                <textarea id="notes" name="notes" rows="2" class="mt-1 <?= $input ?>"><?= e($notes) ?></textarea>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Schutzorgane</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">RCD (FI), LS-Schalter und FI/LS-Kombinationen. Ein LS kann hinter einem FI liegen. Ohne Bezeichnung wird F1, F2, … vergeben.</p>
            </div>
            <button type="button" data-add-device class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">+ Schutzorgan</button>
        </div>
        <div data-devices class="space-y-3">
            <?php foreach (array_values($deviceRows) as $i => $row): $renderDeviceRow((string) $i, $row); endforeach; ?>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Abgänge</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Direkt von der Zuleitung, nur hinter FI, nur hinter LS, hinter FI und LS (LS mit vorgeschaltetem FI wählen) oder hinter FI/LS.</p>
            </div>
            <button type="button" data-add-outlet class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">+ Abgang</button>
        </div>
        <div data-outlets class="space-y-3">
            <?php foreach (array_values($outletRows) as $i => $row): $renderOutletRow((string) $i, $row); endforeach; ?>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
            <a href="/artikel/<?= e($article->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
        </div>
    </div>
</form>

<?php if ($distributor !== null): ?>
    <form method="post" action="/artikel/<?= e($article->id) ?>/stromverteiler/loeschen" class="mt-6 max-w-4xl" data-confirm="Die Stromverteiler-Angaben dieses Artikels entfernen? Der Artikel selbst bleibt erhalten." data-confirm-style="danger">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <button type="submit" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">Kein Stromverteiler mehr (Angaben entfernen)</button>
    </form>
<?php endif; ?>

<template data-template-device><?php $renderDeviceRow('__I__', ['key' => '__KEY__']); ?></template>
<template data-template-outlet><?php $renderOutletRow('__I__', []); ?></template>

<script>
(function () {
    var form = document.querySelector('[data-distributor-form]');
    if (!form) { return; }
    var devices = form.querySelector('[data-devices]');
    var outlets = form.querySelector('[data-outlets]');
    var counter = Date.now();
    var TYPE_LABEL = { rcd: 'FI', mcb: 'LS', rcbo: 'FI/LS' };

    function fromTemplate(selector, replacements) {
        var html = document.querySelector(selector).innerHTML;
        Object.keys(replacements).forEach(function (key) { html = html.split(key).join(replacements[key]); });
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        return wrapper.firstElementChild;
    }

    function deviceInfo() {
        var list = [];
        devices.querySelectorAll('[data-device-row]').forEach(function (row, i) {
            var type = row.querySelector('[data-device-type]').value;
            var label = row.querySelector('[data-device-label]').value.trim() || ('Schutzorgan ' + (i + 1));
            list.push({ key: row.querySelector('[data-device-key]').value, type: type, label: label, row: row });
        });
        return list;
    }

    function fillSelect(select, items, emptyText) {
        var current = select.value || select.getAttribute('data-selected') || '';
        select.innerHTML = '';
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = emptyText;
        select.appendChild(empty);
        items.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.text;
            if (item.value === current) { option.selected = true; }
            select.appendChild(option);
        });
        select.removeAttribute('data-selected');
    }

    function refresh() {
        var list = deviceInfo();
        var byKey = {};
        list.forEach(function (d) { byKey[d.key] = d; });

        list.forEach(function (d) {
            var row = d.row;
            row.querySelectorAll('[data-rcd-field]').forEach(function (el) { el.style.display = d.type === 'mcb' ? 'none' : ''; });
            row.querySelectorAll('[data-mcb-field]').forEach(function (el) { el.style.display = d.type === 'rcd' ? 'none' : ''; });
            row.querySelector('[data-upstream-field]').style.display = d.type === 'mcb' ? '' : 'none';
            row.querySelector('[data-rated-label]').textContent = d.type === 'rcd' ? 'Nennstrom (optional)' : 'Sicherungsgröße';
            var upstream = row.querySelector('[data-upstream-select]');
            fillSelect(upstream, list.filter(function (o) { return o.type === 'rcd' && o.key !== d.key; }).map(function (o) {
                return { value: o.key, text: 'hinter FI ' + o.label };
            }), 'kein FI (LS direkt an der Zuleitung)');
            if (d.type !== 'mcb') { upstream.value = ''; }
        });

        outlets.querySelectorAll('[data-outlet-device]').forEach(function (select) {
            fillSelect(select, list.map(function (d) {
                var text;
                if (d.type === 'rcd') {
                    text = 'nur hinter FI ' + d.label;
                } else if (d.type === 'rcbo') {
                    text = 'hinter FI/LS ' + d.label;
                } else {
                    var up = d.row.querySelector('[data-upstream-select]').value;
                    text = up && byKey[up] ? 'hinter FI ' + byKey[up].label + ' und LS ' + d.label : 'nur hinter LS ' + d.label;
                }
                return { value: d.key, text: text };
            }), 'direkt von der Zuleitung (ohne Schutzorgan)');
        });
    }

    form.querySelector('[data-add-device]').addEventListener('click', function () {
        var key = 'n' + (counter++);
        devices.appendChild(fromTemplate('[data-template-device]', { '__I__': String(counter++), '__KEY__': key }));
        refresh();
    });
    form.querySelector('[data-add-outlet]').addEventListener('click', function () {
        outlets.appendChild(fromTemplate('[data-template-outlet]', { '__I__': String(counter++) }));
        refresh();
    });
    form.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-row]');
        if (!button) { return; }
        button.closest('[data-device-row], [data-outlet-row]').remove();
        refresh();
    });
    devices.addEventListener('change', refresh);
    devices.addEventListener('input', function (event) { if (event.target.matches('[data-device-label]')) { refresh(); } });

    // Neue Zeilen ohne Schlüssel (nach Validierungsfehler) bekommen einen.
    devices.querySelectorAll('[data-device-key]').forEach(function (input) { if (!input.value) { input.value = 'n' + (counter++); } });
    if (outlets.children.length === 0 && devices.children.length === 0) {
        form.querySelector('[data-add-outlet]').click();
    }
    refresh();
})();
</script>
