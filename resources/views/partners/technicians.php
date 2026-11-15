<?php
/**
 * @var \App\Application\Federation\Query\PartnerTechnicianOptions $options
 * @var \App\Domain\Rental\Rental|null $rental
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$partner = $options->partner;
$input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$labelCls = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$selectedTrades = is_array($old['trades'] ?? null) ? $old['trades'] : [];
$errorText = $errors['partner'] ?? $errors['startDate'] ?? $errors['endDate'] ?? $errors['eventName'] ?? $errors['technicianCount'] ?? null;
?>
<div class="mb-6">
    <a href="/partner" class="text-xs text-brand-700 hover:underline dark:text-brand-400">← Partner</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Techniker bei <?= e($partner->name) ?> anfragen</h1>
    <?php if ($rental !== null): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400">Für Veranstaltung <a class="underline" href="/veranstaltungen/<?= e($rental->id) ?>"><?= e($rental->eventName) ?></a>.</p>
    <?php endif; ?>
</div>

<?php if ($options->error !== null): ?>
    <p class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800">Die Techniker konnten nicht geladen werden: <?= e($options->error) ?></p>
<?php elseif (!$options->allowed): ?>
    <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"><?= e($partner->name) ?> nimmt keine Techniker-Anfragen an.</p>
<?php else: ?>
    <?php if ($errorText !== null): ?>
        <p class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800"><?= e($errorText) ?></p>
    <?php endif; ?>
    <form method="post" action="/partner/<?= e($partner->id) ?>/techniker" class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <?php if ($rental !== null): ?><input type="hidden" name="rental_id" value="<?= e($rental->id) ?>"><?php endif; ?>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="<?= $labelCls ?>">Veranstaltung
                <input type="text" name="event_name" maxlength="200" required value="<?= e((string) ($old['event_name'] ?? $rental?->eventName ?? '')) ?>" class="<?= $input ?>">
            </label>
            <label class="<?= $labelCls ?>">Ort
                <input type="text" name="event_location" maxlength="255" value="<?= e((string) ($old['event_location'] ?? $rental?->eventLocation ?? '')) ?>" class="<?= $input ?>">
            </label>
            <label class="<?= $labelCls ?>">Von
                <input type="date" name="from" required value="<?= e((string) ($old['from'] ?? $rental?->startDate->format('Y-m-d') ?? '')) ?>" class="<?= $input ?>">
            </label>
            <label class="<?= $labelCls ?>">Bis
                <input type="date" name="to" required value="<?= e((string) ($old['to'] ?? $rental?->endDate->format('Y-m-d') ?? '')) ?>" class="<?= $input ?>">
            </label>
            <label class="<?= $labelCls ?>">Anzahl Techniker
                <input type="number" name="technician_count" min="1" max="50" value="<?= e((string) ($old['technician_count'] ?? '1')) ?>" class="<?= $input ?>">
            </label>
            <label class="<?= $labelCls ?>">Wunsch-Techniker (optional)
                <select name="preferred_technician" class="<?= $input ?>">
                    <option value="">Egal</option>
                    <?php foreach ($options->technicians as $technician): ?>
                        <?php $value = $technician['id'] . '|' . $technician['name']; ?>
                        <option value="<?= e($value) ?>" <?= ($old['preferred_technician'] ?? '') === $value ? 'selected' : '' ?>><?= e($technician['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <?php if ($options->trades !== []): ?>
            <fieldset>
                <legend class="<?= $labelCls ?>">Gewerke</legend>
                <div class="mt-1 flex flex-wrap gap-3">
                    <?php foreach ($options->trades as $trade): ?>
                        <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                            <input type="checkbox" name="trades[]" value="<?= e($trade) ?>" <?= in_array($trade, $selectedTrades, true) ? 'checked' : '' ?>> <?= e($trade) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>
        <label class="<?= $labelCls ?>">Nachricht (z. B. Aufbauzeiten, Treffpunkt)
            <textarea name="note" rows="3" maxlength="2000" class="<?= $input ?>"><?= e((string) ($old['note'] ?? '')) ?></textarea>
        </label>
        <div class="text-right">
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">Anfrage senden</button>
        </div>
    </form>
<?php endif; ?>
