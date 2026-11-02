<?php
/** @var \App\Application\Rack\Query\RackDetail $detail */
/** @var list<array{label: string, heightUnits: int, installDepthCm: string|null, devices: list<array{id: string, number: string, inRack: string|null}>}> $deviceOptions */

use App\Domain\Rack\RackRailType;
use function App\Infrastructure\Http\e;

$rack = $detail->rack;
$cm = static fn (?string $value) => $value !== null ? rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ',') . ' cm' : '–';
$freeUnits = $rack->heightUnits - $detail->usedUnits();
$csrf = e(\App\Infrastructure\Http\Csrf::token());
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/racks" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Alle Racks</a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($rack->name) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            <span class="font-mono"><?= e($rack->inventoryNumber) ?></span>
            <?php if ($detail->manufacturer !== null): ?> · <?= e($detail->manufacturer->name) ?><?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-3">
        <a href="/racks/<?= e($rack->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Bearbeiten</a>
        <form method="post" action="/racks/<?= e($rack->id) ?>/loeschen" data-confirm="Dieses Rack löschen? Die eingebauten Geräte bleiben erhalten." data-confirm-style="danger">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">Löschen</button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <!-- Belegung -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Belegung</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400"><?= $detail->usedUnits() ?> von <?= $rack->heightUnits ?> HE belegt · <?= $freeUnits ?> frei</span>
            </div>
            <div class="overflow-hidden rounded-md border border-slate-300 dark:border-slate-700">
                <?php foreach ($detail->unitMap() as $unit => $slot): ?>
                    <?php if ($slot === null): ?>
                        <div class="flex items-center border-b border-slate-100 text-xs text-slate-400 last:border-b-0 dark:border-slate-800 dark:text-slate-600" style="height:1.75rem">
                            <span style="width:3rem;flex-shrink:0" class="border-r border-slate-200 text-center font-mono dark:border-slate-700"><?= $unit ?></span>
                            <span class="px-3">frei</span>
                        </div>
                    <?php else: ?>
                        <div class="flex items-stretch border-b border-slate-200 bg-brand-50 last:border-b-0 dark:border-slate-700 dark:bg-brand-950/40" style="min-height:<?= 1.75 * $slot['heightUnits'] ?>rem">
                            <span style="width:3rem;flex-shrink:0" class="flex items-center justify-center border-r border-slate-200 font-mono text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                <?= $slot['startUnit'] === $slot['endUnit'] ? $slot['startUnit'] : $slot['startUnit'] . '–' . $slot['endUnit'] ?>
                            </span>
                            <div class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-2 px-3 py-1">
                                <div class="min-w-0 text-sm">
                                    <span class="font-medium text-slate-800 dark:text-slate-100"><?= e($slot['label']) ?></span>
                                    <span class="ml-2 font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($slot['number']) ?></span>
                                    <?php if ($detail->fixedDevice !== null && $detail->fixedDevice['inventoryNumberId'] === $slot['inventoryNumberId']): ?>
                                        <span class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">fest zugeordnet</span>
                                    <?php endif; ?>
                                </div>
                                <form method="post" action="/rack-einbauten/<?= e($slot['slotId']) ?>/loeschen" data-confirm="Dieses Gerät aus dem Rack ausbauen?">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="rack_id" value="<?= e($rack->id) ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Ausbauen</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <form method="post" action="/racks/<?= e($rack->id) ?>/einbauten" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="min-w-0 flex-1">
                    <label class="block text-xs text-slate-500 dark:text-slate-400">Gerät einbauen (19-Zoll-Artikel)</label>
                    <select name="inventory_number_id" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <option value="">Bitte wählen…</option>
                        <?php foreach ($deviceOptions as $group): ?>
                            <?php $free = array_values(array_filter($group['devices'], static fn ($device) => $device['inRack'] === null)); ?>
                            <?php if ($free === []) { continue; } ?>
                            <optgroup label="<?= e($group['label']) ?> (<?= $group['heightUnits'] ?> HE<?= $group['installDepthCm'] !== null ? ', ' . e($cm($group['installDepthCm'])) : '' ?>)">
                                <?php foreach ($free as $device): ?>
                                    <option value="<?= e($device['id']) ?>"><?= e($device['number']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400">ab HE</label>
                    <input type="number" name="start_unit" min="1" max="<?= $rack->heightUnits ?>" placeholder="auto" class="mt-1 w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                </div>
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Einbauen</button>
            </form>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Ohne Angabe wird der erste freie Bereich genommen. Die Höhe kommt aus dem Artikel (HE), die Einbautiefe wird geprüft.</p>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Details</h2>
            <dl class="space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Breite</dt><dd class="text-slate-800 dark:text-slate-200"><?= e($rack->width->label()) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Höheneinheiten</dt><dd class="text-slate-800 dark:text-slate-200"><?= $rack->heightUnits ?> HE</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Einbautiefe</dt><dd class="text-slate-800 dark:text-slate-200"><?= e($cm($rack->installDepthCm)) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Rackschienen</dt><dd class="text-right text-slate-800 dark:text-slate-200"><?= $rack->railType === RackRailType::FrontBack ? 'vorne und hinten' : 'nur vorne' ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Farbe</dt><dd class="text-slate-800 dark:text-slate-200"><?= $rack->color !== null ? e($rack->color) : '–' ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Shockmount</dt><dd class="text-slate-800 dark:text-slate-200"><?= $rack->hasShockMount ? 'ja' : 'nein' ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Rollen</dt><dd class="text-slate-800 dark:text-slate-200"><?= $rack->hasWheels ? 'ja' : 'nein' ?></dd></div>
            </dl>
            <?php if ($rack->description !== null): ?>
                <p class="mt-3 whitespace-pre-line border-t border-slate-100 pt-3 text-slate-600 dark:border-slate-800 dark:text-slate-400"><?= e($rack->description) ?></p>
            <?php endif; ?>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Feste Zuordnung</h2>
            <?php if ($detail->fixedDevice === null): ?>
                <p class="text-slate-500 dark:text-slate-400">Keine — das Rack ist nicht fest an ein Gerät gebunden.</p>
            <?php else: ?>
                <p class="text-slate-800 dark:text-slate-200"><?= e($detail->fixedDevice['label']) ?></p>
                <p class="font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($detail->fixedDevice['number']) ?></p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Wird dieses Gerät in einer Veranstaltung ausgegeben, geht das Rack automatisch mit.</p>
                <?php if ($detail->issuedIn !== null): ?>
                    <p class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
                        Gerade unterwegs mit: <a class="underline" href="/veranstaltungen/<?= e($detail->issuedIn['rentalId']) ?>"><?= e($detail->issuedIn['eventName']) ?></a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
