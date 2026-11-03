<?php
/**
 * Felder eines Stromverteilers (Migration 117) im Artikelformular:
 * Zuleitung, Schutzorgane (FI, LS, FI/LS) und Abgänge. Zeilen werden per
 * /assets/js/power-distributor-fields.js ergänzt; Feldnamen pd_inlet_type,
 * pd_notes, pd_devices[i][…], pd_outlets[i][…].
 *
 * @var \App\Domain\PowerDistributor\PowerDistributor|null $distributor gespeicherte Angaben
 * @var array{inlet_type?: string, notes?: string, devices?: array, outlets?: array}|null $old nach Validierungsfehler
 * @var array<string, string> $errors
 */

use App\Domain\PowerDistributor\ConnectorType;
use App\Domain\PowerDistributor\ProtectiveDevice;
use App\Domain\PowerDistributor\ProtectiveDeviceType;
use function App\Infrastructure\Http\e;

$input = 'w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900';
$label = 'block text-[11px] text-slate-500 dark:text-slate-400';

if ($old !== null) {
    $inletType = (string) ($old['inlet_type'] ?? '');
    $notes = (string) ($old['notes'] ?? '');
    $deviceRows = (array) ($old['devices'] ?? []);
    $outletRows = (array) ($old['outlets'] ?? []);
} else {
    $inletType = $distributor?->inletType->value ?? '';
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
$distributorErrors = array_values(array_filter([$errors['powerDistributorInlet'] ?? null, $errors['powerDistributorDevices'] ?? null, $errors['powerDistributorOutlets'] ?? null]));

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
    $name = static fn (string $field) => 'pd_devices[' . $index . '][' . $field . ']';
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
    $name = static fn (string $field) => 'pd_outlets[' . $index . '][' . $field . ']';
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
<div data-distributor-fields class="space-y-4">
    <?php if ($distributorErrors !== []): ?>
        <div class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800">
            <?php foreach ($distributorErrors as $message): ?><p><?= e($message) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label for="pd_inlet_type" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Art der Zuleitung *</label>
            <select id="pd_inlet_type" name="pd_inlet_type" class="mt-1 <?= $input ?>"><?= $options($connectorOptions, $inletType, 'Bitte wählen …') ?></select>
        </div>
        <div>
            <label for="pd_notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Bemerkung zum Verteiler</label>
            <input id="pd_notes" name="pd_notes" value="<?= e($notes) ?>" class="mt-1 <?= $input ?>" placeholder="optional">
        </div>
    </div>

    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Schutzorgane</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">RCD (FI), LS-Schalter und FI/LS-Kombinationen. Ein LS kann hinter einem FI liegen. Ohne Bezeichnung wird F1, F2, … vergeben.</p>
            </div>
            <button type="button" data-add-device class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">+ Schutzorgan</button>
        </div>
        <div data-devices class="space-y-3">
            <?php foreach (array_values($deviceRows) as $i => $row): $renderDeviceRow((string) $i, $row); endforeach; ?>
        </div>
    </div>

    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Abgänge</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Direkt von der Zuleitung, nur hinter FI, nur hinter LS, hinter FI und LS (LS mit vorgeschaltetem FI wählen) oder hinter FI/LS.</p>
            </div>
            <button type="button" data-add-outlet class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">+ Abgang</button>
        </div>
        <div data-outlets class="space-y-3">
            <?php foreach (array_values($outletRows) as $i => $row): $renderOutletRow((string) $i, $row); endforeach; ?>
        </div>
    </div>

    <template data-template-device><?php $renderDeviceRow('__I__', ['key' => '__KEY__']); ?></template>
    <template data-template-outlet><?php $renderOutletRow('__I__', []); ?></template>
</div>
