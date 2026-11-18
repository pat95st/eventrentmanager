<?php
/**
 * Form for a stop or a waypoint of a tour.
 *
 * @var \App\Application\Tour\Query\TourOverview $tour
 * @var \App\Domain\Tour\TourStop|null $stop
 * @var bool $waypoint
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */

use App\Domain\Tour\WaypointAddressType;
use App\Domain\Tour\WaypointPurpose;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\renderLiveSearchField;

require __DIR__ . '/../series/_styles.php';
$rental = $tour->rental;
$v = static fn (string $key): string => $old[$key] ?? '';
$action = $stop !== null ? '/tour-stops/' . $stop->id : '/veranstaltungen/' . $rental->id . '/tour/stops';
$kind = $stop?->kind->value ?? ($old['kind'] ?? ($waypoint ? 'waypoint' : 'stop'));
$endpoint = in_array($kind, ['start', 'end'], true);
$heading = match (true) {
    $kind === 'start' => 'Start der Route',
    $kind === 'end' => 'Ziel der Route',
    $waypoint => $stop !== null ? 'Zwischenstopp bearbeiten' : 'Zwischenstopp hinzufügen',
    default => $stop !== null ? 'Tourstop bearbeiten' : 'Tourstop hinzufügen',
};
?>
<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>/tour" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zur Tour</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($heading) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($rental->eventName) ?></p>
</div>

<?php require __DIR__ . '/../series/_errors.php'; ?>

<form method="post" action="<?= e($action) ?>" class="<?= $card ?> space-y-4">
    <?= $csrf ?>
    <input type="hidden" name="kind" value="<?= e($kind) ?>">
    <?php if ($endpoint): ?>
        <p class="text-xs text-slate-500">Ohne eigene Angabe <?= $kind === 'start' ? 'beginnt die Route am Ausgabelager' : 'endet die Route am Rückgabelager' ?> der Veranstaltung.</p>
    <?php endif; ?>
    <?php if (!$waypoint): ?>
        <div>
            <label class="<?= $label ?>" for="location">Ort</label>
            <div class="mt-1">
                <?php renderLiveSearchField('location', '/veranstaltungsorte/suche', null, $v('location'), 'Veranstaltungsort oder Freitext', $errors['location'] ?? null, 'freetext', true); ?>
            </div>
            <p class="mt-1 text-xs text-slate-500">Stimmt der Name mit einem gespeicherten Veranstaltungsort überein, wird dieser verknüpft.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="<?= $label ?>" for="stop-setup">Aufbau ab</label><input id="stop-setup" type="datetime-local" name="setup_at" value="<?= e($v('setup_at')) ?>" class="<?= $input ?>"></div>
            <div><label class="<?= $label ?>" for="stop-start">Beginn der Veranstaltung *</label><input id="stop-start" type="datetime-local" name="event_start_at" value="<?= e($v('event_start_at')) ?>" required class="<?= $input ?>"></div>
            <div><label class="<?= $label ?>" for="stop-end">Ende der Veranstaltung</label><input id="stop-end" type="datetime-local" name="event_end_at" value="<?= e($v('event_end_at')) ?>" class="<?= $input ?>"></div>
            <div><label class="<?= $label ?>" for="stop-teardown">Abbau bis</label><input id="stop-teardown" type="datetime-local" name="teardown_end_at" value="<?= e($v('teardown_end_at')) ?>" class="<?= $input ?>"></div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="<?= $label ?>" for="stop-contact">Ansprechpartner vor Ort</label><input id="stop-contact" type="text" name="contact_name" maxlength="150" value="<?= e($v('contact_name')) ?>" class="<?= $input ?>"></div>
            <div><label class="<?= $label ?>" for="stop-phone">Telefon</label><input id="stop-phone" type="text" name="contact_phone" maxlength="50" value="<?= e($v('contact_phone')) ?>" class="<?= $input ?>"></div>
        </div>
    <?php else: ?>
        <div class="grid gap-3 sm:grid-cols-2"<?= $endpoint ? ' hidden' : '' ?>>
            <div>
                <label class="<?= $label ?>" for="wp-purpose">Zweck</label>
                <select id="wp-purpose" name="purpose" class="<?= $input ?>">
                    <?php foreach (WaypointPurpose::cases() as $purpose): ?>
                        <option value="<?= e($purpose->value) ?>"<?= $v('purpose') === $purpose->value ? ' selected' : '' ?>><?= e($purpose->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="<?= $label ?>" for="wp-after">Zwischen</label>
                <select id="wp-after" name="after_stop_id" class="<?= $input ?>">
                    <option value="">Tourstart und Tourstop 1</option>
                    <?php foreach ($tour->stops() as $i => $entry): ?>
                        <option value="<?= e($entry->stop->id) ?>"<?= $v('after_stop_id') === $entry->stop->id ? ' selected' : '' ?>>Tourstop <?= (int) $entry->number ?> (<?= e($entry->stop->title()) ?>) und <?= isset($tour->stops()[$i + 1]) ? 'Tourstop ' . ((int) $entry->number + 1) : 'Tourende' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <fieldset data-address-types>
            <legend class="<?= $label ?>">Adresse</legend>
            <div class="mt-1 flex flex-wrap gap-3 text-sm">
                <?php foreach (WaypointAddressType::cases() as $type): ?>
                    <label class="flex items-center gap-1"><input type="radio" name="address_type" value="<?= e($type->value) ?>"<?= $v('address_type') === $type->value ? ' checked' : '' ?>> <?= e($type->label()) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div data-address-show="warehouse">
            <label class="<?= $label ?>" for="wp-warehouse">Lager</label>
            <select id="wp-warehouse" name="warehouse_id" class="<?= $input ?>">
                <option value="">Bitte wählen …</option>
                <?php foreach ($tour->warehouses as $id => $name): ?><option value="<?= e((string) $id) ?>"<?= $v('warehouse_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
        </div>
        <p data-address-show="company" class="text-xs text-slate-500">Die Adresse aus den Firmendaten wird übernommen.</p>
        <div data-address-show="saved">
            <label class="<?= $label ?>" for="wp-saved">Gespeicherte Adresse</label>
            <select id="wp-saved" name="saved_address_id" class="<?= $input ?>">
                <option value="">Bitte wählen …</option>
                <?php foreach ($tour->savedAddresses as $address): ?><option value="<?= e($address->id) ?>"<?= $v('saved_address_id') === $address->id ? ' selected' : '' ?>><?= e($address->label . ' – ' . $address->postalCode . ' ' . $address->city) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div data-address-show="custom" class="space-y-3">
            <div><label class="<?= $label ?>" for="wp-label">Bezeichnung (z. B. Hotelname)</label><input id="wp-label" type="text" name="label" maxlength="255" value="<?= e($v('label')) ?>" class="<?= $input ?>"></div>
            <div><label class="<?= $label ?>" for="wp-street">Straße</label><input id="wp-street" type="text" name="street" value="<?= e($v('street')) ?>" class="<?= $input ?>"></div>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="<?= $label ?>" for="wp-zip">PLZ</label><input id="wp-zip" type="text" name="postal_code" value="<?= e($v('postal_code')) ?>" class="<?= $input ?>"></div>
                <div class="col-span-2"><label class="<?= $label ?>" for="wp-city">Ort</label><input id="wp-city" type="text" name="city" value="<?= e($v('city')) ?>" class="<?= $input ?>"></div>
            </div>
            <div><label class="<?= $label ?>" for="wp-country">Land</label><input id="wp-country" type="text" name="country" value="<?= e($v('country')) ?>" class="<?= $input ?>"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember_address" value="1"<?= $v('remember_address') === '1' ? ' checked' : '' ?>> Als Adresse merken (für weitere Touren auswählbar)</label>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="<?= $label ?>" for="wp-arrive">Ankunft (optional)</label><input id="wp-arrive" type="datetime-local" name="arrive_at" value="<?= e($v('arrive_at')) ?>" class="<?= $input ?>"></div>
            <div><label class="<?= $label ?>" for="wp-depart">Abfahrt (optional)</label><input id="wp-depart" type="datetime-local" name="depart_at" value="<?= e($v('depart_at')) ?>" class="<?= $input ?>"></div>
        </div>
    <?php endif; ?>
    <div>
        <label class="<?= $label ?>" for="stop-note">Notiz</label>
        <textarea id="stop-note" name="note" rows="3" class="<?= $input ?>"><?= e($v('note')) ?></textarea>
    </div>
    <div class="flex justify-end gap-2">
        <a href="/veranstaltungen/<?= e($rental->id) ?>/tour" class="<?= $secondaryButton ?>">Abbrechen</a>
        <button type="submit" class="<?= $primaryButton ?>">Speichern</button>
    </div>
</form>
<?php if ($waypoint): ?>
<script>
(() => {
    const radios = document.querySelectorAll('[data-address-types] input[type=radio]');
    const update = () => {
        const value = (Array.from(radios).find((r) => r.checked) || {}).value;
        document.querySelectorAll('[data-address-show]').forEach((el) => { el.hidden = el.dataset.addressShow !== value; });
    };
    radios.forEach((r) => r.addEventListener('change', update));
    update();
})();
</script>
<?php endif; ?>
