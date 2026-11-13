<?php
/**
 * Eingabefelder eines Risers — gemeinsam genutzt vom Hinzufügen- und vom
 * Bearbeiten-Formular in venues/_stage_risers.php. Welche Maße gefragt sind,
 * hängt von der Form ab (App\Domain\Venue\RiserShape); das Umschalten
 * übernimmt public/assets/js/stage-riser-editor.js (data-riser-*-Attribute).
 *
 * @var \App\Domain\Venue\Riser|null $riser null = neuer Riser
 * @var \Closure(float): string $formatMeters
 */

use App\Domain\Venue\RiserShape;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$fieldClass = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700';
$labelClass = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$selectedShape = $riser?->shape ?? RiserShape::Rectangle;
// Formen ohne eigene Tiefe, für das Umschalten im Browser.
$shapesWithoutDepth = implode(' ', array_map(
    static fn (RiserShape $shape): string => $shape->value,
    array_filter(RiserShape::cases(), static fn (RiserShape $shape): bool => !$shape->hasDepth())
));
?>
<div class="grid grid-cols-2 gap-2 sm:grid-cols-6" data-riser-form data-shapes-without-depth="<?= e($shapesWithoutDepth) ?>">
    <label class="col-span-2 sm:col-span-4">
        <span class="<?= $labelClass ?>"><?= e(t('venues.risers.name')) ?></span>
        <input type="text" name="name" maxlength="100" value="<?= e($riser?->name ?? '') ?>"
               placeholder="<?= e(t('venues.risers.name_placeholder')) ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="col-span-2 sm:col-span-2">
        <span class="<?= $labelClass ?>"><?= e(t('venues.risers.shape')) ?></span>
        <select name="shape" data-riser-shape class="<?= $fieldClass ?>">
            <?php foreach (RiserShape::cases() as $shapeOption): ?>
                <option value="<?= e($shapeOption->value) ?>" <?= $shapeOption === $selectedShape ? 'selected' : '' ?>><?= e(t($shapeOption->labelKey())) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="sm:col-span-2">
        <span class="<?= $labelClass ?>" data-riser-width-label data-label-width="<?= e(t('venues.risers.width')) ?>" data-label-diameter="<?= e(t('venues.risers.diameter')) ?>"><?= e(t($selectedShape->widthIsDiameter() ? 'venues.risers.diameter' : 'venues.risers.width')) ?></span>
        <input type="text" inputmode="decimal" name="width" required value="<?= e($riser !== null ? $formatMeters($riser->widthM) : '') ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="sm:col-span-2 <?= $selectedShape->hasDepth() ? '' : 'hidden' ?>" data-riser-depth>
        <span class="<?= $labelClass ?>"><?= e(t('venues.risers.depth')) ?></span>
        <input type="text" inputmode="decimal" name="depth" <?= $selectedShape->hasDepth() ? 'required' : '' ?>
               value="<?= e($riser?->depthM !== null ? $formatMeters($riser->depthM) : '') ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="sm:col-span-2">
        <span class="<?= $labelClass ?>"><?= e(t('venues.risers.height')) ?></span>
        <input type="text" inputmode="decimal" name="height" required value="<?= e($riser !== null ? $formatMeters($riser->heightM) : '') ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="sm:col-span-2">
        <span class="<?= $labelClass ?>"><?= e(t('venues.risers.center_x')) ?></span>
        <input type="text" inputmode="decimal" name="center_x" data-riser-center-x value="<?= e($riser !== null ? $formatMeters($riser->centerXM) : '') ?>"
               placeholder="<?= e(t('venues.risers.position_hint')) ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="sm:col-span-2">
        <span class="<?= $labelClass ?>"><?= e(t('venues.risers.center_y')) ?></span>
        <input type="text" inputmode="decimal" name="center_y" data-riser-center-y value="<?= e($riser !== null ? $formatMeters($riser->centerYM) : '') ?>"
               placeholder="<?= e(t('venues.risers.position_hint')) ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="col-span-2 sm:col-span-2">
        <span class="<?= $labelClass ?>"><?= e(t('venues.risers.rotation')) ?></span>
        <input type="number" min="0" max="359" step="1" inputmode="numeric" name="rotation" value="<?= e((string) ($riser?->rotationDeg ?? 0)) ?>" class="<?= $fieldClass ?>">
    </label>
</div>
