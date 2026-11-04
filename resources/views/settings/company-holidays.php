<?php
/** @var \App\Domain\CompanyHoliday\CompanyHoliday[] $holidays */

use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$input = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$btn = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$label = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

$fields = function (?\App\Domain\CompanyHoliday\CompanyHoliday $holiday) use ($input, $label): void {
    $p = $holiday?->id ?? 'neu';
    ?>
    <div>
        <label class="<?= $label ?>" for="start-<?= e($p) ?>">Von *</label>
        <input type="date" id="start-<?= e($p) ?>" name="start_date" required value="<?= e($holiday?->startDate->format('Y-m-d') ?? '') ?>" class="<?= $input ?>" style="min-width:10rem">
    </div>
    <div>
        <label class="<?= $label ?>" for="end-<?= e($p) ?>">Bis (einschließlich) *</label>
        <input type="date" id="end-<?= e($p) ?>" name="end_date" required value="<?= e($holiday?->endDate->format('Y-m-d') ?? '') ?>" class="<?= $input ?>" style="min-width:10rem">
    </div>
    <div class="min-w-0 flex-1" style="min-width:12rem">
        <label class="<?= $label ?>" for="note-<?= e($p) ?>">Bezeichnung</label>
        <input type="text" id="note-<?= e($p) ?>" name="note" maxlength="255" value="<?= e($holiday?->note ?? '') ?>" placeholder="z. B. Weihnachten" class="<?= $input ?>">
    </div>
    <?php
};
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Betriebsurlaub</h1>
    <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
        Während eines Betriebsurlaubs gibt es keine Ausgabe und keine Rücknahme. Veranstaltungen, die in einen Betriebsurlaub fallen,
        sind nur mit Abholung vor und Rückgabe nach dem Urlaub möglich; beim Anlegen erscheint eine Rückfrage.
    </p>
</div>

<div class="max-w-4xl space-y-4">
    <?php if ($holidays === []): ?>
        <p class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">Noch kein Betriebsurlaub hinterlegt.</p>
    <?php endif; ?>
    <?php foreach ($holidays as $holiday): ?>
        <?php $past = $holiday->endDate->format('Y-m-d') < $today; ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900" <?= $past ? 'style="opacity:.65"' : '' ?>>
            <div class="flex flex-wrap items-end gap-3">
                <form method="post" action="/einstellungen/betriebsurlaub/<?= e($holiday->id) ?>" class="flex min-w-0 flex-1 flex-wrap items-end gap-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <?php $fields($holiday); ?>
                    <button type="submit" class="<?= $btn ?>">Speichern</button>
                </form>
                <form method="post" action="/einstellungen/betriebsurlaub/<?= e($holiday->id) ?>/loeschen" data-confirm="Betriebsurlaub <?= e($holiday->label()) ?> löschen?" data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950/40">Löschen</button>
                </form>
            </div>
            <?php if ($past): ?><p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Vergangen</p><?php endif; ?>
        </div>
    <?php endforeach; ?>

    <form method="post" action="/einstellungen/betriebsurlaub" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <h2 class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-200">Betriebsurlaub hinzufügen</h2>
        <div class="flex flex-wrap items-end gap-3">
            <?php $fields(null); ?>
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Hinzufügen</button>
        </div>
    </form>
</div>
