<?php
/**
 * Route of a tour (migration 178). The page appears at once; the route
 * itself (geocoding, OpenRouteService) is loaded from route.json in the
 * background while spinners are shown: route points, map, legs, timing
 * warnings and Google Maps links.
 *
 * @var \App\Domain\Rental\Rental $rental
 * @var \App\Domain\Routing\RouteProfile $profile
 * @var list<array{id: string, vehicle: \App\Domain\Vehicle\Vehicle, towing: ?\App\Domain\Vehicle\Vehicle}> $planned
 * @var \App\Domain\Vehicle\Vehicle[] $vehicles
 * @var array{start: ?\App\Domain\Tour\TourStop, end: ?\App\Domain\Tour\TourStop} $endpoints
 */

use App\Domain\Routing\RouteProfile;
use function App\Infrastructure\Http\e;

require __DIR__ . '/../series/_styles.php';
$spinner = '<span class="loading-spinner" aria-hidden="true"></span>';
?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>/tour" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zur Tour</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Route: <?= e($rental->eventName) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($profile->label()) ?> <span data-route-summary></span></p>
    <div class="mt-3 flex flex-wrap gap-2" data-route-links></div>
</div>

<div data-route-warnings></div>

<div class="mb-6 grid gap-4 lg:grid-cols-3">
    <div class="<?= $card ?> lg:col-span-2 p-0">
        <div class="relative">
            <div id="tour-map" class="relative z-0 h-[28rem] w-full rounded-lg" style="isolation: isolate" aria-label="Karte der Route"></div>
            <div data-route-loading class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-lg bg-white/80 text-sm text-slate-600 dark:bg-slate-900/80 dark:text-slate-300">
                <span class="text-2xl text-brand-600"><?= $spinner ?></span>
                <span>Route wird berechnet …</span>
                <span class="text-xs text-slate-400">Orte werden gesucht und Fahrzeiten ermittelt.</span>
            </div>
        </div>
    </div>
    <div class="<?= $card ?>">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Routenpunkte</h2>
        <ol class="space-y-2 text-sm" data-route-entries>
            <li class="flex items-center gap-2 text-slate-500"><?= $spinner ?> wird geladen …</li>
        </ol>
    </div>
</div>

<div class="mb-6 grid gap-4 lg:grid-cols-3">
    <div class="<?= $card ?> lg:col-span-2 overflow-x-auto">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Etappen</h2>
        <div data-route-legs><p class="flex items-center gap-2 text-sm text-slate-500"><?= $spinner ?> wird geladen …</p></div>
    </div>

    <div class="space-y-4">
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/tour/route/profil" class="<?= $card ?> space-y-2">
            <?= $csrf ?>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Fahrprofil</h2>
            <?php foreach (RouteProfile::cases() as $option): ?>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="profile" value="<?= e($option->value) ?>"<?= $option === $profile ? ' checked' : '' ?>> <?= e($option->label()) ?></label>
            <?php endforeach; ?>
            <button type="submit" class="<?= $secondaryButton ?>">Speichern</button>
        </form>
        <div class="<?= $card ?> space-y-2 text-sm">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Start und Ziel</h2>
            <?php foreach (['start' => ['Start', 'start', 'Ausgabelager'], 'end' => ['Ziel', 'ziel', 'Rückgabelager']] as $key => [$title, $art, $default]): ?>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium"><?= e($title) ?>:</span>
                    <span><?= $endpoints[$key] !== null ? e($endpoints[$key]->label ?? $endpoints[$key]->addressLine()) : e($default) . ' der Veranstaltung' ?></span>
                    <a href="/veranstaltungen/<?= e($rental->id) ?>/tour/stops/neu?art=<?= e($art) ?>" class="text-xs text-brand-700 hover:underline dark:text-brand-400">ändern</a>
                    <?php if ($endpoints[$key] !== null): ?>
                        <form method="post" action="/tour-stops/<?= e($endpoints[$key]->id) ?>/loeschen" class="inline">
                            <?= $csrf ?><button type="submit" class="text-xs text-slate-500 hover:underline">auf <?= e($default) ?> zurücksetzen</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="<?= $card ?> space-y-2 text-sm">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Geplante Fahrzeuge</h2>
            <?php if ($planned === []): ?>
                <p class="text-xs text-slate-400">Noch keine Fahrzeuge geplant.</p>
            <?php endif; ?>
            <?php foreach ($planned as $entry): ?>
                <div class="flex items-center justify-between gap-2">
                    <span><?= e($entry['vehicle']->name) ?><?= $entry['vehicle']->licensePlate !== null ? ' (' . e($entry['vehicle']->licensePlate) . ')' : '' ?><?= $entry['towing'] !== null ? ' <span class="text-xs text-slate-500">gezogen von ' . e($entry['towing']->name) . '</span>' : '' ?></span>
                    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/tour/route/fahrzeuge/<?= e($entry['id']) ?>/loeschen">
                        <?= $csrf ?><button type="submit" class="text-xs text-red-600 hover:underline dark:text-red-400">entfernen</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/tour/route/fahrzeuge" class="space-y-2 border-t border-slate-100 pt-2 dark:border-slate-800">
                <?= $csrf ?>
                <select name="vehicle_id" required class="<?= $input ?>">
                    <option value="">Fahrzeug wählen …</option>
                    <?php foreach ($vehicles as $vehicle): ?><option value="<?= e($vehicle->id) ?>"><?= e($vehicle->name) ?><?= $vehicle->isAnhaenger() ? ' (Anhänger)' : '' ?></option><?php endforeach; ?>
                </select>
                <select name="towing_vehicle_id" class="<?= $input ?>">
                    <option value="">kein Zugfahrzeug (nur bei Anhängern)</option>
                    <?php foreach ($vehicles as $vehicle): ?><?php if ($vehicle->isAuto()): ?><option value="<?= e($vehicle->id) ?>">gezogen von <?= e($vehicle->name) ?></option><?php endif; ?><?php endforeach; ?>
                </select>
                <button type="submit" class="<?= $secondaryButton ?>">Fahrzeug planen</button>
            </form>
            <p class="text-xs text-slate-500">Maße pflegen unter <a href="/fahrzeuge" class="underline">Fahrzeuge</a>. Mit dem Profil „LKW mit Maßen“ gilt jeweils der strengste Wert; ein Gespann zählt mit Gesamtlänge und -gewicht.</p>
        </div>
    </div>
</div>

<script src="/assets/vendor/leaflet/leaflet.js"></script>
<script>
(() => {
    const url = <?= json_encode('/veranstaltungen/' . $rental->id . '/tour/route.json', JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
    const $ = (sel) => document.querySelector(sel);
    const esc = (text) => { const d = document.createElement('div'); d.textContent = text ?? ''; return d.innerHTML; };
    const km = (m) => (m / 1000).toLocaleString('de-DE', { maximumFractionDigits: m < 10000 ? 1 : 0 }) + ' km';
    const time = (s) => {
        if (s === null || s === undefined) return '–';
        const min = Math.round(s / 60);
        return min >= 60 ? Math.floor(min / 60) + ' h ' + String(min % 60).padStart(2, '0') + ' min' : min + ' min';
    };
    const colors = { start: '#059669', end: '#dc2626', stop: '#2563eb', waypoint: '#64748b' };

    const map = window.L ? L.map('tour-map', { scrollWheelZoom: false }) : null;
    if (map) {
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende',
        }).addTo(map);
        map.setView([51.16, 10.45], 6);
    }

    const legLines = [];
    const legColor = (l) => (l.billed ? '#2563eb' : '#94a3b8'); // not billed: grey

    const summarize = (data) => {
        let summary = data.restrictions ? '· ' + data.restrictions : '';
        if (data.legs.length) {
            summary += ' · ' + km(data.totalDistanceM) + (data.totalDurationS !== null ? ' · ' + time(data.totalDurationS) + ' Fahrzeit' : ' (Luftlinie)');
            if (data.billableDistanceM !== null && Math.round(data.billableDistanceM) !== Math.round(data.totalDistanceM)) {
                summary += ' · abzurechnen: ' + km(data.billableDistanceM);
            }
        }
        $('[data-route-summary]').textContent = summary;
    };

    // Legs with a checkbox "abrechnen": the drive to a stop can be left out of the billable kilometres.
    const renderLegs = (data) => {
        if (data.legs.length === 0) {
            $('[data-route-legs]').innerHTML = '<p class="text-sm text-slate-400">Für eine Route werden mindestens zwei Punkte mit Koordinaten gebraucht.</p>';
            return;
        }
        const billable = data.billableDistanceM ?? data.totalDistanceM;
        $('[data-route-legs]').innerHTML = '<table class="w-full text-sm"><thead class="text-left text-xs text-slate-500"><tr><th class="py-1 pr-2 font-medium">Von</th><th class="py-1 pr-2 font-medium">Nach</th><th class="py-1 pr-2 text-right font-medium">Strecke</th><th class="py-1 pr-2 text-right font-medium">Fahrzeit</th><th class="py-1 text-center font-medium" title="Strecke in die abzurechnenden Kilometer einrechnen">Abrechnen</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">'
            + data.legs.map((l, i) => '<tr' + (l.billed ? '' : ' class="text-slate-400 dark:text-slate-500"') + '><td class="py-1 pr-2">' + esc(l.from) + '</td><td class="py-1 pr-2">' + esc(l.to) + '</td><td class="whitespace-nowrap py-1 pr-2 text-right tabular-nums' + (l.billed ? '' : ' line-through') + '">' + km(l.distanceM) + (data.straightLine ? ' <span class="text-xs text-slate-400">(Luftlinie)</span>' : '') + '</td><td class="whitespace-nowrap py-1 pr-2 text-right tabular-nums">' + time(l.durationS) + '</td>'
                + '<td class="py-1 text-center">' + (l.stopId
                    ? '<input type="checkbox" data-leg-billing="' + i + '"' + (l.billed ? ' checked' : '') + ' aria-label="' + esc('Fahrt ' + l.from + ' → ' + l.to + ' abrechnen') + '">'
                    : '<input type="checkbox" checked disabled title="Die Fahrt zum Rückgabelager wird immer abgerechnet. Für eine Ausnahme ein eigenes Ziel festlegen.">') + '</td></tr>').join('')
            + '</tbody><tfoot class="border-t border-slate-200 text-sm font-medium dark:border-slate-700"><tr><td class="py-1 pr-2" colspan="2">Gesamt</td><td class="whitespace-nowrap py-1 pr-2 text-right tabular-nums">' + km(data.totalDistanceM) + '</td><td colspan="2"></td></tr>'
            + '<tr><td class="py-1 pr-2" colspan="2">Abzurechnen</td><td class="whitespace-nowrap py-1 pr-2 text-right tabular-nums">' + km(billable) + '</td><td colspan="2"></td></tr></tfoot></table>'
            + '<p class="mt-2 text-xs text-slate-500">Fährt z. B. ein Techniker ohne Material zwischen zwei Stops anderweitig mit, den Haken bei dieser Fahrt entfernen – sie zählt dann nicht zu den abzurechnenden Kilometern, bleibt aber für Fahrzeiten und Karte erhalten.</p>';
        $('[data-route-legs]').querySelectorAll('[data-leg-billing]').forEach((box) => box.addEventListener('change', () => {
            const leg = data.legs[Number(box.dataset.legBilling)];
            box.disabled = true;
            const body = new URLSearchParams({ billed: box.checked ? '1' : '0' });
            fetch('/tour-stops/' + encodeURIComponent(leg.stopId) + '/km-abrechnung', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body,
            })
                .then((r) => r.json().then((json) => { if (!r.ok) throw new Error(json.error || 'HTTP ' + r.status); return json; }))
                .then((json) => {
                    leg.billed = json.billed;
                    legLines[Number(box.dataset.legBilling)]?.setStyle({ color: legColor(leg) });
                    data.billableDistanceM = data.legs.reduce((sum, l) => sum + (l.billed ? l.distanceM : 0), 0);
                    summarize(data);
                    renderLegs(data);
                })
                .catch((err) => {
                    box.checked = !box.checked;
                    box.disabled = false;
                    alert('Die Änderung konnte nicht gespeichert werden: ' + err.message);
                });
        }));
    };

    const render = (data) => {
        // Summary and Google Maps links
        summarize(data);
        $('[data-route-links]').innerHTML = data.googleLinks.map((l, i) =>
            '<a href="' + esc(l.url) + '" target="_blank" rel="noopener" class="<?= $secondaryButton ?>">In Google Maps öffnen'
            + (data.googleLinks.length > 1 ? ' (Teil ' + (i + 1) + ': ' + esc(l.from) + ' → ' + esc(l.to) + ')' : '') + '</a>').join('');

        // Warnings and hints
        const errorBox = data.error
            ? '<div class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"><strong>Straßenroute nicht verfügbar – Anzeige als Luftlinie.</strong><br>' + esc(data.error) + '<br><a href="/einstellungen/routenplanung" class="underline">Einstellungen › Routenplanung</a> (dort „Verbindung testen“)</div>'
            : '';
        $('[data-route-warnings]').innerHTML = errorBox +
            data.timingWarnings.map((w) => '<p class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">⚠ ' + esc(w.message) + '</p>').join('')
            + (data.hints.length ? '<ul class="mb-4 space-y-1 text-xs text-slate-600 dark:text-slate-400">' + data.hints.map((h) => '<li>ℹ ' + esc(h) + '</li>').join('') + '</ul>' : '');

        // Route points with status
        let stopNumber = 0;
        $('[data-route-entries]').innerHTML = data.entries.map((e) => {
            const badge = e.included
                ? '<span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white" style="background:' + colors[e.kind] + '">' + (e.kind === 'stop' ? ++stopNumber : (e.kind === 'start' ? 'S' : (e.kind === 'end' ? 'Z' : '•'))) + '</span>'
                : (e.kind === 'stop' && ++stopNumber, '<span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">!</span>');
            return '<li class="flex items-start gap-2">' + badge + '<span class="min-w-0"><span class="block' + (e.included ? '' : ' text-amber-700 dark:text-amber-400') + '">' + esc(e.label) + '</span>'
                + '<span class="block text-xs text-slate-500">' + esc(e.detail) + (e.included ? '' : ' – nicht in der Route') + '</span>'
                + (e.editUrl && !e.included ? '<a href="' + esc(e.editUrl) + '" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Ort bearbeiten</a>' : '')
                + '</span></li>';
        }).join('');

        // Legs
        renderLegs(data);

        // Map
        if (!map) return;
        const bounds = [];
        let n = 0;
        data.points.forEach((p) => {
            const text = p.kind === 'stop' ? String(++n) : (p.kind === 'start' ? 'S' : (p.kind === 'end' ? 'Z' : '•'));
            const icon = L.divIcon({
                className: '',
                html: '<span style="display:flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:9999px;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.4);color:#fff;font:600 12px system-ui;background:' + colors[p.kind] + '">' + text + '</span>',
                iconSize: [24, 24],
                iconAnchor: [12, 12],
            });
            const popup = document.createElement('div');
            popup.textContent = p.label;
            L.marker([p.lat, p.lng], { icon, title: p.label }).addTo(map).bindPopup(popup);
            bounds.push([p.lat, p.lng]);
        });
        data.legs.forEach((l, i) => { legLines[i] = L.polyline(l.geometry, { color: legColor(l), weight: 4, opacity: 0.8, dashArray: data.straightLine ? '6 8' : null }).addTo(map); });
        if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30] });
        else if (bounds.length === 1) map.setView(bounds[0], 12);
    };

    fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(render)
        .catch((err) => {
            $('[data-route-entries]').innerHTML = '<li class="text-sm text-red-600">Die Route konnte nicht geladen werden (' + esc(err.message) + ').</li>';
            $('[data-route-legs]').innerHTML = '';
        })
        .finally(() => { $('[data-route-loading]').remove(); });
})();
</script>
