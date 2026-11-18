<?php
/**
 * Route of a tour (migration 178): map, legs with distance and driving
 * time, timing warnings, planned vehicles, route profile, start/destination
 * and Google Maps links.
 *
 * @var \App\Application\Routing\TourRoute $route
 * @var list<array{id: string, vehicle: \App\Domain\Vehicle\Vehicle, towing: ?\App\Domain\Vehicle\Vehicle}> $planned
 * @var \App\Domain\Vehicle\Vehicle[] $vehicles
 * @var array{start: ?\App\Domain\Tour\TourStop, end: ?\App\Domain\Tour\TourStop} $endpoints
 */

use App\Domain\Routing\RouteProfile;
use function App\Infrastructure\Http\e;

require __DIR__ . '/../series/_styles.php';
$rental = $route->rental;
$result = $route->result;
$km = static fn (float $m): string => number_format($m / 1000, $m < 10000 ? 1 : 0, ',', '.') . ' km';
$time = static function (?float $s): string {
    if ($s === null) {
        return '–';
    }
    $minutes = (int) round($s / 60);

    return $minutes >= 60 ? intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . ' min' : $minutes . ' min';
};
$mapData = [
    'points' => array_map(static fn ($p) => ['label' => $p->label, 'lat' => $p->latitude, 'lng' => $p->longitude, 'kind' => $p->kind], $route->points),
    'legs' => $result !== null ? array_map(static fn ($l) => $l->geometry, $result->legs) : [],
    'straight' => $result?->straightLine ?? true,
];
?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>/tour" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zur Tour</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Route: <?= e($rental->eventName) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e($route->profile->label()) ?><?= !$route->restrictions->isEmpty() ? ' · ' . e($route->restrictions->describe()) : '' ?>
        <?php if ($result !== null): ?> · <?= e($km($result->totalDistanceM())) ?><?= $result->totalDurationS() !== null ? ' · ' . e($time($result->totalDurationS())) . ' Fahrzeit' : '' ?><?php endif; ?>
    </p>
    <?php if ($route->googleLinks !== []): ?>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($route->googleLinks as $i => $link): ?>
                <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener" class="<?= $secondaryButton ?>">
                    In Google Maps öffnen<?= count($route->googleLinks) > 1 ? ' (Teil ' . ($i + 1) . ': ' . e($link['from']) . ' → ' . e($link['to']) . ')' : '' ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php foreach ($route->timingWarnings as $warning): ?>
    <p class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">⚠ <?= e($warning['message']) ?></p>
<?php endforeach; ?>
<?php if ($route->hints !== []): ?>
    <ul class="mb-4 space-y-1 text-xs text-slate-600 dark:text-slate-400">
        <?php foreach ($route->hints as $hint): ?><li>ℹ <?= e($hint) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="<?= $card ?> mb-6 p-0">
    <div id="tour-map" class="relative z-0 h-[28rem] w-full rounded-lg" style="isolation: isolate" aria-label="Karte der Route"></div>
</div>

<div class="mb-6 grid gap-4 lg:grid-cols-3">
    <div class="<?= $card ?> lg:col-span-2 overflow-x-auto">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Etappen</h2>
        <?php if ($result === null || $result->legs === []): ?>
            <p class="text-sm text-slate-400">Keine Etappen.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-500"><tr><th class="py-1 pr-2 font-medium">Von</th><th class="py-1 pr-2 font-medium">Nach</th><th class="py-1 pr-2 text-right font-medium">Strecke</th><th class="py-1 text-right font-medium">Fahrzeit</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($result->legs as $leg): ?>
                        <tr>
                            <td class="py-1 pr-2"><?= e($route->points[$leg->from]->label ?? '') ?></td>
                            <td class="py-1 pr-2"><?= e($route->points[$leg->to]->label ?? '') ?></td>
                            <td class="whitespace-nowrap py-1 pr-2 text-right tabular-nums"><?= e($km($leg->distanceM)) ?><?= $result->straightLine ? ' <span class="text-xs text-slate-400">(Luftlinie)</span>' : '' ?></td>
                            <td class="whitespace-nowrap py-1 text-right tabular-nums"><?= e($time($leg->durationS)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="space-y-4">
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/tour/route/profil" class="<?= $card ?> space-y-2">
            <?= $csrf ?>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Fahrprofil</h2>
            <?php foreach (RouteProfile::cases() as $profile): ?>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="profile" value="<?= e($profile->value) ?>"<?= $profile === $route->profile ? ' checked' : '' ?>> <?= e($profile->label()) ?></label>
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
    const data = <?= json_encode($mapData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>;
    const el = document.getElementById('tour-map');
    if (!window.L || !el) return;
    const map = L.map(el, { scrollWheelZoom: false });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende',
    }).addTo(map);
    const colors = { start: '#059669', end: '#dc2626', stop: '#2563eb', waypoint: '#64748b' };
    const bounds = [];
    let stopNumber = 0;
    data.points.forEach((p) => {
        const isStop = p.kind === 'stop';
        if (isStop) stopNumber++;
        const text = isStop ? String(stopNumber) : (p.kind === 'start' ? 'S' : (p.kind === 'end' ? 'Z' : '•'));
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
    data.legs.forEach((geometry) => {
        L.polyline(geometry, { color: '#2563eb', weight: 4, opacity: 0.8, dashArray: data.straight ? '6 8' : null }).addTo(map);
    });
    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [30, 30] });
    } else if (bounds.length === 1) {
        map.setView(bounds[0], 12);
    } else {
        map.setView([51.16, 10.45], 6);
    }
})();
</script>
