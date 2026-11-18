<?php
/**
 * Tour page (migration 177): stops and waypoints in driving order with
 * material and crew per stop, plus the bulk actions.
 *
 * @var \App\Application\Tour\Query\TourOverview $tour
 * @var string|null $focusStop
 */

use App\Domain\Tour\TourAllocation;
use App\Domain\Tour\TourCrewAction;
use function App\Infrastructure\Http\e;

require __DIR__ . '/../series/_styles.php';
$rental = $tour->rental;
$stops = $tour->stops();
$waypoints = $tour->waypoints();
$fmt = static fn (?\DateTimeImmutable $d): string => $d?->format('d.m.Y H:i') ?? '–';
$fmtTime = static fn (?\DateTimeImmutable $d): string => $d?->format('H:i') ?? '';
$materialList = static function (array $material) use ($tour): string {
    if ($material === []) {
        return '<p class="text-xs text-slate-400">Kein Material.</p>';
    }
    $rows = [];
    foreach ($material as $articleId => $quantity) {
        $rows[$tour->articleName((string) $articleId)] = $quantity;
    }
    ksort($rows, SORT_NATURAL | SORT_FLAG_CASE);
    $html = '<ul class="space-y-0.5 text-xs">';
    foreach ($rows as $name => $quantity) {
        $html .= '<li><span class="inline-block w-10 text-right font-mono">' . (int) $quantity . '×</span> ' . e((string) $name) . '</li>';
    }

    return $html . '</ul>';
};
?>
<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zur Veranstaltung</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">🚚 Tour: <?= e($rental->eventName) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= count($stops) ?> Tourstops · <?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?></p>
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="/veranstaltungen/<?= e($rental->id) ?>/tour/stops/neu" class="<?= $primaryButton ?>">Tourstop hinzufügen</a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/tour/stops/neu?art=zwischenstopp" class="<?= $secondaryButton ?>">Zwischenstopp hinzufügen</a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/tour/route" class="<?= $secondaryButton ?>">Route &amp; Fahrzeiten</a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/tour/ladeliste" class="<?= $secondaryButton ?>">Ladelisten je Tourstop</a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/tour/ladeliste.pdf" target="_blank" class="<?= $secondaryButton ?>">Ladelisten (PDF)</a>
        <?php if (count($stops) <= 1): ?>
            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/tour/aufloesen" data-confirm-key="rentals.tour.revert" data-confirm="Die Tour-Planung entfernen? Tourstops und Zwischenstopps werden gelöscht, die Veranstaltung bleibt erhalten.">
                <?= $csrf ?>
                <button type="submit" class="<?= $secondaryButton ?>">Tour-Planung entfernen</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<p class="mb-4 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400" data-timing-status>
    <span class="loading-spinner text-brand-600" aria-hidden="true"></span> Fahrzeiten zwischen den Tourstops werden geprüft …
</p>

<div class="mb-6 grid gap-4 md:grid-cols-3">
    <div class="<?= $card ?>">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Grundliste</h2>
        <?= $materialList($tour->base) ?>
        <p class="mt-2 text-xs text-slate-500">Gilt für alle Tourstops; bearbeiten auf der <a class="underline" href="/veranstaltungen/<?= e($rental->id) ?>">Veranstaltungsseite</a>.</p>
    </div>
    <div class="<?= $card ?>">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Beladung zum Tourstart</h2>
        <?= $materialList($tour->startLoad) ?>
        <p class="mt-2 text-xs text-slate-500">Grundliste plus Zusätze, die die ganze Tour mitfahren.</p>
    </div>
    <div class="<?= $card ?>">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Tour-Crew</h2>
        <?php if ($tour->tourCrew === []): ?>
            <p class="text-xs text-slate-400">Noch keine Techniker – auf der Veranstaltungsseite anfragen.</p>
        <?php else: ?>
            <ul class="space-y-0.5 text-xs"><?php foreach ($tour->tourCrew as $name): ?><li><?= e($name) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</div>

<ol class="mb-8 space-y-3">
    <?php foreach ($tour->entries as $entry): ?>
        <?php $stop = $entry->stop; ?>
        <?php if (!$stop->isStop()): ?>
            <li class="ml-6 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-400">
                <span>↳ <?= e($stop->title()) ?></span>
                <?php if ($stop->addressLine() !== ''): ?><span class="text-xs"><?= e($stop->addressLine()) ?></span><?php endif; ?>
                <?php if ($stop->arriveAt !== null || $stop->departAt !== null): ?>
                    <span class="text-xs"><?= e($fmt($stop->arriveAt)) ?><?= $stop->departAt !== null ? ' → ' . e($fmt($stop->departAt)) : '' ?></span>
                <?php endif; ?>
                <?php if ($stop->note !== null): ?><span class="text-xs italic"><?= e($stop->note) ?></span><?php endif; ?>
                <span class="ml-auto flex gap-3 text-xs">
                    <a href="/tour-stops/<?= e($stop->id) ?>/bearbeiten" class="text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                    <form method="post" action="/tour-stops/<?= e($stop->id) ?>/loeschen" data-confirm-key="rentals.tour.stop.delete" data-confirm="Zwischenstopp löschen?" data-confirm-style="danger">
                        <?= $csrf ?><button type="submit" class="text-red-600 hover:underline dark:text-red-400">Löschen</button>
                    </form>
                </span>
            </li>
            <?php continue; ?>
        <?php endif; ?>
        <li id="stop-<?= e($stop->id) ?>" class="<?= $card ?><?= $entry->locked ? ' opacity-70' : '' ?>">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">Tourstop <?= (int) $entry->number ?>: <?= e($stop->title()) ?><?= $entry->locked ? ' <span class="text-xs font-normal text-slate-500">(vorbei)</span>' : '' ?></h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        <?php if ($stop->setupAt !== null): ?>Aufbau <?= e($fmt($stop->setupAt)) ?> · <?php endif; ?>
                        Veranstaltung <?= e($fmt($stop->eventStartAt)) ?><?= $stop->eventEndAt !== null ? ' – ' . e($stop->eventEndAt->format('Y-m-d') === $stop->eventStartAt?->format('Y-m-d') ? $fmtTime($stop->eventEndAt) : $fmt($stop->eventEndAt)) : '' ?>
                        <?php if ($stop->teardownEndAt !== null): ?> · Abbau bis <?= e($fmt($stop->teardownEndAt)) ?><?php endif; ?>
                    </p>
                    <?php if ($stop->contactName !== null || $stop->contactPhone !== null): ?>
                        <p class="text-xs text-slate-500">Ansprechpartner: <?= e(trim(($stop->contactName ?? '') . ' ' . ($stop->contactPhone ?? ''))) ?></p>
                    <?php endif; ?>
                    <?php if ($stop->note !== null): ?><p class="mt-1 text-xs italic text-slate-600 dark:text-slate-400"><?= nl2br(e($stop->note)) ?></p><?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-3 text-xs">
                    <a href="/tour-stops/<?= e($stop->id) ?>/bearbeiten" class="text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                    <a href="/veranstaltungen/<?= e($rental->id) ?>/tour/stops/neu?art=zwischenstopp&amp;nach=<?= e($stop->id) ?>" class="text-brand-700 hover:underline dark:text-brand-400">Zwischenstopp danach</a>
                    <?php if (count($stops) > 1): ?>
                        <form method="post" action="/tour-stops/<?= e($stop->id) ?>/loeschen" data-confirm-key="rentals.tour.stop.delete" data-confirm="Tourstop löschen? Abweichungen bei Material und Besetzung dieses Tourstops gehen verloren." data-confirm-style="danger">
                            <?= $csrf ?><button type="submit" class="text-red-600 hover:underline dark:text-red-400">Löschen</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php foreach ($entry->warnings as $warning): ?>
                <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">⚠ <?= e($warning) ?></p>
            <?php endforeach; ?>
            <div data-stop-timing="<?= e($stop->id) ?>"></div>
            <?php foreach ($entry->conflicts as $conflict): ?>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">⚠ <?= e($conflict->describe()) ?></p>
            <?php endforeach; ?>

            <div class="mt-3 grid gap-4 md:grid-cols-3">
                <div>
                    <h3 class="mb-1 text-xs font-semibold text-slate-600 dark:text-slate-400">Material</h3>
                    <?php if ($entry->deviations === []): ?>
                        <p class="text-xs text-slate-500">wie Grundliste</p>
                    <?php else: ?>
                        <ul class="space-y-0.5 text-xs">
                            <?php foreach ($entry->deviations as $articleId => $deviation): ?>
                                <li class="flex flex-wrap items-center gap-1">
                                    <span class="font-mono <?= $deviation->delta > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' ?>"><?= $deviation->delta > 0 ? '+' : '−' ?><?= abs($deviation->delta) ?>×</span>
                                    <?= e($tour->articleName((string) $articleId)) ?>
                                    <?php if ($deviation->delta > 0): ?><span class="text-slate-400">(<?= e($deviation->allocation->label()) ?>)</span><?php endif; ?>
                                    <?php if (!$entry->locked): ?>
                                        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/tour/material/<?= e($deviation->id) ?>/loeschen" class="inline">
                                            <?= $csrf ?><button type="submit" class="text-slate-400 hover:text-red-600" title="Abweichung entfernen">✕</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($entry->changes !== []): ?>
                        <p class="mt-1 text-xs text-slate-500">Laden/Abladen: <?= e(implode(', ', array_map(static fn ($c) => ($c['after'] > $c['before'] ? '+' : '−') . abs($c['after'] - $c['before']) . '× ' . $tour->articleName($c['articleId']), $entry->changes))) ?></p>
                    <?php endif; ?>
                    <details class="mt-1">
                        <summary class="cursor-pointer text-xs text-brand-700 dark:text-brand-400">Komplette Liste (<?= array_sum($entry->material) ?> Stück)</summary>
                        <div class="mt-1"><?= $materialList($entry->material) ?></div>
                    </details>
                    <?php if (!$entry->locked): ?>
                        <a href="?stop=<?= e($stop->id) ?>#material" class="mt-1 inline-block text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Material ändern …</a>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="mb-1 text-xs font-semibold text-slate-600 dark:text-slate-400">Besetzung</h3>
                    <?php if ($entry->crew === []): ?>
                        <p class="text-xs text-slate-400">Niemand eingeplant.</p>
                    <?php else: ?>
                        <ul class="space-y-0.5 text-xs">
                            <?php foreach ($entry->crew as $member): ?>
                                <li>
                                    <?= e($member['name']) ?>
                                    <?php if ($member['role'] !== null): ?><span class="text-slate-500">(<?= e($member['role']) ?>)</span><?php endif; ?>
                                    <?php if ($member['pending']): ?><span class="text-amber-700 dark:text-amber-400">· Zusage offen</span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php foreach ($entry->crewDeviations as $deviation): ?>
                        <?php if ($deviation->action === TourCrewAction::Remove): ?>
                            <p class="text-xs text-slate-500">nicht dabei: <?= e($tour->technicians[$deviation->userId] ?? $tour->tourCrew[$deviation->userId] ?? 'Techniker') ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($entry->crewDeviations !== [] && !$entry->locked): ?>
                        <details class="mt-1">
                            <summary class="cursor-pointer text-xs text-slate-500">Abweichungen entfernen</summary>
                            <?php foreach ($entry->crewDeviations as $deviation): ?>
                                <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/tour/crew/<?= e($deviation->id) ?>/loeschen" class="mt-1 text-xs">
                                    <?= $csrf ?>
                                    <button type="submit" class="text-red-600 hover:underline dark:text-red-400">✕ <?= e($deviation->action->label()) ?>: <?= e($tour->technicians[$deviation->userId] ?? 'Techniker') ?></button>
                                </form>
                            <?php endforeach; ?>
                        </details>
                    <?php endif; ?>
                    <?php if (!$entry->locked): ?>
                        <a href="?stop=<?= e($stop->id) ?>#crew" class="mt-1 inline-block text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Besetzung ändern …</a>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="mb-1 text-xs font-semibold text-slate-600 dark:text-slate-400">Minutenprogramm</h3>
                    <?php foreach ($entry->minutePrograms as $program): ?>
                        <a href="/minutenprogramme/<?= e($program['id']) ?>" class="block text-xs text-brand-700 hover:underline dark:text-brand-400"><?= e($program['name']) ?></a>
                    <?php endforeach; ?>
                    <form method="post" action="/tour-stops/<?= e($stop->id) ?>/minutenprogramm" class="mt-1">
                        <?= $csrf ?>
                        <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">+ Minutenprogramm für diesen Tourstop</button>
                    </form>
                    <p class="mt-2 text-xs text-slate-400">Stromplanung: über die Veranstaltung (gilt für die Tour).</p>
                </div>
            </div>
        </li>
    <?php endforeach; ?>
</ol>

<div class="grid gap-4 lg:grid-cols-2">
    <form id="material" method="post" action="/veranstaltungen/<?= e($rental->id) ?>/tour/material" class="<?= $card ?> space-y-3">
        <?= $csrf ?>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Material an Tourstops ändern</h2>
        <div>
            <label class="<?= $label ?>" for="tour-article">Artikel</label>
            <select id="tour-article" name="article_id" required class="<?= $input ?>">
                <option value="">Bitte wählen …</option>
                <?php foreach ($tour->articleOptions as $id => $name): ?>
                    <option value="<?= e((string) $id) ?>"><?= e($name) ?><?= isset($tour->base[$id]) ? ' (Grundliste: ' . $tour->base[$id] . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="<?= $label ?>" for="tour-quantity">Menge an jedem gewählten Tourstop</label>
            <input id="tour-quantity" type="number" name="quantity" min="0" required class="<?= $input ?> max-w-[8rem]">
            <p class="mt-1 text-xs text-slate-500">Gleich viel wie in der Grundliste entfernt die Abweichung, 0 = an diesen Tourstops nicht dabei.</p>
        </div>
        <fieldset class="text-sm">
            <legend class="<?= $label ?>">Zusatzmaterial</legend>
            <?php foreach (TourAllocation::cases() as $allocation): ?>
                <label class="mt-1 flex items-center gap-2"><input type="radio" name="allocation" value="<?= e($allocation->value) ?>"<?= $allocation === TourAllocation::StopsOnly ? ' checked' : '' ?>> <?= e(ucfirst($allocation->label())) ?></label>
            <?php endforeach; ?>
        </fieldset>
        <?php if ($waypoints !== []): ?>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="<?= $label ?>" for="tour-issue">Laden an</label>
                    <select id="tour-issue" name="issue_waypoint_id" class="<?= $input ?>">
                        <option value="">Tourstart</option>
                        <?php foreach ($waypoints as $wp): ?><option value="<?= e($wp->stop->id) ?>"><?= e($wp->stop->title()) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="<?= $label ?>" for="tour-return">Abladen an</label>
                    <select id="tour-return" name="return_waypoint_id" class="<?= $input ?>">
                        <option value="">Tourende</option>
                        <?php foreach ($waypoints as $wp): ?><option value="<?= e($wp->stop->id) ?>"><?= e($wp->stop->title()) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>
        <?php $pickerId = 'material'; require __DIR__ . '/_stop_picker.php'; ?>
        <button type="submit" class="<?= $primaryButton ?>">Übernehmen</button>
    </form>

    <form id="crew" method="post" action="/veranstaltungen/<?= e($rental->id) ?>/tour/crew" class="<?= $card ?> space-y-3">
        <?= $csrf ?>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Besetzung an Tourstops ändern</h2>
        <div>
            <label class="<?= $label ?>" for="crew-action">Was?</label>
            <select id="crew-action" name="action" class="<?= $input ?>" data-crew-action>
                <option value="replace">Vertretung: jemand übernimmt für eine andere Person</option>
                <option value="add">Zusätzlich dabei</option>
                <option value="remove">Nicht dabei</option>
            </select>
        </div>
        <div data-crew-show="replace">
            <label class="<?= $label ?>" for="crew-replaces">Wer fällt aus?</label>
            <select id="crew-replaces" name="replaces_user_id" class="<?= $input ?>">
                <option value="">Bitte wählen …</option>
                <?php foreach ($tour->tourCrew as $id => $name): ?><option value="<?= e((string) $id) ?>"><?= e($name) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="<?= $label ?>" for="crew-user"><span data-crew-label>Wer übernimmt?</span></label>
            <select id="crew-user" name="user_id" required class="<?= $input ?>">
                <option value="">Bitte wählen …</option>
                <?php foreach ($tour->technicians as $id => $name): ?><option value="<?= e((string) $id) ?>"><?= e($name) ?></option><?php endforeach; ?>
            </select>
            <p class="mt-1 text-xs text-slate-500">Vertretungen und zusätzliche Techniker werden angefragt; wer nicht mehr dabei ist, bekommt eine Info.</p>
        </div>
        <?php $pickerId = 'crew'; require __DIR__ . '/_stop_picker.php'; ?>
        <button type="submit" class="<?= $primaryButton ?>">Übernehmen</button>
    </form>
</div>

<script>
(() => {
    // Timing warnings from the route (geocoding/driving times can take a moment).
    const status = document.querySelector('[data-timing-status]');
    fetch(<?= json_encode('/veranstaltungen/' . $rental->id . '/tour/route.json', JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => { if (!r.ok) throw new Error(); return r.json(); })
        .then((data) => {
            data.timingWarnings.forEach((w) => {
                const box = document.querySelector('[data-stop-timing="' + CSS.escape(w.stopId) + '"]');
                if (!box) return;
                const p = document.createElement('p');
                p.className = 'mt-2 text-xs text-amber-700 dark:text-amber-400';
                p.textContent = '⚠ ' + w.message;
                box.appendChild(p);
            });
            const missing = data.entries.filter((e) => !e.included).length;
            if (data.error) {
                status.textContent = 'Fahrzeiten konnten nicht berechnet werden: ' + data.error;
                status.classList.add('text-red-600');
            } else if (data.timingWarnings.length === 0 && missing === 0) {
                status.textContent = data.straightLine ? 'Route als Luftlinie (ohne Fahrzeiten) – siehe „Route & Fahrzeiten“.' : 'Fahrzeiten geprüft: die Zeit zwischen den Tourstops reicht.';
            } else {
                status.textContent = (data.timingWarnings.length ? data.timingWarnings.length + ' Fahrzeit-Warnung(en) bei den Tourstops. ' : '')
                    + (missing ? missing + ' Punkt(e) ohne Koordinaten – siehe „Route & Fahrzeiten“.' : '');
                status.classList.add('text-amber-700');
            }
        })
        .catch(() => { status.textContent = 'Die Fahrzeiten konnten nicht geprüft werden.'; });

    document.querySelectorAll('[data-stop-picker]').forEach((picker) => {
        const boxes = Array.from(picker.querySelectorAll('input[type=checkbox]'));
        const ref = picker.querySelector('[data-picker-ref]');
        picker.querySelectorAll('[data-pick]').forEach((button) => button.addEventListener('click', () => {
            const refIndex = boxes.findIndex((b) => b.value === ref.value);
            boxes.forEach((box, i) => {
                if (box.dataset.locked === '1') return;
                const mode = button.dataset.pick;
                box.checked = mode === 'all' || (mode === 'open' && box.dataset.started !== '1') || (mode === 'only' && i === refIndex) || (mode === 'following' && i >= refIndex);
            });
        }));
    });
    const action = document.querySelector('[data-crew-action]');
    const update = () => {
        document.querySelectorAll('[data-crew-show]').forEach((el) => { el.hidden = el.dataset.crewShow !== action.value; });
        const label = document.querySelector('[data-crew-label]');
        label.textContent = action.value === 'replace' ? 'Wer übernimmt?' : (action.value === 'add' ? 'Wer kommt dazu?' : 'Wer ist nicht dabei?');
    };
    action.addEventListener('change', update);
    update();
})();
</script>
