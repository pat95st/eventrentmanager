<?php
/**
 * @var \App\Domain\Calendar\Calendar[] $calendars
 * @var \App\Domain\Category\Category[] $categories
 * @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers
 * @var \App\Domain\Warehouse\Warehouse[] $warehouses
 * @var \App\Domain\Venue\Venue[] $venues
 * @var \App\Domain\User\User[] $users
 * @var array<string, string> $errors
 * @var string|null $editCalendarId
 * @var string|null $accessCalendarId
 * @var string[] $accessUserIds
 * @var \App\Domain\Settings\CalDavSigningSettings $signingSettings
 * @var array<string, string> $signingErrors
 */

use App\Domain\Calendar\CalendarKind;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$editCalendar = null;
$accessCalendar = null;
foreach ($calendars as $calendar) {
    if ($calendar->id === $editCalendarId) {
        $editCalendar = $calendar;
    }
    if ($calendar->id === $accessCalendarId) {
        $accessCalendar = $calendar;
    }
}

$filterLabel = function (\App\Domain\Calendar\Calendar $calendar) use ($categories, $manufacturers, $warehouses, $venues): string {
    if ($calendar->kind === CalendarKind::Event) {
        return t('settings.calendars.filter.event');
    }

    $parts = [];
    if ($calendar->categoryId !== null) {
        foreach ($categories as $category) {
            if ($category->id === $calendar->categoryId) {
                $parts[] = t('settings.calendars.filter.category') . ': ' . $category->name;
            }
        }
    }
    if ($calendar->manufacturerId !== null) {
        foreach ($manufacturers as $manufacturer) {
            if ($manufacturer->id === $calendar->manufacturerId) {
                $parts[] = t('settings.calendars.filter.manufacturer') . ': ' . $manufacturer->name;
            }
        }
    }
    if ($calendar->warehouseId !== null) {
        foreach ($warehouses as $warehouse) {
            if ($warehouse->id === $calendar->warehouseId) {
                $parts[] = t('settings.calendars.filter.warehouse') . ': ' . $warehouse->name;
            }
        }
    }
    if ($calendar->venueId !== null) {
        foreach ($venues as $venue) {
            if ($venue->id === $calendar->venueId) {
                $parts[] = t('settings.calendars.filter.venue') . ': ' . $venue->name;
            }
        }
    }

    return $parts === [] ? '–' : implode(' · ', $parts);
};
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.calendars.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.calendars.subtitle')) ?></p>
</div>

<div class="space-y-6">
    <!-- Kalenderliste -->
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2.5"><?= e(t('settings.calendars.column.name')) ?></th>
                    <th class="px-4 py-2.5"><?= e(t('settings.calendars.column.filter')) ?></th>
                    <th class="px-4 py-2.5"><?= e(t('settings.calendars.column.status')) ?></th>
                    <th class="px-4 py-2.5 text-right"><?= e(t('settings.calendars.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($calendars as $calendar): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-800 dark:text-slate-200"><?= e($calendar->name) ?></span>
                            <?php if ($calendar->kind === CalendarKind::Event): ?>
                                <span class="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400"><?= e(t('settings.calendars.built_in_badge')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($filterLabel($calendar)) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($calendar->enabled): ?>
                                <span class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950/40 dark:text-brand-400"><?= e(t('settings.calendars.status.enabled')) ?></span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e(t('settings.calendars.status.disabled')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a href="/einstellungen/kalender?bearbeiten=<?= e($calendar->id) ?>" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400"><?= e(t('settings.calendars.edit')) ?></a>
                                <a href="/einstellungen/kalender?zugriff=<?= e($calendar->id) ?>" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400"><?= e(t('settings.calendars.manage_access')) ?></a>
                                <?php if ($calendar->enabled): ?>
                                    <form method="post" action="/einstellungen/kalender/<?= e($calendar->id) ?>/deaktivieren">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <button type="submit" class="text-xs font-medium text-slate-500 hover:underline dark:text-slate-400"><?= e(t('settings.calendars.disable')) ?></button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="/einstellungen/kalender/<?= e($calendar->id) ?>/aktivieren">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <button type="submit" class="text-xs font-medium text-slate-500 hover:underline dark:text-slate-400"><?= e(t('settings.calendars.enable')) ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($calendar->kind === CalendarKind::Rental): ?>
                                    <form method="post" action="/einstellungen/kalender/<?= e($calendar->id) ?>/loeschen" data-confirm="<?= e(t('settings.calendars.confirm_delete')) ?>" data-confirm-style="danger">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('settings.calendars.delete')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Bearbeiten -->
    <?php if ($editCalendar !== null): ?>
        <div class="rounded-lg border border-brand-200 bg-brand-50/40 p-4 dark:border-brand-800 dark:bg-brand-950/20">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.calendars.edit_title', ['name' => $editCalendar->name])) ?></h2>
            <form method="post" action="/einstellungen/kalender/<?= e($editCalendar->id) ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.calendars.field.name')) ?></label>
                    <input type="text" name="name" value="<?= e($editCalendar->name) ?>" required
                           class="mt-1 w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <?php if ($editCalendar->kind === CalendarKind::Rental): ?>
                    <?php include __DIR__ . '/_calendar_filter_fields.php'; ?>
                <?php endif; ?>

                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('settings.calendars.save')) ?></button>
                <a href="/einstellungen/kalender" class="ml-2 text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('settings.calendars.cancel')) ?></a>
            </form>
        </div>
    <?php endif; ?>

    <!-- Zugriffsrechte -->
    <?php if ($accessCalendar !== null): ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.calendars.access.title', ['name' => $accessCalendar->name])) ?></h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.calendars.access.hint')) ?></p>
            <form method="post" action="/einstellungen/kalender/<?= e($accessCalendar->id) ?>/zugriff" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <?php foreach ($users as $accessUser): ?>
                        <?php if (!$accessUser->isActive) continue; ?>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="checkbox" name="user_ids[]" value="<?= e($accessUser->id) ?>"
                                   <?= (in_array($accessUser->id, $accessUserIds, true) || $accessUser->isAdministrator()) ? 'checked' : '' ?>
                                   <?= $accessUser->isAdministrator() ? 'disabled' : '' ?>
                                   class="rounded border-slate-300">
                            <?= e(trim(($accessUser->firstName ?? '') . ' ' . ($accessUser->lastName ?? '')) !== '' ? trim($accessUser->firstName . ' ' . $accessUser->lastName) : $accessUser->email) ?>
                            <?php if ($accessUser->isAdministrator()): ?>
                                <span class="text-xs text-slate-400 dark:text-slate-500">(<?= e(t('settings.calendars.access.admin_note')) ?>)</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('settings.calendars.save')) ?></button>
                <a href="/einstellungen/kalender" class="ml-2 text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('settings.calendars.cancel')) ?></a>
            </form>
        </div>
    <?php endif; ?>

    <!-- Neuen Vermietungskalender anlegen -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.calendars.create.title')) ?></h2>
        <form method="post" action="/einstellungen/kalender" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.calendars.field.name')) ?></label>
                <input type="text" name="name" value=""
                       class="mt-1 w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <?php if (!isset($errors['id']) && isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <?php $editCalendar = null; include __DIR__ . '/_calendar_filter_fields.php'; ?>
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('settings.calendars.create.submit')) ?></button>
        </form>
    </div>

    <!-- .mobileconfig-Signatur -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.calendars.signing.title')) ?></h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.calendars.signing.hint')) ?></p>

        <div class="mb-3 text-sm">
            <?php if ($signingSettings->certificateFilename === null): ?>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e(t('settings.calendars.signing.status.none')) ?></span>
            <?php elseif ($signingSettings->isSelfSigned): ?>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-400"><?= e(t('settings.calendars.signing.status.self_signed')) ?></span>
            <?php else: ?>
                <span class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950/40 dark:text-brand-400"><?= e(t('settings.calendars.signing.status.uploaded')) ?></span>
            <?php endif; ?>
            <?php if ($signingSettings->updatedAt !== null): ?>
                <span class="ml-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.calendars.signing.status.since', ['date' => $signingSettings->updatedAt->format('d.m.Y H:i')])) ?></span>
            <?php endif; ?>
        </div>

        <form method="post" action="/einstellungen/kalender/signatur" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.calendars.signing.field.certificate')) ?></label>
                <input type="file" name="certificate" accept=".pem,.crt,.cer"
                       class="mt-1 w-full max-w-md text-sm text-slate-700 dark:text-slate-300">
                <?php if (isset($signingErrors['certificate'])): ?><p class="mt-1 text-xs text-red-600"><?= e($signingErrors['certificate']) ?></p><?php endif; ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.calendars.signing.field.private_key')) ?></label>
                <input type="file" name="private_key" accept=".pem,.key"
                       class="mt-1 w-full max-w-md text-sm text-slate-700 dark:text-slate-300">
                <?php if (isset($signingErrors['private_key'])): ?><p class="mt-1 text-xs text-red-600"><?= e($signingErrors['private_key']) ?></p><?php endif; ?>
            </div>
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('settings.calendars.signing.upload')) ?></button>
        </form>

        <?php if ($signingSettings->certificateFilename !== null && !$signingSettings->isSelfSigned): ?>
            <form method="post" action="/einstellungen/kalender/signatur/loeschen" class="mt-3" data-confirm="<?= e(t('settings.calendars.signing.confirm_delete')) ?>" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('settings.calendars.signing.delete')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
