<?php
/** @var \App\Domain\Venue\Venue|null $venue */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var \App\Application\Rental\Query\VenueRentalEntry[] $upcomingRentals */
/** @var \App\Application\Rental\Query\VenueRentalEntry[] $pastRentals */
/** @var \App\Domain\Venue\PowerConnection[] $powerConnections */
/** @var bool $needsDistributorWithRcd */
/** @var \App\Domain\Warehouse\Warehouse[] $warehouses */
/** @var string $companyAddress */

use App\Domain\Venue\CircuitBreakerRating;
use App\Domain\Venue\PowerConnectionType;
use App\Domain\Venue\RcdTripCurrent;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/veranstaltungsorte" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('venues.form.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $venue !== null ? e(t('venues.edit')) : e(t('venues.new')) ?></h1>
    </div>
    <?php if ($venue !== null): ?>
        <details class="relative">
            <summary class="cursor-pointer list-none rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <?= e(t('rentals.logistics.route_button')) ?>
            </summary>
            <div class="absolute right-0 z-10 mt-2 w-56 rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <?php if ($companyAddress !== ''): ?>
                    <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($companyAddress) ?>&destination=<?= urlencode($venue->fullAddress()) ?>&travelmode=driving" target="_blank" rel="noopener" class="block px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('rentals.logistics.route_from_company')) ?></a>
                <?php endif; ?>
                <?php foreach ($warehouses as $warehouse): ?>
                    <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($warehouse->fullAddress()) ?>&destination=<?= urlencode($venue->fullAddress()) ?>&travelmode=driving" target="_blank" rel="noopener" class="block px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e($warehouse->name) ?></a>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/veranstaltungsorte/' . e($editingId) : '/veranstaltungsorte' ?>"
    class="max-w-3xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.form.name')) ?></label>
            <input type="text" id="name" name="name" required
                   value="<?= e($val('name', $venue?->name)) ?>"
                   placeholder="z. B. Stadthalle Musterstadt"
                   class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="street" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.form.street')) ?></label>
            <input type="text" id="street" name="street" required
                   value="<?= e($val('street', $venue?->street)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['street']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['street'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['street']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="postal_code" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.form.postal_code')) ?></label>
            <input type="text" id="postal_code" name="postal_code" required
                   value="<?= e($val('postal_code', $venue?->postalCode)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['postalCode']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['postalCode'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['postalCode']) ?></p><?php endif; ?>
        </div>
        <div class="col-span-2">
            <label for="city" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.form.city')) ?></label>
            <input type="text" id="city" name="city" required
                   value="<?= e($val('city', $venue?->city)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['city']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['city'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['city']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="country" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.form.country')) ?></label>
        <input type="text" id="country" name="country"
               value="<?= e($val('country', $venue?->country)) ?>"
               placeholder="Deutschland"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.form.country_hint')) ?></p>
    </div>

    <div>
        <label for="notes" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.form.notes')) ?></label>
        <textarea id="notes" name="notes" rows="3"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
        ><?= e($val('notes', $venue?->notes)) ?></textarea>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="caretaker_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.form.caretaker_name')) ?></label>
            <input type="text" id="caretaker_name" name="caretaker_name"
                   value="<?= e($val('caretaker_name', $venue?->caretakerName)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
            <label for="caretaker_phone" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.form.caretaker_phone')) ?></label>
            <input type="text" id="caretaker_phone" name="caretaker_phone"
                   value="<?= e($val('caretaker_phone', $venue?->caretakerPhone)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <p class="sm:col-span-2 -mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.form.caretaker_hint')) ?></p>
    </div>

    <div class="rounded-md bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">
        <?= e(t('venues.form.coordinates_hint')) ?>
        <?php if ($venue !== null): ?>
            <br><?= e(t('venues.form.status_label')) ?>:
            <?php if ($venue->hasCoordinates()): ?>
                <span class="font-medium text-emerald-700 dark:text-emerald-400"><?= e(t('venues.form.coordinates_present')) ?></span>
            <?php else: ?>
                <span class="font-medium text-amber-700 dark:text-amber-400"><?= e(t('venues.form.coordinates_missing')) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="/veranstaltungsorte" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<?php if ($venue !== null): ?>
    <div class="mt-6 max-w-3xl rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('venues.power.title')) ?></h2>

        <?php if ($needsDistributorWithRcd): ?>
            <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                <?= e(t('venues.power.banner')) ?>
            </div>
        <?php endif; ?>

        <?php if ($powerConnections === []): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('venues.power.empty')) ?></p>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($powerConnections as $connection): ?>
                    <?php
                        $breakerTooLarge = $connection->breakerExceedsRatedCurrent();
                        $needsDistributor = $connection->needsOwnDistributorWithRcd();
                        $ratedAmps = $connection->type->ratedCurrentAmps();
                    ?>
                    <div class="py-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="text-sm">
                                <span class="font-medium text-slate-800 dark:text-slate-200"><?= e($connection->type->value) ?></span>
                                <span class="text-slate-500 dark:text-slate-400">
                                    · <?= e(t('venues.power.breaker_rating')) ?>: <?= e($connection->breakerRating->value) ?>
                                    · <?= e(t('venues.power.rcd_trip_current')) ?>: <?= e($connection->rcdTripCurrent->value) ?>
                                    <?php if ($connection->location !== null && $connection->location !== ''): ?>
                                        · <?= e(t('venues.power.location')) ?>: <?= e($connection->location) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="flex shrink-0 items-center gap-3 text-xs">
                                <details class="inline-block">
                                    <summary class="cursor-pointer font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('venues.power.edit')) ?></summary>
                                    <form method="post" action="/veranstaltungsorte/<?= e($venue->id) ?>/stromanschluesse/<?= e($connection->id) ?>" class="mt-2 space-y-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.power.type')) ?></label>
                                            <select name="type" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                                                <?php foreach (PowerConnectionType::all() as $typeOption): ?>
                                                    <option value="<?= e($typeOption->value) ?>" <?= $typeOption === $connection->type ? 'selected' : '' ?>><?= e($typeOption->value) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.power.breaker_rating')) ?></label>
                                            <select name="breaker_rating" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                                                <?php foreach (CircuitBreakerRating::all() as $breakerOption): ?>
                                                    <option value="<?= e($breakerOption->value) ?>" <?= $breakerOption === $connection->breakerRating ? 'selected' : '' ?>><?= e($breakerOption->value) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.power.rcd_trip_current')) ?></label>
                                            <select name="rcd_trip_current" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                                                <?php foreach (RcdTripCurrent::all() as $rcdOption): ?>
                                                    <option value="<?= e($rcdOption->value) ?>" <?= $rcdOption === $connection->rcdTripCurrent ? 'selected' : '' ?>><?= e($rcdOption->value) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.power.location')) ?></label>
                                            <input type="text" name="location" value="<?= e($connection->location ?? '') ?>" placeholder="<?= e(t('venues.power.location_placeholder')) ?>"
                                                   class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                                        </div>
                                        <button type="submit" class="rounded-md bg-brand-600 px-3 py-1 text-xs font-medium text-white hover:bg-brand-700"><?= e(t('venues.power.save')) ?></button>
                                    </form>
                                </details>
                                <form method="post" action="/veranstaltungsorte/<?= e($venue->id) ?>/stromanschluesse/<?= e($connection->id) ?>/loeschen" data-confirm="<?= e(t('venues.power.confirm_delete')) ?>" data-confirm-style="danger">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('venues.power.delete')) ?></button>
                                </form>
                            </div>
                        </div>
                        <?php if ($breakerTooLarge): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                <?= e(t('venues.power.warning_breaker_too_large', ['breaker' => $connection->breakerRating->value, 'rated' => (string) $ratedAmps])) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($needsDistributor): ?>
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                                <?= e(t('venues.power.warning_distributor_needed')) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/veranstaltungsorte/<?= e($venue->id) ?>/stromanschluesse" class="mt-4 grid grid-cols-1 gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700 sm:grid-cols-2">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.power.type')) ?></label>
                <select name="type" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700">
                    <?php foreach (PowerConnectionType::all() as $typeOption): ?>
                        <option value="<?= e($typeOption->value) ?>"><?= e($typeOption->value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.power.breaker_rating')) ?></label>
                <select name="breaker_rating" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700">
                    <?php foreach (CircuitBreakerRating::all() as $breakerOption): ?>
                        <option value="<?= e($breakerOption->value) ?>"><?= e($breakerOption->value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.power.rcd_trip_current')) ?></label>
                <select name="rcd_trip_current" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700">
                    <?php foreach (RcdTripCurrent::all() as $rcdOption): ?>
                        <option value="<?= e($rcdOption->value) ?>"><?= e($rcdOption->value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.power.location')) ?></label>
                <input type="text" name="location" placeholder="<?= e(t('venues.power.location_placeholder')) ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-brand-700"><?= e(t('venues.power.add')) ?></button>
            </div>
        </form>
    </div>

    <div class="mt-6 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('venues.form.upcoming_events_title')) ?></h2>
            <?php if ($upcomingRentals === []): ?>
                <p class="text-slate-500 dark:text-slate-400"><?= e(t('articles.show.upcoming_rentals_none')) ?></p>
            <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach (array_slice($upcomingRentals, 0, 6) as $entry): ?>
                        <a href="/veranstaltungen/<?= e($entry->rental->id) ?>" class="block py-2 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <p class="font-medium text-slate-800 dark:text-slate-200"><?= e($entry->rental->eventName) ?></p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                <?= e($entry->rental->startDate->format('d.m.Y')) ?> – <?= e($entry->rental->endDate->format('d.m.Y')) ?>
                                · <?= e($entry->customer->displayName()) ?>
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($upcomingRentals) > 6): ?>
                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.form.more', ['count' => (string) (count($upcomingRentals) - 6)])) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('venues.form.past_events_title')) ?></h2>
            <?php if ($pastRentals === []): ?>
                <p class="text-slate-500 dark:text-slate-400"><?= e(t('articles.show.past_rentals_none')) ?></p>
            <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach (array_slice($pastRentals, 0, 6) as $entry): ?>
                        <a href="/veranstaltungen/<?= e($entry->rental->id) ?>" class="block py-2 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <p class="font-medium text-slate-800 dark:text-slate-200"><?= e($entry->rental->eventName) ?></p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                <?= e($entry->rental->startDate->format('d.m.Y')) ?> – <?= e($entry->rental->endDate->format('d.m.Y')) ?>
                                · <?= e($entry->customer->displayName()) ?>
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($pastRentals) > 6): ?>
                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.form.more', ['count' => (string) (count($pastRentals) - 6)])) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
