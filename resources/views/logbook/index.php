<?php
/**
 * Driver's logbook list (migration 184) with filters, totals and CSV export.
 *
 * @var \App\Domain\Logbook\LogbookEntry[] $entries
 * @var array{count: int, km: int, byCategory: array<string, int>} $totals
 * @var array<string, string> $query active filters
 * @var bool $manager
 * @var \App\Domain\Vehicle\Vehicle[] $vehicleOptions
 * @var array<string, string> $driverOptions
 */

use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookStatus;

use function App\Infrastructure\Http\e;

$q = static fn (string $key): string => $query[$key] ?? '';
$input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$km = static fn (int $value): string => number_format($value, 0, ',', '.') . ' km';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Fahrtenbuch</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= $manager ? 'Alle Fahrten' : 'Deine Fahrten' ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="/fahrtenbuch/fahrtenbuch.pdf<?= $query !== [] ? '?' . e(http_build_query($query)) : '' ?>" target="_blank" data-pdf-viewer class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" title="Fahrtenbuch mit den gewählten Filtern, z. B. je Fahrzeug und Zeitraum">PDF</a>
        <a href="/fahrtenbuch?<?= e(http_build_query($query + ['export' => 'csv'])) ?>" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">CSV-Export</a>
        <a href="/fahrtenbuch/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">+ Fahrt erfassen</a>
    </div>
</div>

<?php if (($pendingCount ?? 0) > 0): ?>
    <a href="/fahrtenbuch?status=pending" class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
        <span><strong><?= (int) $pendingCount ?> <?= $pendingCount === 1 ? 'Fahrt' : 'Fahrten' ?> zu klären</strong> – ungeprüfte Fahrten externer Fahrer und gemeldete Lücken im Kilometerstand.</span>
        <span aria-hidden="true">→</span>
    </a>
<?php endif; ?>

<form method="get" action="/fahrtenbuch" id="logbook-filter-form" class="mb-4 grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-6 dark:border-slate-800 dark:bg-slate-900">
    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Von
        <input type="date" name="von" value="<?= e($q('von')) ?>" class="<?= $input ?>">
    </label>
    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bis
        <input type="date" name="bis" value="<?= e($q('bis')) ?>" class="<?= $input ?>">
    </label>
    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fahrzeug
        <select name="fahrzeug" class="<?= $input ?>">
            <option value="">alle</option>
            <?php foreach ($vehicleOptions as $vehicle): ?>
                <option value="<?= e($vehicle->id) ?>" <?= $q('fahrzeug') === $vehicle->id ? 'selected' : '' ?>><?= e($vehicle->name) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Art
        <select name="kategorie" class="<?= $input ?>">
            <option value="">alle</option>
            <?php foreach (LogbookCategory::cases() as $category): ?>
                <option value="<?= e($category->value) ?>" <?= $q('kategorie') === $category->value ? 'selected' : '' ?>><?= e($category->label()) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($manager): ?>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fahrer
            <select name="fahrer" class="<?= $input ?>">
                <option value="">alle</option>
                <?php foreach ($driverOptions as $id => $name): ?>
                    <option value="<?= e($id) ?>" <?= $q('fahrer') === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status
            <select name="status" class="<?= $input ?>">
                <option value="">alle</option>
                <?php foreach (LogbookStatus::cases() as $status): ?>
                    <option value="<?= e($status->value) ?>" <?= $q('status') === $status->value ? 'selected' : '' ?>><?= e($status->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fahrer intern/extern
            <select name="extern" class="<?= $input ?>">
                <option value="">alle</option>
                <option value="0" <?= $q('extern') === '0' ? 'selected' : '' ?>>nur interne</option>
                <option value="1" <?= $q('extern') === '1' ? 'selected' : '' ?>>nur externe</option>
            </select>
        </label>
        <label class="flex items-end gap-2 pb-2 text-xs font-medium text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="geloescht" value="1" <?= $q('geloescht') === '1' ? 'checked' : '' ?>> Gelöschte anzeigen
        </label>
    <?php endif; ?>
    <?php if ($q('veranstaltung') !== ''): ?><input type="hidden" name="veranstaltung" value="<?= e($q('veranstaltung')) ?>"><?php endif; ?>
    <div class="col-span-2 flex items-end gap-2 sm:col-span-1">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Filtern</button>
        <a href="/fahrtenbuch" class="rounded-md px-3 py-2 text-sm text-slate-500 hover:underline dark:text-slate-400">Zurücksetzen</a>
    </div>
</form>

<div class="mb-4 flex flex-wrap gap-3 text-sm">
    <div class="rounded-lg border border-slate-200 bg-white px-4 py-2 dark:border-slate-800 dark:bg-slate-900">
        <span class="text-slate-500 dark:text-slate-400">Fahrten</span> <strong class="ml-1 text-slate-900 dark:text-slate-100"><?= $totals['count'] ?></strong>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white px-4 py-2 dark:border-slate-800 dark:bg-slate-900">
        <span class="text-slate-500 dark:text-slate-400">Gesamt</span> <strong class="ml-1 text-slate-900 dark:text-slate-100"><?= e($km($totals['km'])) ?></strong>
    </div>
    <?php foreach (LogbookCategory::cases() as $category): ?>
        <?php if (isset($totals['byCategory'][$category->value])): ?>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-2 dark:border-slate-800 dark:bg-slate-900">
                <span class="text-slate-500 dark:text-slate-400"><?= e($category->label()) ?></span> <strong class="ml-1 text-slate-900 dark:text-slate-100"><?= e($km($totals['byCategory'][$category->value])) ?></strong>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div id="logbook-table-container">
    <?php require __DIR__ . '/_table.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initPaginatedList({ container: '#logbook-table-container', form: '#logbook-filter-form', endpoint: '/fahrtenbuch' });
    });
</script>
