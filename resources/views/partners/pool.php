<?php
/**
 * @var \App\Application\Federation\Query\PartnerPool $pool
 * @var \App\Domain\Rental\Rental|null $rental
 * @var string $search
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use App\Domain\Federation\Discount;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$partner = $pool->partner;
$money = static fn (?float $v): string => $v === null ? '–' : number_format($v, 2, ',', '.') . ' €';
$input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$labelCls = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$quantities = is_array($old['quantity'] ?? null) ? $old['quantity'] : [];
$baseQuery = ['from' => $pool->from, 'to' => $pool->to, 'q' => $search, 'rental' => $rental?->id];
?>
<div class="mb-6">
    <a href="/partner" class="text-xs text-brand-700 hover:underline dark:text-brand-400">← Partner</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Materialpool von <?= e($partner->name) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Verfügbarkeit im gewählten Zeitraum, Preise je Einsatztag<?= $pool->discountPercent > 0 ? ' inkl. vereinbartem Rabatt von ' . e(Discount::format($pool->discountPercent)) : '' ?>.
        <?php if ($rental !== null): ?>Für Veranstaltung <a class="underline" href="/veranstaltungen/<?= e($rental->id) ?>"><?= e($rental->eventName) ?></a>.<?php endif; ?>
    </p>
</div>

<form method="get" action="/partner/<?= e($partner->id) ?>/materialpool" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <?php if ($rental !== null): ?><input type="hidden" name="rental" value="<?= e($rental->id) ?>"><?php endif; ?>
    <label class="<?= $labelCls ?>">Von<input type="date" name="from" value="<?= e($pool->from) ?>" class="<?= $input ?>"></label>
    <label class="<?= $labelCls ?>">Bis<input type="date" name="to" value="<?= e($pool->to) ?>" class="<?= $input ?>"></label>
    <label class="<?= $labelCls ?> min-w-0 flex-1">Suche<input type="search" name="q" value="<?= e($search) ?>" placeholder="Artikelname" class="<?= $input ?>"></label>
    <button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Anzeigen</button>
</form>

<?php foreach (['endDate', 'startDate', 'items', 'eventName', 'partner'] as $key): ?>
    <?php if (isset($errors[$key])): ?>
        <p class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800"><?= e($errors[$key]) ?></p>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($pool->error !== null): ?>
    <p class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800">Der Materialpool konnte nicht geladen werden: <?= e($pool->error) ?></p>
<?php else: ?>
<form method="post" action="/partner/<?= e($partner->id) ?>/materialpool" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="from" value="<?= e($pool->from) ?>">
    <input type="hidden" name="to" value="<?= e($pool->to) ?>">
    <input type="hidden" name="q" value="<?= e($search) ?>">
    <?php if ($rental !== null): ?><input type="hidden" name="rental_id" value="<?= e($rental->id) ?>"><?php endif; ?>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                <tr>
                    <th class="px-3 py-2">Artikel</th>
                    <th class="px-3 py-2 text-right">Verfügbar</th>
                    <th class="px-3 py-2 text-right">Preis/Tag</th>
                    <th class="px-3 py-2 text-right">Menge</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if ($pool->items === []): ?>
                    <tr><td colspan="4" class="px-3 py-4 text-slate-500 dark:text-slate-400">Keine Artikel gefunden.</td></tr>
                <?php endif; ?>
                <?php foreach ($pool->items as $item): ?>
                    <tr>
                        <td class="px-3 py-2">
                            <p class="font-medium text-slate-800 dark:text-slate-200"><?= e($item['name']) ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400"><?= e(implode(' · ', array_filter([$item['manufacturer'], $item['category']]))) ?></p>
                        </td>
                        <td class="px-3 py-2 text-right <?= $item['available'] === 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-300' ?>"><?= $item['available'] ?> / <?= $item['total'] ?></td>
                        <td class="px-3 py-2 text-right text-slate-700 dark:text-slate-300">
                            <?php if ($item['discounted_daily_price'] !== null && $item['daily_price'] !== null && $item['discounted_daily_price'] < $item['daily_price']): ?>
                                <span class="text-xs text-slate-400 line-through"><?= e($money($item['daily_price'])) ?></span><br>
                            <?php endif; ?>
                            <?= e($money($item['discounted_daily_price'] ?? $item['daily_price'])) ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <input type="number" min="0" max="<?= max(0, $item['available']) ?>" name="quantity[<?= e($item['id']) ?>]" value="<?= e((string) ($quantities[$item['id']] ?? '')) ?>" <?= $item['available'] === 0 ? 'disabled' : '' ?> class="w-20 rounded-md border border-slate-300 px-2 py-1 text-right text-sm dark:border-slate-700 dark:bg-slate-900">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pool->totalPages() > 1): ?>
        <div class="flex items-center justify-between text-sm text-slate-600 dark:text-slate-400">
            <span>Seite <?= $pool->page ?> von <?= $pool->totalPages() ?> (<?= $pool->total ?> Artikel) – Mengen werden nur für die angezeigte Seite übernommen.</span>
            <span class="flex gap-2">
                <?php if ($pool->page > 1): ?><a class="underline" href="?<?= e(http_build_query($baseQuery + ['page' => $pool->page - 1])) ?>">← Zurück</a><?php endif; ?>
                <?php if ($pool->page < $pool->totalPages()): ?><a class="underline" href="?<?= e(http_build_query($baseQuery + ['page' => $pool->page + 1])) ?>">Weiter →</a><?php endif; ?>
            </span>
        </div>
    <?php endif; ?>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Anfrage für <?= e(date('d.m.Y', strtotime($pool->from))) ?><?= $pool->to !== $pool->from ? ' – ' . e(date('d.m.Y', strtotime($pool->to))) : '' ?></h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="<?= $labelCls ?>">Veranstaltung
                <input type="text" name="event_name" maxlength="200" required value="<?= e((string) ($old['event_name'] ?? $rental?->eventName ?? '')) ?>" class="<?= $input ?>">
            </label>
            <label class="<?= $labelCls ?>">Ort (optional)
                <input type="text" name="event_location" maxlength="255" value="<?= e((string) ($old['event_location'] ?? $rental?->eventLocation ?? '')) ?>" class="<?= $input ?>">
            </label>
            <label class="<?= $labelCls ?> sm:col-span-2">Nachricht an <?= e($partner->name) ?> (optional)
                <textarea name="note" rows="2" maxlength="2000" class="<?= $input ?>"><?= e((string) ($old['note'] ?? '')) ?></textarea>
            </label>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                <?= $partner->name !== '' ? e($partner->name) : 'Der Partner' ?> entscheidet über die Anfrage – je nach dessen Einstellung wird sie sofort eingetragen und zugesagt.
            </p>
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">Anfrage senden</button>
        </div>
    </div>
</form>
<?php endif; ?>
