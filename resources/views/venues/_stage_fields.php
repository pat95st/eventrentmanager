<?php
/**
 * Eingabefelder einer Bühne — gemeinsam genutzt vom "Bühne hinzufügen"- und
 * vom Bearbeiten-Formular in venues/form.php. Welche Felder sichtbar sind,
 * hängt von Bauart und (bei mobilen Bühnen) von der Art der Angabe ab; das
 * Umschalten übernimmt das Skript am Ende von venues/form.php
 * (data-stage-*-Attribute). Der Server wertet nur die zur Auswahl passenden
 * Felder aus, siehe ValidatesVenueStageInput.
 *
 * @var \App\Domain\Venue\Stage|null $stage null = neue Bühne
 * @var \App\Domain\Venue\PowerConnection[] $powerConnections
 * @var \Closure(float): string $formatMeters
 */

use App\Domain\Venue\Stage;
use App\Domain\Venue\StageBuildMode;
use App\Domain\Venue\StageElementSize;
use App\Domain\Venue\StageType;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$fieldClass = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700';
$labelClass = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$radioRowClass = 'mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-700 dark:text-slate-300';
$selectedType = $stage?->type ?? StageType::Fixed;
$isMobile = $selectedType === StageType::Mobile;
$selectedMode = $stage !== null && $isMobile && $stage->isDefinedByElementStock() ? StageBuildMode::Elements : StageBuildMode::Dimensions;
$showElements = $isMobile && $selectedMode === StageBuildMode::Elements;
?>
<div class="grid grid-cols-2 gap-2 sm:grid-cols-6" data-stage-form>
    <label class="col-span-2 sm:col-span-6">
        <span class="<?= $labelClass ?>"><?= e(t('venues.stages.name')) ?></span>
        <input type="text" name="name" required maxlength="150" value="<?= e($stage->name ?? '') ?>"
               placeholder="<?= e(t('venues.stages.name_placeholder')) ?>" class="<?= $fieldClass ?>">
    </label>
    <fieldset class="col-span-2 sm:col-span-6">
        <legend class="<?= $labelClass ?>"><?= e(t('venues.stages.type')) ?></legend>
        <div class="<?= $radioRowClass ?>">
            <?php foreach (StageType::cases() as $typeOption): ?>
                <label class="inline-flex items-center gap-1.5">
                    <input type="radio" name="type" value="<?= e($typeOption->value) ?>" required data-stage-type <?= $typeOption === $selectedType ? 'checked' : '' ?>>
                    <?= e(t($typeOption->labelKey())) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <fieldset class="col-span-2 sm:col-span-6 <?= $isMobile ? '' : 'hidden' ?>" data-stage-mobile-only>
        <legend class="<?= $labelClass ?>"><?= e(t('venues.stages.build_mode')) ?></legend>
        <div class="<?= $radioRowClass ?>">
            <?php foreach (StageBuildMode::cases() as $modeOption): ?>
                <label class="inline-flex items-center gap-1.5">
                    <input type="radio" name="build_mode" value="<?= e($modeOption->value) ?>" data-stage-build-mode <?= $modeOption === $selectedMode ? 'checked' : '' ?>>
                    <?= e(t('venues.stages.build_mode.' . $modeOption->value)) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.stages.build_mode_hint')) ?></p>
    </fieldset>
    <label class="sm:col-span-2 <?= $showElements ? 'hidden' : '' ?>" data-stage-dimensions>
        <span class="<?= $labelClass ?>"><?= e(t('venues.stages.width')) ?></span>
        <input type="text" inputmode="decimal" name="width" <?= $showElements ? '' : 'required' ?>
               value="<?= e($stage?->widthM !== null ? $formatMeters($stage->widthM) : '') ?>" class="<?= $fieldClass ?>">
    </label>
    <label class="sm:col-span-2 <?= $showElements ? 'hidden' : '' ?>" data-stage-dimensions>
        <span class="<?= $labelClass ?>"><?= e(t('venues.stages.depth')) ?></span>
        <input type="text" inputmode="decimal" name="depth" <?= $showElements ? '' : 'required' ?>
               value="<?= e($stage?->depthM !== null ? $formatMeters($stage->depthM) : '') ?>" class="<?= $fieldClass ?>">
    </label>
    <fieldset class="col-span-2 sm:col-span-4 <?= $showElements ? '' : 'hidden' ?>" data-stage-elements>
        <legend class="<?= $labelClass ?>"><?= e(t('venues.stages.elements_title')) ?></legend>
        <div class="mt-1 grid grid-cols-2 gap-x-3 gap-y-1">
            <?php foreach (StageElementSize::all() as $elementSize): ?>
                <label class="flex items-center gap-2">
                    <input type="number" min="0" max="<?= Stage::MAX_ELEMENTS_PER_SIZE ?>" step="1" inputmode="numeric" name="elements[<?= e($elementSize->value) ?>]"
                           value="<?= e((string) ($stage?->elementStock[$elementSize->value] ?? '')) ?>" placeholder="0"
                           class="w-16 shrink-0 rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                    <span class="whitespace-nowrap text-xs text-slate-600 dark:text-slate-400"><?= e($elementSize->label()) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <label class="col-span-2 sm:col-span-2">
        <span class="<?= $labelClass ?>"><?= e(t('venues.stages.height')) ?></span>
        <input type="text" inputmode="decimal" name="height" required value="<?= e($stage !== null ? $formatMeters($stage->heightM) : '') ?>"
               placeholder="<?= e(t('venues.stages.height_hint')) ?>" class="<?= $fieldClass ?>">
    </label>
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
