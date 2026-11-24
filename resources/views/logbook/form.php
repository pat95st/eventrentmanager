<?php
/**
 * Capture page of the driver's logbook in the normal layout (migration 184):
 * records a new trip or edits one. The mobile page of the QR code is
 * logbook/capture.php; both share public/assets/js/logbook-capture.js and
 * the field names.
 *
 * @var \App\Domain\Logbook\LogbookEntry|null $entry
 * @var array<string, mixed> $form values by input name
 * @var array<string, string> $errors
 * @var \App\Domain\User\User $user
 * @var bool $manager
 * @var \App\Domain\Vehicle\Vehicle[] $vehicles
 * @var \App\Domain\Vehicle\Vehicle[] $trailers
 * @var array<string, string> $drivers user id => name (managers only)
 * @var list<array{id: string, label: string, isTour: bool}> $events
 * @var list<array{group: string, options: list<array{value: string, label: string}>}> $generalLocations
 * @var array{options: list<array{value: string, label: string}>, stops: list<array{id: string, label: string}>, outbound: array, return: array}|null $rentalLocations
 * @var \App\Domain\Logbook\LogbookEntryChange[] $history
 * @var \DateTimeImmutable|null $editableUntil
 * @var string $driverLabel
 */

use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookStatus;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\LogbookLabels;
use App\Infrastructure\Http\LogbookOdometerSession;

use function App\Infrastructure\Http\e;

require __DIR__ . '/../rentals/series/_styles.php';
$v = static fn (string $key): string => is_scalar($form[$key] ?? null) ? (string) $form[$key] : '';
$on = static fn (string $key): bool => ($form[$key] ?? null) !== null && $form[$key] !== '' && $form[$key] !== false;
$err = static fn (string $key): string => isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
$field = static fn (string ...$keys): string => 'mt-1 w-full rounded-md border px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 '
    . (array_intersect_key($errors, array_flip($keys)) !== [] ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700');
$toggle = 'inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300';
$category = $v('category') !== '' ? $v('category') : LogbookCategory::Event->value;
$categories = LogbookCategory::selectable();
if ($entry?->category === LogbookCategory::Unclear) {
    $categories[] = LogbookCategory::Unclear;
}
$odometerVehicle = null;
foreach ($vehicles as $candidate) {
    if ($candidate->id === $v('vehicle_id')) {
        $odometerVehicle = $candidate;
    }
}
$verifiedOdometer = $odometerVehicle !== null && $v('odometer_start') !== '' && LogbookOdometerSession::confirmed($odometerVehicle->id) === (int) $v('odometer_start') ? (int) $v('odometer_start') : null;
$isTour = false;
foreach ($events as $event) {
    if ($event['id'] === $v('rental_id')) {
        $isTour = $event['isTour'];
    }
}
$action = $entry !== null ? '/fahrtenbuch/' . $entry->id : '/fahrtenbuch';
$locationOptions = static function (string $selected) use ($generalLocations, $rentalLocations): string {
    $option = static fn (string $value, string $label): string => '<option value="' . e($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    $html = '<option value="">– bitte wählen –</option>';
    $html .= '<optgroup label="Veranstaltung" data-rental-group' . (($rentalLocations['options'] ?? []) === [] ? ' hidden' : '') . '>'
        . implode('', array_map(static fn ($o) => $option($o['value'], $o['label']), $rentalLocations['options'] ?? [])) . '</optgroup>';
    foreach ($generalLocations as $group) {
        $html .= '<optgroup label="' . e($group['group']) . '">' . implode('', array_map(static fn ($o) => $option($o['value'], $o['label']), $group['options'])) . '</optgroup>';
    }

    return $html . '<optgroup label="Sonstiges">' . $option('custom:', 'Freie Adresse eingeben …') . '</optgroup>';
};
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="<?= $v('return_to_rental') !== '' ? '/veranstaltungen/' . e($v('return_to_rental')) : '/fahrtenbuch' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; <?= $v('return_to_rental') !== '' ? 'Zurück zur Veranstaltung' : 'Fahrtenbuch' ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $entry !== null ? 'Fahrt bearbeiten' : 'Fahrt erfassen' ?></h1>
        <?php if ($entry !== null): ?><p class="text-sm text-slate-500 dark:text-slate-400">erfasst am <?= e($entry->createdAt->format('d.m.Y H:i')) ?> · <?= e($entry->status->label()) ?></p><?php endif; ?>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">Bitte die markierten Angaben prüfen.</div>
<?php endif; ?>
<?php if ($editableUntil !== null): ?>
    <div class="mb-4 rounded-md border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-800 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-300">Du kannst diese Fahrt bis <?= e($editableUntil->format('d.m.Y H:i')) ?> Uhr ändern oder löschen.</div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" id="trip-form" novalidate class="space-y-4"
      data-templates="<?= e(json_encode($rentalLocations !== null ? ['outbound' => $rentalLocations['outbound'], 'return' => $rentalLocations['return']] : null, JSON_THROW_ON_ERROR)) ?>"
      data-api-base="/fahrtenbuch" data-distance-touched="<?= $v('distance_km') !== '' ? '1' : '0' ?>">
    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
    <?php if ($v('return_to_rental') !== ''): ?><input type="hidden" name="return_to_rental" value="<?= e($v('return_to_rental')) ?>"><?php endif; ?>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="<?= $card ?> space-y-3">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Wann &amp; wer</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="<?= $label ?>">Datum *
                    <input type="date" name="trip_date" value="<?= e($v('trip_date')) ?>" required data-trip-date class="<?= $field('tripDate') ?>"><?= $err('tripDate') ?>
                </label>
                <label class="<?= $label ?>">Abfahrt
                    <input type="time" name="depart_time" value="<?= e($v('depart_time')) ?>" class="<?= $field('departTime') ?>"><?= $err('departTime') ?>
                </label>
                <label class="<?= $label ?>">Ankunft
                    <input type="time" name="arrive_time" value="<?= e($v('arrive_time')) ?>" class="<?= $field('arriveTime') ?>"><?= $err('arriveTime') ?>
                </label>
            </div>
            <?php if ($manager): ?>
                <label class="<?= $label ?> block">Fahrer
                    <select name="driver_user_id" data-driver-select class="<?= $field('driverUserId') ?>">
                        <?php foreach ($drivers as $driverId => $driverName): ?>
                            <option value="<?= e($driverId) ?>" <?= ($v('driver_user_id') !== '' ? $v('driver_user_id') : $user->id) === $driverId ? 'selected' : '' ?>><?= e($driverName) ?></option>
                        <?php endforeach; ?>
                        <option value="external" <?= $v('driver_user_id') === 'external' ? 'selected' : '' ?>>Externer Fahrer …</option>
                    </select><?= $err('driverUserId') ?>
                </label>
                <div data-external-fields><div class="grid gap-3 sm:grid-cols-2">
                    <label class="<?= $label ?>">Name *
                        <input type="text" name="driver_name" value="<?= e($v('driver_name')) ?>" maxlength="150" class="<?= $field('driverName') ?>"><?= $err('driverName') ?>
                    </label>
                    <label class="<?= $label ?>">Telefon
                        <input type="tel" name="driver_phone" value="<?= e($v('driver_phone')) ?>" maxlength="50" class="<?= $field() ?>">
                    </label>
                </div></div>
            <?php else: ?>
                <p class="text-sm text-slate-600 dark:text-slate-400">Fahrer: <?= e($driverLabel) ?></p>
            <?php endif; ?>
        </div>

        <div class="<?= $card ?> space-y-3">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Fahrzeug</h2>
            <label class="<?= $label ?> block">Fahrzeug *
                <select name="vehicle_id" data-vehicle-select class="<?= $field('vehicleId') ?>">
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <option value="<?= e($vehicle->id) ?>" data-odometer="<?= $vehicle->logbookOdometerCheck ? '1' : '0' ?>" <?= $v('vehicle_id') === $vehicle->id ? 'selected' : '' ?>><?= e($vehicle->displayName() . ($vehicle->isPrivate ? ' · privat' : '')) ?></option>
                    <?php endforeach; ?>
                    <option value="other" <?= $v('vehicle_id') === 'other' ? 'selected' : '' ?>>Sonstiges Fahrzeug …</option>
                </select><?= $err('vehicleId') ?>
            </label>
            <div data-other-vehicle><label class="<?= $label ?>">Welches Fahrzeug? *
                <input type="text" name="other_vehicle_label" value="<?= e($v('other_vehicle_label')) ?>" maxlength="150" placeholder="z. B. Privat-PKW, M-XY 123" class="<?= $field('otherVehicleLabel') ?>"><?= $err('otherVehicleLabel') ?>
            </label></div>
            <?php if ($trailers !== []): ?>
                <label class="<?= $label ?> block">Anhänger
                    <select name="trailer_id" class="<?= $field('trailerId') ?>">
                        <option value="">ohne Anhänger</option>
                        <?php foreach ($trailers as $trailer): ?>
                            <option value="<?= e($trailer->id) ?>" <?= $v('trailer_id') === $trailer->id ? 'selected' : '' ?>><?= e($trailer->displayName()) ?></option>
                        <?php endforeach; ?>
                    </select><?= $err('trailerId') ?>
                </label>
            <?php endif; ?>
        </div>
    </div>

    <div class="<?= $card ?> space-y-3">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Art der Fahrt</h2>
        <div class="flex flex-wrap gap-4" role="radiogroup">
            <?php foreach ($categories as $option): ?>
                <label class="<?= $toggle ?>"><input type="radio" name="category" value="<?= e($option->value) ?>" <?= $category === $option->value ? 'checked' : '' ?> data-category> <?= e($option->label()) ?></label>
            <?php endforeach; ?>
        </div>
        <?= $err('category') ?>
        <div data-show-for="event"><div class="grid gap-3 lg:grid-cols-3">
            <label class="<?= $label ?> lg:col-span-2">Veranstaltung *
                <select name="rental_id" data-rental-select class="<?= $field('rentalId') ?>">
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= e($event['id']) ?>" <?= $v('rental_id') === $event['id'] ? 'selected' : '' ?>><?= e($event['label']) ?></option>
                    <?php endforeach; ?>
                </select><?= $err('rentalId') ?>
                <span class="mt-1 block font-normal text-slate-500">Angeboten werden Veranstaltungen ±3 Tage um das Datum der Fahrt.</span>
            </label>
            <div data-tour-stop <?= $isTour ? '' : 'hidden' ?>><label class="<?= $label ?>">Tourstop
                <select name="tour_stop_id" data-tour-stop-select class="<?= $field('tourStopId') ?>">
                    <option value="">ganze Tour</option>
                    <?php foreach ($rentalLocations['stops'] ?? [] as $stop): ?>
                        <option value="<?= e($stop['id']) ?>" <?= $v('tour_stop_id') === $stop['id'] ? 'selected' : '' ?>><?= e($stop['label']) ?></option>
                    <?php endforeach; ?>
                </select><?= $err('tourStopId') ?>
            </label></div>
            <div class="lg:col-span-3" data-templates <?= $rentalLocations === null ? 'hidden' : '' ?>><div class="flex flex-wrap items-end gap-2">
                <span class="text-xs text-slate-500">Vorlage:</span>
                <button type="button" class="<?= $secondaryButton ?>" data-template="outbound">➜ Hinfahrt (Lager → Veranstaltung)</button>
                <button type="button" class="<?= $secondaryButton ?>" data-template="return">⟲ Rückfahrt (Veranstaltung → Lager)</button>
            </div></div>
        </div></div>
        <label class="<?= $label ?> block"><span data-purpose-label><?= $category === LogbookCategory::Business->value ? 'Zweck der Fahrt *' : 'Zweck / Notiz' ?></span>
            <input type="text" name="purpose" value="<?= e($v('purpose')) ?>" maxlength="500" placeholder="z. B. Material abholen" class="<?= $field('purpose') ?>"><?= $err('purpose') ?>
        </label>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="<?= $card ?> space-y-3">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Strecke</h2>
            <p class="text-xs text-slate-500" data-show-for="private unclear">Bei Privatfahrten sind Start und Ziel freiwillig.</p>
            <label class="<?= $label ?> block">Start
                <select name="from" data-location="from" class="<?= $field('from') ?>"><?= $locationOptions($v('from')) ?></select><?= $err('from') ?>
            </label>
            <div data-custom="from" class="space-y-2">
                <input type="text" name="from_text" value="<?= e($v('from_text')) ?>" maxlength="255" placeholder="Straße Nr., PLZ Ort" class="<?= $field() ?>">
                <label class="<?= $toggle ?>"><input type="checkbox" name="save_from_address" value="1" <?= $on('save_from_address') ? 'checked' : '' ?>> Als gespeicherte Adresse ablegen</label>
            </div>
            <div class="flex justify-center"><button type="button" data-swap class="<?= $secondaryButton ?>" title="Start und Ziel tauschen">⇅ tauschen</button></div>
            <label class="<?= $label ?> block">Ziel
                <select name="to" data-location="to" class="<?= $field('to') ?>"><?= $locationOptions($v('to')) ?></select><?= $err('to') ?>
            </label>
            <div data-custom="to" class="space-y-2">
                <input type="text" name="to_text" value="<?= e($v('to_text')) ?>" maxlength="255" placeholder="Straße Nr., PLZ Ort" class="<?= $field() ?>">
                <label class="<?= $toggle ?>"><input type="checkbox" name="save_to_address" value="1" <?= $on('save_to_address') ? 'checked' : '' ?>> Als gespeicherte Adresse ablegen</label>
            </div>
            <label class="<?= $toggle ?>"><input type="checkbox" name="round_trip" value="1" <?= $on('round_trip') ? 'checked' : '' ?> data-round-trip> Hin- und Rückfahrt (Start → Ziel → Start)</label>
        </div>

        <div class="<?= $card ?> space-y-3">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Kilometer</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="<?= $label ?>"><span data-odometer-label>Km-Stand Abfahrt</span>
                    <input type="text" name="odometer_start" value="<?= e($v('odometer_start')) ?>" inputmode="numeric" autocomplete="off" data-odometer="start" <?= $verifiedOdometer !== null ? 'readonly' : '' ?> class="<?= $field('odometerStart') ?> read-only:bg-slate-50 dark:read-only:bg-slate-800"><?= $err('odometerStart') ?>
                </label>
                <label class="<?= $label ?>"><span data-odometer-label>Km-Stand Ankunft</span>
                    <input type="text" name="odometer_end" value="<?= e($v('odometer_end')) ?>" inputmode="numeric" autocomplete="off" data-odometer="end" class="<?= $field('odometerEnd') ?>"><?= $err('odometerEnd') ?>
                </label>
            </div>
            <?php if ($verifiedOdometer !== null): ?>
                <p class="text-xs text-green-700 dark:text-green-400">✓ Kilometerstand bei Abfahrt geprüft.</p>
            <?php elseif ($odometerVehicle !== null && $odometerVehicle->logbookOdometerCheck && $entry === null): ?>
                <p class="text-xs text-slate-500">Für dieses Fahrzeug wird der Kilometerstand geprüft. <a href="/fahrtenbuch/fahrzeug/<?= e($odometerVehicle->id) ?>/kilometerstand" class="underline">Kilometerstand-Abfrage öffnen</a> (dort lässt sich auch eine Lücke melden).</p>
            <?php endif; ?>
            <?php if ($manager && $entry === null): ?>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 [&[hidden]]:hidden" data-bypass><input type="checkbox" name="bypass_odometer_check" value="1" <?= $on('bypass_odometer_check') ? 'checked' : '' ?>> Kilometerstand-Prüfung umgehen (wird vermerkt)</label>
            <?php endif; ?>
            <div data-distance-row><div class="flex items-start gap-2">
                <label class="<?= $label ?> min-w-0 flex-1"><span data-distance-label><?= $on('round_trip') ? 'Kilometer (einfache Strecke)' : 'Gefahrene Kilometer' ?></span>
                    <input type="text" name="distance_km" value="<?= e($v('distance_km')) ?>" inputmode="numeric" autocomplete="off" data-distance class="<?= $field('distanceKm') ?>"><?= $err('distanceKm') ?>
                </label>
                <button type="button" class="<?= $secondaryButton ?> mt-5" data-suggest>Vorschlagen</button>
            </div></div>
            <p class="text-xs text-slate-500" data-suggest-hint></p>
            <div class="rounded-md bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800 [&_b]:text-brand-700 dark:[&_b]:text-brand-400" data-total hidden></div>
            <?php if ($manager): ?>
                <label class="<?= $toggle ?>"><input type="checkbox" name="not_billed" value="1" <?= $on('not_billed') ? 'checked' : '' ?>> Nicht abrechnen (z. B. Mitfahrt oder Umweg)</label>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions flex flex-wrap items-center gap-3">
        <button type="submit" class="<?= $primaryButton ?> px-4 py-2">Fahrt speichern</button>
        <a href="/fahrtenbuch" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>

<?php if ($entry !== null): ?>
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="<?= $card ?> space-y-3">
            <?php if ($manager && $entry->status !== LogbookStatus::Approved): ?>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Freigabe</h2>
                <p class="text-xs text-slate-500">Status: <?= e($entry->status->label()) ?>. Nicht freigegebene Fahrten zählen nicht für die Abrechnung.</p>
                <div class="flex flex-wrap gap-2">
                    <form method="post" action="/fahrtenbuch/<?= e($entry->id) ?>/freigeben"><?= $csrf ?><button type="submit" class="<?= $primaryButton ?>">Freigeben</button></form>
                    <?php if ($entry->status === LogbookStatus::Pending): ?>
                        <form method="post" action="/fahrtenbuch/<?= e($entry->id) ?>/ablehnen"><?= $csrf ?><button type="submit" class="<?= $secondaryButton ?>">Ablehnen</button></form>
                    <?php endif; ?>
                </div>
                <hr class="border-slate-100 dark:border-slate-800">
            <?php endif; ?>
            <form method="post" action="/fahrtenbuch/<?= e($entry->id) ?>/loeschen" data-confirm-key="logbook.delete" data-confirm="Diese Fahrt wirklich löschen? Fahrtenbuch-Verwalter können sie wiederherstellen." data-confirm-style="danger">
                <?= $csrf ?><button type="submit" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">Fahrt löschen</button>
            </form>
        </div>
        <?php if ($history !== []): ?>
            <div class="<?= $card ?>">
                <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Verlauf</h2>
                <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <?php foreach (array_reverse($history) as $change): ?>
                        <li class="py-2">
                            <?= e(ucfirst($change->actionLabel())) ?> von <?= e($change->actorName ?? 'Unbekannt') ?>
                            <span class="block text-xs text-slate-500"><?= e($change->createdAt->format('d.m.Y H:i')) ?><?= $change->action === 'updated' && $change->changes !== [] ? ' · ' . e(implode(', ', array_map([LogbookLabels::class, 'field'], array_keys($change->changes)))) : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="/assets/js/logbook-capture.js"></script>
