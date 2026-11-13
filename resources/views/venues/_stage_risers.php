<?php
/**
 * Riser einer Bühne: maßstäblicher Bühnenplan (Draufsicht, Publikum unten),
 * in dem sich die Riser frei verschieben lassen, darunter die Liste zum
 * Bearbeiten/Löschen und das Formular für einen neuen Riser. Das Verschieben
 * übernimmt public/assets/js/stage-riser-editor.js; ohne JavaScript bleiben
 * die Positionsfelder im Bearbeiten-Formular.
 *
 * @var \App\Domain\Venue\Venue $venue
 * @var \App\Domain\Venue\Stage $stage
 * @var \App\Domain\Venue\Riser[] $risers Riser dieser Bühne
 * @var \Closure(float): string $formatMeters
 */

use App\Domain\Venue\RiserShape;
use App\Infrastructure\Http\Csrf;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$riserBaseUrl = '/veranstaltungsorte/' . $venue->id . '/buehnen/' . $stage->id . '/riser';
$hasDimensions = $stage->widthM !== null && $stage->depthM !== null;
$svgNumber = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
$riserSummary = static function (\App\Domain\Venue\Riser $riser) use ($formatMeters): string {
    $parts = [t($riser->shape->labelKey())];
    $parts[] = $riser->shape->hasDepth()
        ? t('venues.risers.summary_size', ['width' => $formatMeters($riser->widthM), 'depth' => $formatMeters($riser->footprintDepthM())])
        : t('venues.risers.summary_diameter', ['width' => $formatMeters($riser->widthM)]);
    $parts[] = t('venues.risers.summary_height', ['height' => $formatMeters($riser->heightM)]);

    return implode(' · ', $parts);
};
?>
<div class="mt-3">
    <p class="text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.risers.title')) ?></p>

    <?php if (!$hasDimensions): ?>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.risers.needs_dimensions')) ?></p>
    <?php else: ?>
        <?php
            $stageWidth = (float) $stage->widthM;
            $stageDepth = (float) $stage->depthM;
            // Rand um die Bühne (für Kantenbeschriftung und überstehende Riser) und
            // Schriftgröße in Metern, beides relativ zur Bühnengröße.
            $unit = max($stageWidth, $stageDepth * 1.4) / 40;
            $pad = $unit * 2.4;
            $fontSize = $unit * 1.2;
            $viewBox = implode(' ', array_map($svgNumber, [-$pad, -$pad, $stageWidth + 2 * $pad, $stageDepth + 2 * $pad]));
            $dimensions = $formatMeters($stageWidth) . ' × ' . $formatMeters($stageDepth) . ' m';
        ?>
        <svg viewBox="<?= e($viewBox) ?>" class="mt-1 block h-auto w-full max-w-2xl select-none rounded-md border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950"
             role="group" aria-label="<?= e(t('venues.risers.plan_label', ['name' => $stage->name, 'dimensions' => $dimensions, 'count' => (string) count($risers)])) ?>"
             data-riser-plan data-stage-width="<?= e($svgNumber($stageWidth)) ?>" data-stage-depth="<?= e($svgNumber($stageDepth)) ?>"
             data-move-url="<?= e($riserBaseUrl) ?>" data-msg-moved="<?= e(t('venues.risers.moved')) ?>" data-msg-failed="<?= e(t('venues.risers.move_failed')) ?>">
            <defs>
                <pattern id="stage-grid-<?= e($stage->id) ?>" width="1" height="1" patternUnits="userSpaceOnUse" class="text-slate-200 dark:text-slate-800">
                    <path d="M 1 0 L 0 0 0 1" fill="none" stroke="currentColor" stroke-width="1" vector-effect="non-scaling-stroke"/>
                </pattern>
            </defs>
            <rect x="0" y="0" width="<?= e($svgNumber($stageWidth)) ?>" height="<?= e($svgNumber($stageDepth)) ?>" class="fill-white dark:fill-slate-900"/>
            <rect x="0" y="0" width="<?= e($svgNumber($stageWidth)) ?>" height="<?= e($svgNumber($stageDepth)) ?>" fill="url(#stage-grid-<?= e($stage->id) ?>)"/>
            <rect x="0" y="0" width="<?= e($svgNumber($stageWidth)) ?>" height="<?= e($svgNumber($stageDepth)) ?>" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" class="text-slate-500 dark:text-slate-400"/>
            <g class="fill-slate-400 dark:fill-slate-500" font-size="<?= e($svgNumber($fontSize)) ?>" text-anchor="middle">
                <text x="<?= e($svgNumber($stageWidth / 2)) ?>" y="<?= e($svgNumber(-$pad * 0.35)) ?>"><?= e(t('venues.risers.plan_back')) ?> · <?= e($dimensions) ?></text>
                <text x="<?= e($svgNumber($stageWidth / 2)) ?>" y="<?= e($svgNumber($stageDepth + $pad * 0.75)) ?>"><?= e(t('venues.risers.plan_front')) ?></text>
            </g>
            <?php foreach ($risers as $riser): ?>
                <?php
                    $halfWidth = $riser->widthM / 2;
                    $halfDepth = $riser->footprintDepthM() / 2;
                    $label = $riser->name ?? t('venues.risers.unnamed');
                ?>
                <g data-riser="<?= e($riser->id) ?>" data-x="<?= e($svgNumber($riser->centerXM)) ?>" data-y="<?= e($svgNumber($riser->centerYM)) ?>"
                   transform="translate(<?= e($svgNumber($riser->centerXM)) ?> <?= e($svgNumber($riser->centerYM)) ?>)"
                   tabindex="0" role="img" aria-label="<?= e($label . ', ' . $riserSummary($riser)) ?>"
                   class="cursor-move text-brand-600 outline-none focus-visible:text-amber-600 dark:text-brand-400 dark:focus-visible:text-amber-400" style="touch-action: none">
                    <g transform="rotate(<?= e((string) $riser->rotationDeg) ?>)" fill="currentColor" fill-opacity="0.2" stroke="currentColor" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke">
                        <?php if ($riser->shape === RiserShape::Rectangle): ?>
                            <rect x="<?= e($svgNumber(-$halfWidth)) ?>" y="<?= e($svgNumber(-$halfDepth)) ?>" width="<?= e($svgNumber($riser->widthM)) ?>" height="<?= e($svgNumber($halfDepth * 2)) ?>" vector-effect="non-scaling-stroke"/>
                        <?php elseif ($riser->shape === RiserShape::Circle): ?>
                            <circle r="<?= e($svgNumber($halfWidth)) ?>" vector-effect="non-scaling-stroke"/>
                        <?php elseif ($riser->shape === RiserShape::Semicircle): ?>
                            <?php /* Gerade Kante zum Publikum, Bogen nach hinten. */ ?>
                            <path d="M <?= e($svgNumber(-$halfWidth)) ?> <?= e($svgNumber($halfDepth)) ?> A <?= e($svgNumber($halfWidth)) ?> <?= e($svgNumber($halfWidth)) ?> 0 0 1 <?= e($svgNumber($halfWidth)) ?> <?= e($svgNumber($halfDepth)) ?> Z" vector-effect="non-scaling-stroke"/>
                        <?php else: ?>
                            <?php /* Grundseite zum Publikum, Spitze nach hinten. */ ?>
                            <polygon points="<?= e($svgNumber(-$halfWidth)) ?>,<?= e($svgNumber($halfDepth)) ?> <?= e($svgNumber($halfWidth)) ?>,<?= e($svgNumber($halfDepth)) ?> 0,<?= e($svgNumber(-$halfDepth)) ?>" vector-effect="non-scaling-stroke"/>
                        <?php endif; ?>
                    </g>
                    <text text-anchor="middle" font-size="<?= e($svgNumber($fontSize)) ?>" class="pointer-events-none fill-slate-800 dark:fill-slate-100">
                        <tspan x="0" dy="-0.15em" font-weight="600"><?= e($label) ?></tspan>
                        <tspan x="0" dy="1.15em"><?= e($formatMeters($riser->heightM)) ?> m</tspan>
                    </text>
                </g>
            <?php endforeach; ?>
        </svg>
        <?php if ($risers !== []): ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.risers.plan_hint')) ?> <span class="font-medium text-slate-600 dark:text-slate-300" data-riser-status role="status"></span></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($risers === [] && $hasDimensions): ?>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= e(t('venues.risers.empty')) ?></p>
    <?php endif; ?>

    <?php if ($risers !== []): ?>
        <ul class="mt-2 space-y-1">
            <?php foreach ($risers as $riser): ?>
                <li class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1 text-xs">
                    <div class="min-w-0 text-slate-500 dark:text-slate-400">
                        <span class="font-medium text-slate-700 dark:text-slate-300"><?= e($riser->name ?? t('venues.risers.unnamed')) ?></span>
                        · <?= e($riserSummary($riser)) ?>
                        · <span data-riser-position="<?= e($riser->id) ?>" data-template="<?= e(t('venues.risers.summary_position', ['x' => '{x}', 'y' => '{y}'])) ?>"><?= e(t('venues.risers.summary_position', ['x' => $formatMeters($riser->centerXM), 'y' => $formatMeters($riser->centerYM)])) ?></span>
                        <?php if ($riser->rotationDeg !== 0): ?>
                            · <?= e(t('venues.risers.summary_rotation', ['degrees' => (string) $riser->rotationDeg])) ?>
                        <?php endif; ?>
                    </div>
                    <div class="flex shrink-0 items-start gap-3">
                        <?php if ($hasDimensions): ?>
                            <details class="inline-block">
                                <summary class="cursor-pointer font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('venues.risers.edit')) ?></summary>
                                <form method="post" action="<?= e($riserBaseUrl . '/' . $riser->id) ?>" data-riser-edit="<?= e($riser->id) ?>" class="mt-2 max-w-md space-y-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
                                    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                                    <?php require __DIR__ . '/_riser_fields.php'; ?>
                                    <button type="submit" class="rounded-md bg-brand-600 px-3 py-1 text-xs font-medium text-white hover:bg-brand-700"><?= e(t('venues.risers.save')) ?></button>
                                </form>
                            </details>
                        <?php endif; ?>
                        <form method="post" action="<?= e($riserBaseUrl . '/' . $riser->id . '/loeschen') ?>" data-confirm="<?= e(t('venues.risers.confirm_delete')) ?>" data-confirm-style="danger">
                            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                            <button type="submit" class="font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('venues.risers.delete')) ?></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($hasDimensions): ?>
        <details class="mt-2">
            <summary class="cursor-pointer text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('venues.risers.add')) ?></summary>
            <form method="post" action="<?= e($riserBaseUrl) ?>" class="mt-2 max-w-md space-y-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                <?php $riser = null; require __DIR__ . '/_riser_fields.php'; ?>
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1 text-xs font-medium text-white hover:bg-brand-700"><?= e(t('venues.risers.add')) ?></button>
            </form>
        </details>
    <?php endif; ?>
</div>
