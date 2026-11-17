<?php
/**
 * Stromplanung einer Veranstaltung (Migration 159): wo welches Gerät bzw.
 * welcher Stromverteiler steckt, erwartete Last je Phase, Überlastungen und
 * Umsteck-Vorschläge.
 *
 * @var \App\Application\PowerPlan\Query\RentalPowerPlanView $powerPlan
 */

use App\Domain\PowerDistributor\ProtectiveDevice;
use App\Domain\PowerPlan\LoadCheck;
use App\Domain\PowerPlan\PowerLoadCalculator;
use function App\Infrastructure\Http\e;

$setup = $powerPlan->setup;
$rental = $setup->rental;
$plan = $setup->plan;
$result = $powerPlan->result;
$targets = $powerPlan->targets;
$csrf = '<input type="hidden" name="csrf_token" value="' . e(\App\Infrastructure\Http\Csrf::token()) . '">';

$fmtW = static fn (float $w): string => number_format($w, 0, ',', '.') . ' W';
$fmtA = static fn (float $a): string => number_format($a, 1, ',', '.') . ' A';
$fmtFactor = static fn (float $f): string => rtrim(rtrim(number_format($f, 2, ',', ''), '0'), ',');
$barColor = static function (?float $utilization): string {
    if ($utilization === null) {
        return 'bg-slate-400';
    }

    return $utilization > 100 ? 'bg-red-500' : ($utilization > 80 ? 'bg-amber-500' : 'bg-emerald-500');
};
$overloads = $result->overloads();
$card = 'rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900';
$groupedTargets = $targets->grouped();
$input = 'w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900';

/** @param LoadCheck[] $checks */
$renderChecks = static function (array $checks) use ($fmtA, $barColor): void {
    if ($checks === []) {
        echo '<p class="text-xs text-slate-400 dark:text-slate-500">Noch nichts eingesteckt.</p>';

        return;
    }
    ?>
    <table class="w-full text-xs">
        <thead class="text-left text-slate-500 dark:text-slate-400">
            <tr><th class="py-1 pr-2 font-medium">Element</th><th class="py-1 pr-2 font-medium">Phase</th><th class="py-1 pr-2 text-right font-medium">Strom</th><th class="py-1 pr-2 text-right font-medium">Grenze</th><th class="w-32 py-1 font-medium">Auslastung</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($checks as $check): ?>
            <?php $u = $check->utilization(); ?>
            <tr class="<?= $check->isOverloaded() ? 'text-red-700 dark:text-red-400 font-medium' : 'text-slate-700 dark:text-slate-300' ?>">
                <td class="py-1 pr-2"><?= e($check->label) ?></td>
                <td class="py-1 pr-2"><?= e($check->phase ?? '1~') ?></td>
                <td class="whitespace-nowrap py-1 pr-2 text-right tabular-nums"><?= e($fmtA($check->currentA)) ?></td>
                <td class="py-1 pr-2 text-right tabular-nums"><?= $check->limitA !== null ? e($check->limitA . ' A') : '–' ?></td>
                <td class="py-1">
                    <?php if ($u !== null): ?>
                        <div class="flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded bg-slate-100 dark:bg-slate-800"><div class="h-2 <?= $barColor($u) ?>" style="width: <?= e((string) min(100, round($u))) ?>%"></div></div>
                            <span class="w-10 text-right tabular-nums"><?= e((string) round($u)) ?> %</span>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
};

/** @param float[] $vector */
$renderPhases = static function (array $vector) use ($fmtW, $fmtA): void {
    ?>
    <div class="grid grid-cols-3 gap-2 text-center text-xs">
        <?php foreach (['L1', 'L2', 'L3'] as $i => $phase): ?>
            <div class="rounded-md bg-slate-50 px-2 py-1.5 dark:bg-slate-800/60">
                <p class="font-semibold text-slate-700 dark:text-slate-200"><?= e($phase) ?></p>
                <p class="tabular-nums text-slate-600 dark:text-slate-300"><?= e($fmtA($vector[$i] / PowerLoadCalculator::VOLTAGE)) ?></p>
                <p class="tabular-nums text-slate-400 dark:text-slate-500"><?= e($fmtW($vector[$i])) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
};
?>

<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück zur Veranstaltung</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Stromplanung: <?= e($rental->eventName) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Lege fest, wo welches Gerät und welcher Stromverteiler steckt. Berechnet wird mit 230 V je Phase (cos φ = 1) und der Leistung der Geräte × Gleichzeitigkeitsfaktor – der durchschnittlichen Leistung (Ø), wenn sie am Artikel angegeben ist, sonst der Spitzenleistung.
    </p>
</div>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="<?= $card ?>">
        <p class="text-xs text-slate-500 dark:text-slate-400">Erwartete Gesamtleistung</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100"><?= e($fmtW($result->totalEffectivePowerW)) ?></p>
        <p class="text-xs text-slate-400 dark:text-slate-500">≈ <?= e($fmtA($result->totalEffectivePowerW / PowerLoadCalculator::VOLTAGE / 3)) ?> je Phase bei gleichmäßiger Verteilung</p>
    </div>
    <div class="<?= $card ?>">
        <p class="text-xs text-slate-500 dark:text-slate-400">Gleichzeitigkeitsfaktor</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100"><?= e($fmtFactor($plan->simultaneityFactor)) ?></p>
        <p class="text-xs text-slate-400 dark:text-slate-500">für alle Geräte ohne eigenen Faktor</p>
    </div>
    <div class="<?= $card ?> <?= $overloads !== [] ? 'border-red-300 dark:border-red-700' : '' ?>">
        <p class="text-xs text-slate-500 dark:text-slate-400">Überlastungen</p>
        <p class="mt-1 text-lg font-semibold <?= $overloads !== [] ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' ?>"><?= $overloads !== [] ? e((string) count($overloads)) : 'keine' ?></p>
        <p class="text-xs text-slate-400 dark:text-slate-500">Zuleitungen, Sicherungsautomaten, Anschlüsse und Abgänge</p>
    </div>
</div>

<?php if ($overloads !== []): ?>
    <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-700 dark:bg-red-950/40 dark:text-red-200" role="alert">
        <p class="font-semibold">Überlastet</p>
        <ul class="mt-1 list-disc pl-5 text-xs">
            <?php foreach ($overloads as $check): ?>
                <li><?= e($check->label) ?><?= $check->phase !== null ? ' · ' . e($check->phase) : '' ?>: <?= e($fmtA($check->currentA)) ?> bei <?= e((string) $check->limitA) ?> A (<?= e((string) round($check->utilization() ?? 0)) ?> %)</li>
            <?php endforeach; ?>
        </ul>

        <div class="mt-3 border-t border-red-200 pt-3 dark:border-red-800">
            <p class="font-semibold">Vorschlag zum Umstecken</p>
            <?php if ($powerPlan->suggestions === []): ?>
                <p class="mt-1 text-xs">Durch Umstecken auf freie, passende Steckdosen lässt sich die Überlastung nicht beheben. Bitte einen weiteren Stromverteiler bzw. Anschluss einplanen oder den Gleichzeitigkeitsfaktor prüfen.</p>
            <?php else: ?>
                <ol class="mt-1 space-y-1 text-xs">
                    <?php foreach ($powerPlan->suggestions as $i => $move): ?>
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-white/70 px-2 py-1.5 dark:bg-slate-900/60">
                            <span><span class="font-medium"><?= e(($i + 1) . '. ' . $move->label) ?>:</span> <?= e($targets->describe($move->from)) ?> &rarr; <span class="font-medium"><?= e($targets->describe($move->to)) ?></span></span>
                            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/strom/vorschlaege">
                                <?= $csrf ?>
                                <input type="hidden" name="key" value="<?= e($move->key) ?>">
                                <button class="rounded border border-red-300 px-2 py-0.5 font-medium hover:bg-red-100 dark:border-red-700 dark:hover:bg-red-900/40">Übernehmen</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <span>
                        <?php if ($powerPlan->resultAfterSuggestions->hasOverloads()): ?>
                            Danach bleiben <?= e((string) count($powerPlan->resultAfterSuggestions->overloads())) ?> Überlastung(en) – zusätzlich einen Verteiler bzw. Anschluss einplanen.
                        <?php else: ?>
                            Danach ist nichts mehr überlastet (höchste Auslastung <?= e((string) round($powerPlan->resultAfterSuggestions->maxUtilization())) ?> %).
                        <?php endif; ?>
                    </span>
                    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/strom/vorschlaege">
                        <?= $csrf ?>
                        <button class="rounded-md bg-red-600 px-3 py-1 font-medium text-white hover:bg-red-700">Alle Vorschläge übernehmen</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($result->warnings !== []): ?>
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200" role="note">
        <p class="font-semibold">Hinweise</p>
        <ul class="mt-1 list-disc pl-5 text-xs">
            <?php foreach ($result->warnings as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($setup->nodes === []): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
        In dieser Veranstaltung sind weder Stromverteiler noch Geräte mit hinterlegter Leistung eingeplant.
        Die Leistung wird am Artikel erfasst (Artikel bearbeiten › Spitzenleistung / Durchschnittliche Leistung).
    </div>
<?php endif; ?>

<?php if ($result->venueLoads !== [] || array_filter($setup->nodes, static fn ($n) => $n->distributor !== null) !== []): ?>
    <h2 class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-200">Last je Phase</h2>
    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <?php foreach ($setup->venueConnections as $connection): ?>
            <?php if (!isset($result->venueLoads[$connection->id])) { continue; } ?>
            <div class="<?= $card ?>">
                <h3 class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-200">Anschluss <?= e($connection->type->value) ?><?= $connection->location !== null ? ' · ' . e($connection->location) : '' ?></h3>
                <p class="mb-2 text-xs text-slate-500 dark:text-slate-400"><?= e($connection->protectionLabel()) ?> · Veranstaltungsort</p>
                <?php $renderPhases($result->venueLoads[$connection->id]); ?>
                <div class="mt-3"><?php $renderChecks($result->checksFor($connection->id)); ?></div>
            </div>
        <?php endforeach; ?>
        <?php foreach ($setup->nodes as $node): ?>
            <?php if ($node->distributor === null) { continue; } ?>
            <?php $vector = $result->distributorLoads[$node->key()] ?? [0.0, 0.0, 0.0]; ?>
            <div class="<?= $card ?>">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e($node->label) ?></h3>
                <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                    Zuleitung <?= e($node->distributor->inletType->shortLabel()) ?> · steckt in: <?= e($targets->describe($result->validAssignments[$node->key()] ?? null)) ?>
                </p>
                <?php $renderPhases($vector); ?>
                <?php $children = $result->outletChildren[$node->key()] ?? []; ?>
                <?php if ($children !== []): ?>
                    <ul class="mt-3 space-y-0.5 text-xs text-slate-600 dark:text-slate-300">
                        <?php foreach ($node->distributor->outlets as $outlet): ?>
                            <?php if (($children[$outlet->id] ?? []) === []) { continue; } ?>
                            <li><span class="font-medium"><?= e(PowerLoadCalculator::outletName($outlet)) ?>:</span>
                                <?= e(implode(', ', array_map(static fn (string $k) => $setup->node($k)?->label ?? $k, $children[$outlet->id]))) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="mt-3"><?php $renderChecks($result->checksFor($node->key())); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($setup->nodes !== []): ?>
<form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/strom" class="<?= $card ?> mb-6">
    <?= $csrf ?>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Wo steckt was?</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Die Phase ist nur nötig, wenn sie sich nicht aus dem Abgang ergibt (z. B. einphasiges Gerät per Adapter an CEE rot).</p>
        </div>
        <div class="w-56">
            <label for="simultaneity_factor" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Gleichzeitigkeitsfaktor (0,01 – 1)</label>
            <input id="simultaneity_factor" name="simultaneity_factor" inputmode="decimal" value="<?= e($fmtFactor($plan->simultaneityFactor)) ?>" class="mt-1 <?= $input ?>" placeholder="z. B. 0,7 oder 70 %">
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[48rem] text-sm">
            <thead class="text-left text-xs text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="py-1.5 pr-2 font-medium">Gerät / Stromverteiler</th>
                    <th class="py-1.5 pr-2 text-right font-medium">Leistung</th>
                    <th class="py-1.5 pr-2 font-medium">steckt in</th>
                    <th class="w-24 py-1.5 pr-2 font-medium">Phase</th>
                    <th class="w-28 py-1.5 font-medium">eigener Faktor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($setup->nodes as $node): ?>
                <?php
                $key = $node->key();
                $assignment = $plan->assignment($key);
                $selected = \App\Application\PowerPlan\PowerTargetOptions::encode($assignment);
                $name = static fn (string $field): string => 'units[' . $key . '][' . $field . ']';
                $unplugged = in_array($key, $result->unassignedKeys, true);
                ?>
                <tr>
                    <td class="py-1.5 pr-2 text-slate-800 dark:text-slate-200">
                        <?= e($node->label) ?>
                        <?php if ($node->distributor !== null): ?><span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Verteiler</span><?php endif; ?>
                        <?php if ($unplugged): ?><span class="ml-1 text-[11px] text-amber-700 dark:text-amber-400">nicht eingesteckt</span><?php endif; ?>
                    </td>
                    <td class="py-1.5 pr-2 text-right tabular-nums text-slate-600 dark:text-slate-300"><?= $node->inputPowerW !== null ? e(($node->inputPowerIsAverage ? 'Ø ' : '') . $fmtW((float) $node->inputPowerW)) : '–' ?></td>
                    <td class="py-1.5 pr-2">
                        <select name="<?= e($name('target')) ?>" class="<?= $input ?>">
                            <option value="">– nicht eingesteckt –</option>
                            <?php foreach ($groupedTargets as $group => $options): ?>
                                <?php if (str_starts_with(array_key_first($options), 'outlet:' . $node->articleId . ':' . $node->unitNo . ':')) { continue; } ?>
                                <optgroup label="<?= e($group) ?>">
                                    <?php foreach ($options as $value => $optionLabel): ?>
                                        <option value="<?= e($value) ?>" <?= $value === $selected ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="py-1.5 pr-2">
                        <select name="<?= e($name('phase')) ?>" class="<?= $input ?>">
                            <option value="">auto</option>
                            <?php foreach (ProtectiveDevice::PHASES as $phase): ?>
                                <option value="<?= e($phase) ?>" <?= $assignment?->phase === $phase ? 'selected' : '' ?>><?= e($phase) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="py-1.5">
                        <?php if ($node->distributor === null): ?>
                            <input name="<?= e($name('factor')) ?>" inputmode="decimal" value="<?= $assignment?->simultaneityFactor !== null ? e($fmtFactor($assignment->simultaneityFactor)) : '' ?>" placeholder="<?= e($fmtFactor($plan->simultaneityFactor)) ?>" class="<?= $input ?>">
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($setup->unitsWithoutPower > 0): ?>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400"><?= e((string) $setup->unitsWithoutPower) ?> eingeplante(s) Gerät(e) ohne hinterlegte Leistung werden nicht berücksichtigt (Leitungen haben keine Leistung).</p>
    <?php endif; ?>
    <div class="mt-4 flex justify-end">
        <button class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern und neu berechnen</button>
    </div>
</form>
<?php endif; ?>
