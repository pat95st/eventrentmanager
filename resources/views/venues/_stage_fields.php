<?php
/**
 * Eingabefelder einer Bühne — gemeinsam genutzt vom "Bühne hinzufügen"- und
 * vom Bearbeiten-Formular in venues/form.php.
 *
 * @var \App\Domain\Venue\Stage|null $stage null = neue Bühne
 * @var \App\Domain\Venue\PowerConnection[] $powerConnections
 * @var \Closure(float): string $formatMeters
 */

use App\Domain\Venue\StageType;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$fieldClass = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700';
$labelClass = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$selectedType = $stage?->type ?? StageType::Fixed;
?>
<div class="grid grid-cols-2 gap-2 sm:grid-cols-6">
    <label class="col-span-2 sm:col-span-6">
        <span class="<?= $labelClass ?>"><?= e(t('venues.stages.name')) ?></span>
        <input type="text" name="name" required maxlength="150" value="<?= e($stage->name ?? '') ?>"
               placeholder="<?= e(t('venues.stages.name_placeholder')) ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="sm:col-span-2">
        <span class="<?= $labelClass ?>"><?= e(t('venues.stages.width')) ?></span>
        <input type="text" inputmode="decimal" name="width" required value="<?= e($stage !== null ? $formatMeters($stage->widthM) : '') ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="sm:col-span-2">
        <span class="<?= $labelClass ?>"><?= e(t('venues.stages.depth')) ?></span>
        <input type="text" inputmode="decimal" name="depth" required value="<?= e($stage !== null ? $formatMeters($stage->depthM) : '') ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="col-span-2 sm:col-span-2">
        <span class="<?= $labelClass ?>"><?= e(t('venues.stages.height')) ?></span>
        <input type="text" inputmode="decimal" name="height" required value="<?= e($stage !== null ? $formatMeters($stage->heightM) : '') ?>"
               placeholder="<?= e(t('venues.stages.height_hint')) ?>" class="<?= $fieldClass ?>">
    </label>
    <fieldset class="col-span-2 sm:col-span-6">
        <legend class="<?= $labelClass ?>"><?= e(t('venues.stages.type')) ?></legend>
        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-700 dark:text-slate-300">
            <?php foreach (StageType::cases() as $typeOption): ?>
                <label class="inline-flex items-center gap-1.5">
                    <input type="radio" name="type" value="<?= e($typeOption->value) ?>" required <?= $typeOption === $selectedType ? 'checked' : '' ?>>
                    <?= e(t($typeOption->labelKey())) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <fieldset class="col-span-2 sm:col-span-6">
        <legend class="<?= $labelClass ?>"><?= e(t('venues.stages.distances_title')) ?></legend>
        <?php if ($powerConnections === []): ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.stages.distances_no_connections')) ?></p>
        <?php else: ?>
            <div class="mt-1 space-y-1">
                <?php foreach ($powerConnections as $connection): ?>
                    <?php $distance = $stage?->distanceTo($connection->id); ?>
                    <label class="flex items-center gap-2">
                        <input type="text" inputmode="decimal" name="distances[<?= e($connection->id) ?>]"
                               value="<?= e($distance !== null ? $formatMeters($distance) : '') ?>"
                               class="w-20 shrink-0 rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                        <span class="min-w-0 text-xs text-slate-600 dark:text-slate-400">
                            <?= e($connection->type->value) ?><?php if ($connection->location !== null && $connection->location !== ''): ?> · <?= e($connection->location) ?><?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.stages.distances_hint')) ?></p>
        <?php endif; ?>
    </fieldset>
</div>
