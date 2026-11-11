<?php
/** @var \App\Application\Rack\Query\RackDetail|null $detail */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers */

use App\Domain\Rack\RackRailType;
use App\Domain\Rack\RackWidth;
use function App\Infrastructure\Http\e;

$rack = $detail?->rack;
$hasOld = $old !== [];
$val = fn (string $key, ?string $fallback) => $hasOld ? (string) ($old[$key] ?? '') : ($fallback ?? '');
$checked = fn (string $key, bool $fallback) => $hasOld ? isset($old[$key]) : $fallback;
$err = fn (string $key) => isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
$input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900';
$depthDisplay = $rack?->installDepthCm !== null ? rtrim(rtrim(number_format((float) $rack->installDepthCm, 1, ',', ''), '0'), ',') : null;
?>
<div class="mb-6">
    <a href="<?= $rack !== null ? '/racks/' . e($rack->id) : '/racks' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $rack !== null ? 'Rack-Typ bearbeiten' : 'Neuer Rack-Typ' ?></h1>
</div>

<form method="post" action="<?= $rack !== null ? '/racks/' . e($rack->id) : '/racks' ?>" class="space-y-6 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Bezeichnung *</label>
            <input id="name" name="name" value="<?= e($val('name', $rack?->name)) ?>" required class="<?= $input ?>" placeholder="z. B. 10 HE Shockmount-Rack">
            <?= $err('name') ?>
        </div>
        <div>
            <label for="prefix" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Rack-Kürzel *</label>
            <input id="prefix" name="prefix" value="<?= e($val('prefix', $rack?->prefix)) ?>" required maxlength="10" class="<?= $input ?> font-mono uppercase" placeholder="z. B. RK10">
            <?php if (isset($errors['prefix'])): ?><?= $err('prefix') ?><?php else: ?><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Mit dem Hersteller-Kürzel Grundlage der Inventurnummern (HERSTELLER-KÜRZEL-0001). Jedes Exemplar bekommt zusätzlich eine Rack-Nummer (RACK-0001 …).</p><?php endif; ?>
        </div>
        <div>
            <label for="manufacturer_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Hersteller *</label>
            <select id="manufacturer_id" name="manufacturer_id" required class="<?= $input ?>">
                <option value="">Bitte wählen…</option>
                <?php $selectedManufacturer = $val('manufacturer_id', $rack?->manufacturerId); ?>
                <?php foreach ($manufacturers as $manufacturer): ?>
                    <option value="<?= e($manufacturer->id) ?>" <?= $selectedManufacturer === $manufacturer->id ? 'selected' : '' ?>><?= e($manufacturer->name) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('manufacturer_id') ?>
        </div>
        <div>
            <label for="color" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Farbe</label>
            <input id="color" name="color" value="<?= e($val('color', $rack?->color)) ?>" class="<?= $input ?>" placeholder="z. B. schwarz">
            <?= $err('color') ?>
        </div>
        <div>
            <label for="height_units" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Höheneinheiten (HE) *</label>
            <input id="height_units" name="height_units" type="number" min="1" max="60" value="<?= e($val('height_units', $rack !== null ? (string) $rack->heightUnits : null)) ?>" required class="<?= $input ?>">
            <?= $err('height_units') ?>
        </div>
        <div>
            <label for="install_depth_cm" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Einbautiefe (cm)</label>
            <input id="install_depth_cm" name="install_depth_cm" inputmode="decimal" value="<?= e($val('install_depth_cm', $depthDisplay)) ?>" class="<?= $input ?>" placeholder="z. B. 45">
            <?= $err('install_depth_cm') ?>
        </div>
        <div>
            <label for="width" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Breite *</label>
            <select id="width" name="width" class="<?= $input ?>">
                <?php $selectedWidth = $val('width', ($rack?->width ?? RackWidth::Full)->value); ?>
                <?php foreach (RackWidth::cases() as $width): ?>
                    <option value="<?= e($width->value) ?>" <?= $selectedWidth === $width->value ? 'selected' : '' ?>><?= e($width->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('width') ?>
        </div>
        <div>
            <label for="rail_type" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Rackschienen *</label>
            <select id="rail_type" name="rail_type" class="<?= $input ?>">
                <?php $selectedRail = $val('rail_type', ($rack?->railType ?? RackRailType::Front)->value); ?>
                <?php foreach (RackRailType::cases() as $railType): ?>
                    <option value="<?= e($railType->value) ?>" <?= $selectedRail === $railType->value ? 'selected' : '' ?>><?= e($railType->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('rail_type') ?>
        </div>
    </div>

    <div class="flex flex-wrap gap-6">
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="has_shock_mount" value="1" <?= $checked('has_shock_mount', $rack?->hasShockMount ?? false) ? 'checked' : '' ?>> Shockmount
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="has_wheels" value="1" <?= $checked('has_wheels', $rack?->hasWheels ?? false) ? 'checked' : '' ?>> Rollen
        </label>
    </div>


    <div>
        <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Bemerkung</label>
        <textarea id="description" name="description" rows="3" class="<?= $input ?>"><?= e($val('description', $rack?->description)) ?></textarea>
    </div>

    <div class="form-actions flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
        <a href="<?= $rack !== null ? '/racks/' . e($rack->id) : '/racks' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>
