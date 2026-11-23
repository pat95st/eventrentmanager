<?php
/**
 * Logbook settings of a vehicle (migration 183, right "Fahrtenbuch verwalten").
 *
 * @var \App\Domain\Vehicle\Vehicle $vehicle
 * @var string|null $ownerName
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use function App\Infrastructure\Http\e;

$checked = static fn (string $field, bool $current): bool => array_key_exists($field, $old) ? (bool) $old[$field] : $current;
$value = static fn (string $field, ?string $current): string => array_key_exists($field, $old) ? (string) ($old[$field] ?? '') : (string) ($current ?? '');
$input = 'mt-1 w-full rounded-md border px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500';
$border = static fn (string $key): string => isset($errors[$key]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700';
$toggle = static function (string $name, bool $isChecked, string $label, string $hint, string $extra = '') {
    ?>
    <label class="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-300">
        <input type="checkbox" name="<?= e($name) ?>" value="1" class="mt-0.5" <?= $isChecked ? 'checked' : '' ?> <?= $extra ?>>
        <span>
            <span class="font-medium"><?= e($label) ?></span>
            <span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($hint) ?></span>
        </span>
    </label>
    <?php
};
?>

<div class="mb-6">
    <a href="/fahrzeuge" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Fahrzeuge</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Fahrtenbuch – <?= e($vehicle->name) ?></h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        <?= e($vehicle->category->label()) ?><?= $vehicle->licensePlate !== null ? ' · ' . e($vehicle->licensePlate) : '' ?>
        <?php if ($vehicle->isPrivate): ?>
            · Privatfahrzeug<?= $ownerName !== null ? ' von ' . e($ownerName) : ' (ohne Eigentümer)' ?>
        <?php endif; ?>
    </p>
</div>

<form method="post" action="/fahrzeuge/<?= e($vehicle->id) ?>/fahrtenbuch" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <section class="space-y-3 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Erfassung per QR-Code</h3>
        <?php $toggle('logbook_qr_enabled', $checked('logbook_qr_enabled', $vehicle->logbookQrEnabled), 'QR-Erfassung aktiv', 'Über den QR-Code des Fahrzeugs kann direkt ein Fahrtenbuch-Eintrag angelegt werden. Ohne Login werden Benutzer mit dem Recht „Fahrer“ zur Anmeldung geleitet.'); ?>
        <?php $toggle('logbook_anonymous_allowed', $checked('logbook_anonymous_allowed', $vehicle->logbookAnonymousAllowed), 'Erfassung ohne Login erlauben', 'Externe Fahrer können über den QR-Code unter Angabe ihres Namens eine Fahrt erfassen.', 'data-anonymous-toggle'); ?>
        <div data-anonymous-options class="ml-7 space-y-3 border-l border-slate-200 pl-4 dark:border-slate-700">
            <?php $toggle('logbook_anonymous_private_allowed', $checked('logbook_anonymous_private_allowed', $vehicle->logbookAnonymousPrivateAllowed), 'Privatfahrten für externe Fahrer erlauben', 'Ohne diese Option können externe Fahrer nur geschäftliche Fahrten erfassen.'); ?>
            <?php $toggle('logbook_anonymous_requires_approval', $checked('logbook_anonymous_requires_approval', $vehicle->logbookAnonymousRequiresApproval), 'Einträge externer Fahrer müssen freigegeben werden', 'Bis zur Freigabe durch einen Fahrtenbuch-Verwalter zählen sie nicht für die Abrechnung, wohl aber für den Kilometerstand.'); ?>
        </div>
    </section>

    <section class="space-y-3 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kilometerstand-Prüfung</h3>
        <?php $toggle('logbook_odometer_check', $checked('logbook_odometer_check', $vehicle->logbookOdometerCheck), 'Kilometerstand vor der Erfassung abfragen', 'Eine neue Fahrt kann erst angelegt werden, wenn der aktuelle Kilometerstand korrekt angegeben wurde. Kilometerstand Start und Ende sind dann Pflicht.', 'data-odometer-toggle'); ?>
        <div data-odometer-options class="ml-7 grid grid-cols-1 gap-3 border-l border-slate-200 pl-4 sm:grid-cols-2 dark:border-slate-700">
            <div>
                <label for="logbook_odometer_initial_km" class="block text-[11px] text-slate-500 dark:text-slate-400">Anfangs-Kilometerstand (km) *</label>
                <input type="text" id="logbook_odometer_initial_km" name="logbook_odometer_initial_km" inputmode="numeric" placeholder="z. B. 48250"
                       value="<?= e($value('logbook_odometer_initial_km', $vehicle->logbookOdometerInitialKm !== null ? (string) $vehicle->logbookOdometerInitialKm : null)) ?>"
                       class="<?= $input ?> <?= $border('odometerInitialKm') ?>">
                <?php if (isset($errors['odometerInitialKm'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['odometerInitialKm']) ?></p><?php endif; ?>
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Stand, mit dem die erste Fahrt beginnen muss.</p>
            </div>
            <div>
                <label for="logbook_odometer_tolerance_km" class="block text-[11px] text-slate-500 dark:text-slate-400">Toleranz (km)</label>
                <input type="text" id="logbook_odometer_tolerance_km" name="logbook_odometer_tolerance_km" inputmode="numeric" placeholder="0"
                       value="<?= e($value('logbook_odometer_tolerance_km', (string) $vehicle->logbookOdometerToleranceKm)) ?>"
                       class="<?= $input ?> <?= $border('odometerToleranceKm') ?>">
                <?php if (isset($errors['odometerToleranceKm'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['odometerToleranceKm']) ?></p><?php endif; ?>
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Erlaubte Abweichung vom zuletzt erfassten Stand, 0 = exakt.</p>
            </div>
        </div>
    </section>

    <div class="form-actions flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
        <a href="/fahrzeuge" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>

<script>
    // Shows the dependent options only while their switch is on; the server
    // keeps the values either way (see SaveVehicleLogbookSettingsHandler).
    (function () {
        var form = document.currentScript.previousElementSibling;
        [['[data-anonymous-toggle]', '[data-anonymous-options]'], ['[data-odometer-toggle]', '[data-odometer-options]']].forEach(function (pair) {
            var box = form.querySelector(pair[0]);
            var target = form.querySelector(pair[1]);
            if (!box || !target) {
                return;
            }
            var update = function () { target.classList.toggle('hidden', !box.checked); };
            box.addEventListener('change', update);
            update();
        });
    })();
</script>
