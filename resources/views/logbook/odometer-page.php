<?php
/**
 * Odometer check before recording a trip, in the normal layout (migration
 * 185). The QR code uses the mobile page logbook/odometer.php.
 *
 * @var \App\Domain\Vehicle\Vehicle $vehicle
 * @var string $action
 * @var string|null $gapAction
 * @var string|null $error
 * @var array{expected: int, reading: int}|null $gap
 * @var \DateTimeImmutable|null $lockedUntil
 */

use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

require __DIR__ . '/../rentals/series/_styles.php';
$km = static fn (int $v): string => number_format($v, 0, ',', '.');
?>
<div class="mb-6">
    <a href="/fahrtenbuch" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Fahrtenbuch</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Fahrt erfassen</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($vehicle->displayName()) ?></p>
</div>

<?php if ($lockedUntil !== null): ?>
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">Zu viele falsche Eingaben. Bitte versuche es ab <?= e($lockedUntil->format('H:i')) ?> Uhr erneut oder wende dich an einen Fahrtenbuch-Verwalter.</div>
<?php elseif ($error !== null): ?>
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid gap-4 lg:grid-cols-2">
    <form method="post" action="<?= e($action) ?>" autocomplete="off" class="<?= $card ?> space-y-3">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Aktueller Kilometerstand</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Für dieses Fahrzeug wird der Kilometerstand geprüft. Bitte lies ihn am Tacho ab; erst wenn er zum zuletzt erfassten Stand passt, kannst du die Fahrt erfassen.</p>
        <label class="<?= $label ?>">Kilometerstand jetzt (km)
            <input type="text" name="odometer" inputmode="numeric" required autofocus <?= $lockedUntil !== null ? 'disabled' : '' ?> placeholder="z. B. 48250"
                   class="mt-1 block w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900">
        </label>
        <button type="submit" class="<?= $primaryButton ?> px-4 py-2" <?= $lockedUntil !== null ? 'disabled' : '' ?>>Weiter</button>
    </form>

    <?php if ($gap !== null && $gapAction !== null): ?>
        <form method="post" action="<?= e($gapAction) ?>" class="<?= $card ?> space-y-3">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="odometer" value="<?= e((string) $gap['reading']) ?>">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Fehlen Fahrten?</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Zuletzt erfasst sind <?= e($km($gap['expected'])) ?> km, du hast <?= e($km($gap['reading'])) ?> km abgelesen. Wenn dazwischen Fahrten nicht eingetragen wurden, kannst du die Lücke melden (<?= e($km($gap['reading'] - $gap['expected'])) ?> km). Ein Fahrtenbuch-Verwalter klärt sie anschließend; danach kannst du deine Fahrt erfassen.</p>
            <button type="submit" class="<?= $secondaryButton ?>">Lücke melden und weiter</button>
        </form>
    <?php endif; ?>
</div>
