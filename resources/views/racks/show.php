<?php
/** @var \App\Application\Rack\Query\RackDetail $detail */
/** @var list<array{label: string, heightUnits: int, installDepthCm: string|null, devices: list<array{id: string, number: string, inRack: string|null}>}> $deviceOptions */

use App\Domain\Rack\RackRailType;
use function App\Infrastructure\Http\e;

$rack = $detail->rack;
$cm = static fn (?string $value) => $value !== null ? rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ',') . ' cm' : '–';
$csrf = e(\App\Infrastructure\Http\Csrf::token());
$btn = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$smallBtn = 'inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800';
$prefixPattern = $detail->manufacturer?->prefix !== null && $rack->prefix !== null ? $detail->manufacturer->prefix . '-' . $rack->prefix . '-…' : null;
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/racks" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Alle Racks</a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($rack->name) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            <?= e($detail->manufacturer?->name ?? 'ohne Hersteller') ?>
            <?php if ($prefixPattern !== null): ?> · <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e($prefixPattern) ?></span><?php endif; ?>
            · <?= count($detail->exemplars) ?> Exemplar<?= count($detail->exemplars) === 1 ? '' : 'e' ?>
        </p>
    </div>
    <div class="flex items-center gap-3">
        <a href="/racks/<?= e($rack->id) ?>/bearbeiten" class="<?= $btn ?>">Rack-Typ bearbeiten</a>
        <form method="post" action="/racks/<?= e($rack->id) ?>/loeschen" data-confirm="Diesen Rack-Typ mit allen <?= count($detail->exemplars) ?> Exemplaren löschen? Die eingebauten Geräte bleiben erhalten." data-confirm-style="danger">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">Löschen</button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <?php if ($detail->exemplars === []): ?>
            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                Noch keine Exemplare. Lege unten das erste Rack dieses Typs an – es bekommt automatisch eine Rack-Nummer und eine Inventurnummer.
            </div>
        <?php endif; ?>

        <?php foreach ($detail->exemplars as $entry): ?>
            <?php $exemplar = $entry->exemplar; $freeUnits = $entry->freeUnits(); $halfFree = $entry->halfFreeUnits(); ?>
            <div id="exemplar-<?= e($exemplar->id) ?>" class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-100 px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Rack-Exemplar</p>
                        <p class="font-mono font-semibold text-slate-900 dark:text-slate-100" style="font-size:1rem"><?= e($exemplar->rackNumber) ?></p>
                        <p class="font-mono text-xs text-slate-600 dark:text-slate-400">Inventurnummer: <?= e($exemplar->inventoryNumber) ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= $entry->usedUnits() ?> von <?= $rack->heightUnits ?> HE belegt · <?= $freeUnits ?> frei<?= $halfFree > 0 ? ' (+ ' . $halfFree . ' × 9,5″)' : '' ?> · Angeschafft: <?= $exemplar->acquisitionDate !== null ? e($exemplar->acquisitionDate->format('d.m.Y')) : '–' ?></p>
                        <?php if ($entry->issuedIn !== null): ?>
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Unterwegs mit: <a class="underline" href="/veranstaltungen/<?= e($entry->issuedIn['rentalId']) ?>"><?= e($entry->issuedIn['eventName']) ?></a></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Beschriftung:</span>
                        <a href="/rack-exemplare/<?= e($exemplar->id) ?>/beschriftung-front.pdf" target="_blank" class="<?= $smallBtn ?>" title="Für den vorderen Deckel (A6)">FRONT</a>
                        <a href="/rack-exemplare/<?= e($exemplar->id) ?>/beschriftung-rear.pdf" target="_blank" class="<?= $smallBtn ?>" title="Für den hinteren Deckel (A6)">REAR</a>
                        <a href="/rack-exemplare/<?= e($exemplar->id) ?>/beschriftung-inhalt.pdf" target="_blank" class="<?= $smallBtn ?>" title="Inhaltsliste für oben auf dem Rack (A5)">Inhalt (oben)</a>
                        <form method="post" action="/rack-exemplare/<?= e($exemplar->id) ?>/loeschen" data-confirm="Rack <?= e($exemplar->rackNumber) ?> löschen? Die eingebauten Geräte bleiben erhalten, die Nummer wird nicht erneut vergeben." data-confirm-style="danger">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                        </form>
                    </div>
                </div>

                <div class="p-4">
                    <?php
                    // Raster: Zeile = HE, Spalten = HE-Nummer + Rackbreite (bei 19 Zoll zwei 9,5-Zoll-Hälften), Migration 128.
                    $halves = $entry->hasHalves();
                    $columnFor = static fn (string $column) => match ($column) {
                        'left' => '2',
                        'right' => '3',
                        default => $halves ? '2 / span 2' : '2',
                    };
                    ?>
                    <div class="overflow-hidden rounded-md border border-slate-300 dark:border-slate-700"
                         style="display:grid;grid-template-columns:3rem <?= $halves ? 'minmax(0,1fr) minmax(0,1fr)' : 'minmax(0,1fr)' ?>;grid-auto-rows:minmax(1.75rem,auto)"
                         role="list" aria-label="Bestückung <?= e($exemplar->rackNumber) ?>">
                        <?php for ($unit = 1; $unit <= $rack->heightUnits; $unit++): ?>
                            <span class="flex items-center justify-center border-b border-r border-slate-200 font-mono text-xs text-slate-400 dark:border-slate-700 dark:text-slate-500" style="grid-row:<?= $unit ?>;grid-column:1"><?= $unit ?></span>
                        <?php endfor; ?>
                        <?php foreach ($entry->cells() as $cell): ?>
                            <?php $placement = 'grid-row:' . $cell['row'] . ' / span ' . $cell['span'] . ';grid-column:' . $columnFor($cell['column']); ?>
                            <?php if ($cell['slot'] === null): ?>
                                <div class="flex items-center border-b border-slate-100 px-3 text-xs text-slate-400 dark:border-slate-800 dark:text-slate-600" style="<?= $placement ?><?= $cell['column'] === 'left' ? ';border-right:1px dashed rgb(148 163 184 / .7)' : '' ?>">
                                    frei<?= $cell['column'] !== 'full' ? ' (9,5″)' : '' ?>
                                </div>
                            <?php else: ?>
                                <?php $slot = $cell['slot']; $half = $cell['column'] !== 'full'; ?>
                                <div role="listitem" class="flex min-w-0 flex-wrap items-center justify-between gap-x-2 gap-y-1 border-b border-slate-200 bg-brand-50 px-3 py-1 dark:border-slate-700 dark:bg-brand-950/40" style="<?= $placement ?><?= $cell['column'] === 'left' ? ';border-right:1px dashed rgb(148 163 184 / .7)' : '' ?>">
                                    <div class="min-w-0 text-sm">
                                        <span class="font-medium text-slate-800 dark:text-slate-100"><?= e($slot['label']) ?></span>
                                        <span class="ml-1 font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($slot['number']) ?></span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                                            HE <?= $slot['startUnit'] === $slot['endUnit'] ? $slot['startUnit'] : $slot['startUnit'] . '–' . $slot['endUnit'] ?><?= $half ? ' · 9,5″ ' . ($cell['column'] === 'left' ? 'links' : 'rechts') : '' ?>
                                        </span>
                                        <?php if ($entry->fixedDevice !== null && $entry->fixedDevice['inventoryNumberId'] === $slot['inventoryNumberId']): ?>
                                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">fest zugeordnet</span>
                                        <?php endif; ?>
                                    </div>
                                    <form method="post" action="/rack-einbauten/<?= e($slot['slotId']) ?>/loeschen" data-confirm="Dieses Gerät aus dem Rack ausbauen?" data-row-actions="off">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="rack_id" value="<?= e($rack->id) ?>">
                                        <input type="hidden" name="exemplar_id" value="<?= e($exemplar->id) ?>">
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Ausbauen</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <form method="post" action="/rack-exemplare/<?= e($exemplar->id) ?>/einbauten" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <div class="min-w-0 flex-1" style="flex-basis:16rem">
                            <label class="block text-xs text-slate-500 dark:text-slate-400">Gerät einbauen (<?= $rack->width === \App\Domain\Rack\RackWidth::Half ? 'nur 9,5-Zoll-Geräte' : '19- oder 9,5-Zoll-Geräte' ?>)</label>
                            <select name="inventory_number_id" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                                <option value="">Bitte wählen…</option>
                                <?php foreach ($deviceOptions as $group): ?>
                                    <?php $groupHalf = ($group['rackWidth'] ?? '19') === \App\Domain\Rack\RackWidth::Half->value; ?>
                                    <?php if ($rack->width === \App\Domain\Rack\RackWidth::Half && !$groupHalf) { continue; } /* 19-Zoll-Geräte passen nicht ins 9,5-Zoll-Rack */ ?>
                                    <?php $free = array_values(array_filter($group['devices'], static fn ($device) => $device['inRack'] === null)); ?>
                                    <?php if ($free === []) { continue; } ?>
                                    <optgroup label="<?= e($group['label']) ?> (<?= $groupHalf ? '9,5″, ' : '' ?><?= $group['heightUnits'] ?> HE<?= $group['installDepthCm'] !== null ? ', ' . e($cm($group['installDepthCm'])) : '' ?>)">
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
                        <?php if ($rack->width === \App\Domain\Rack\RackWidth::Full): ?>
                            <div>
                                <label class="block text-xs text-slate-500 dark:text-slate-400" title="Nur für 9,5-Zoll-Geräte: welche Hälfte des 19-Zoll-Racks">Seite (9,5″)</label>
                                <select name="side" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                                    <option value="">auto</option>
                                    <option value="left">links</option>
                                    <option value="right">rechts</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="<?= $btn ?>">Einbauen</button>
                    </form>

                    <form method="post" action="/rack-exemplare/<?= e($exemplar->id) ?>" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <div class="min-w-0 flex-1" style="flex-basis:16rem">
                            <label class="block text-xs text-slate-500 dark:text-slate-400">Fest zugeordnetes Gerät (Rack geht bei dessen Ausgabe mit)</label>
                            <select name="fixed_inventory_number_id" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                                <option value="">– keine feste Zuordnung –</option>
                                <?php foreach ($deviceOptions as $group): ?>
                                    <optgroup label="<?= e($group['label']) ?>">
                                        <?php foreach ($group['devices'] as $device): ?>
                                            <option value="<?= e($device['id']) ?>" <?= $exemplar->fixedInventoryNumberId === $device['id'] ? 'selected' : '' ?>><?= e($device['number']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 dark:text-slate-400">Angeschafft</label>
                            <input type="date" name="acquisition_date" value="<?= e($exemplar->acquisitionDate?->format('Y-m-d') ?? '') ?>" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <button type="submit" class="<?= $btn ?>">Speichern</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Neues Exemplar</h2>
            <?php if ($detail->nextInventoryNumberPreview === null): ?>
                <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
                    Um ein Exemplar anzulegen, müssen zuerst ein Hersteller-Kürzel (unter „Hersteller“) und ein Rack-Kürzel (<a href="/racks/<?= e($rack->id) ?>/bearbeiten" class="underline">Rack-Typ bearbeiten</a>) hinterlegt sein.
                </p>
            <?php else: ?>
                <form method="post" action="/racks/<?= e($rack->id) ?>/exemplare" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Rack-Nummer</span>
                        <p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 font-mono text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-400"><?= e($detail->nextRackNumberPreview ?? 'RACK-…') ?></p>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Inventurnummer</span>
                        <p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 font-mono text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-400"><?= e($detail->nextInventoryNumberPreview) ?></p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400">Angeschafft</label>
                        <input type="date" name="acquisition_date" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                    </div>
                    <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Exemplar anlegen</button>
                </form>
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Beide Nummern werden automatisch fortlaufend vergeben.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Rack-Typ</h2>
            <dl class="space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Kürzel</dt><dd class="font-mono text-slate-800 dark:text-slate-200"><?= e($rack->prefix ?? '–') ?></dd></div>
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
        <p class="px-1 text-xs text-slate-500 dark:text-slate-400">Die Bestückung, die feste Gerätezuordnung und die Beschriftungen gelten je Exemplar.</p>
    </div>
</div>
