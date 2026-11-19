<?php
/** @var \App\Application\Article\Query\ArticleDetail $detail */
/** @var list<array{article_id: string, label: string, quantity: int}> $bundleComponentRows */
/** @var list<array{id: string, label: string}> $containingBundles */
/** @var \App\Domain\PowerDistributor\PowerDistributor|null $powerDistributor Stromverteiler-Angaben (Migration 117) */
$powerDistributor ??= null;
/** @var \App\Application\Cable\Query\ArticleCableDetail|null $cable Leitungsangaben (Migration 125) */
$cable ??= null;
/** @var \App\Application\DigitalStagebox\Query\ArticleDigitalStageboxDetail|null $digitalStagebox Angaben der digitalen Stagebox (Migration 152) */
$digitalStagebox ??= null;
/** @var \App\Application\MixingConsole\Query\ArticleMixingConsoleDetail|null $mixingConsole Tonmischpult (Migration 154) */
$mixingConsole ??= null;
/** @var \App\Domain\MixingConsole\CompatibleConsole[] $compatibleConsoles Pulte, die diese Stagebox als kompatibel führen */
$compatibleConsoles ??= [];
/** @var string|null $editInventoryId */
/** @var \App\Application\Article\Query\InventoryNumberWithRmaInfo[] $inventoryPageEntries Inventory numbers on the current list page */
$inventoryPageEntries ??= $detail->inventoryNumbers;
/** @var int $inventoryPage */
$inventoryPage ??= 1;
/** @var int $inventoryPageSize */
$inventoryPageSize ??= \App\Infrastructure\Http\Pagination::DEFAULT_PAGE_SIZE;
/** @var int $inventoryTotalPages */
$inventoryTotalPages ??= 1;
/** @var array<string, string> $inventoryListQuery Other query parameters of the page (kept by pagination links) */
$inventoryListQuery ??= [];
/** @var \App\Application\Article\Query\InventoryNumberWithRmaInfo|null $editInventoryEntry Inventory number shown in the edit dialog */
$editInventoryEntry ??= null;
// Article URL showing the inventory number list on the given page, optionally with the edit dialog open.
$inventoryListUrl = static function (int $page, ?string $editId = null) use ($detail, $inventoryPageSize, $inventoryListQuery): string {
    $params = $inventoryListQuery;
    if ($page > 1) {
        $params['page'] = (string) $page;
    }
    if ($inventoryPageSize !== (\App\Infrastructure\Http\Auth\CurrentUser::get()?->defaultPageSize ?? \App\Infrastructure\Http\Pagination::DEFAULT_PAGE_SIZE)) {
        $params['pageSize'] = (string) $inventoryPageSize;
    }
    if ($editId !== null) {
        $params['edit_inventory'] = $editId;
    }
    $query = http_build_query($params);

    return '/artikel/' . $detail->article->id . ($query !== '' ? '?' . $query : '') . '#inventurnummern';
};
/** @var \App\Application\Network\Query\ArticleNetworkOverview|null $networkOverview Netzwerke (Migration 155) */
$networkOverview ??= null;
/** @var \App\Application\Network\NetworkInterfaceForm|null $networkAddForm */
$networkAddForm ??= null;
/** @var \App\Application\Network\NetworkInterfaceForm|null $networkEditForm */
$networkEditForm ??= null;
/** @var string|null $editAccessoryId */
/** @var string|null $editPreIssueCheckId */
/** @var \App\Domain\Supplier\Supplier[] $suppliers */
/** @var \App\Application\Receipt\Query\ReceiptListEntry[] $receipts */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var \DateTimeImmutable[][] $availabilityWeeks */
/** @var \DateTimeImmutable $availabilityMonth */
/** @var \App\Application\Rental\Query\ArticleAvailability $availability */
/** @var string $availabilityPrevMonth */
/** @var string $availabilityNextMonth */
/** @var array<int, string>|null $rentalPricesByDay */
/** @var int $saleStockQuantity */
/** @var \App\Domain\Stock\SaleStockLot[] $saleStockLots */
/** @var \App\Domain\Customer\Customer[] $saleCustomers */
/** @var array<string, string> $saleStockErrors */

use App\Domain\Article\ArticleDisplayName;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$article = $detail->article;
$supplierById = [];
foreach ($suppliers as $supplier) {
    $supplierById[$supplier->id] = $supplier;
}
// Für die Anzeige "über den Beleg" aufgelöster Lieferant (siehe
// AddInventoryNumberHandler): $receipts liefert Beleg + zugehörigen
// Lieferanten bereits zusammen (ReceiptListEntry), sodass hier keine
// zusätzliche Lieferanten-Auflösung nötig ist.
$receiptById = [];
foreach ($receipts as $receiptEntry) {
    $receiptById[$receiptEntry->receipt->id] = $receiptEntry;
}
$receiptLabel = static function (\App\Domain\Receipt\Receipt $receipt) use ($receiptById): string {
    $entry = $receiptById[$receipt->id] ?? null;
    $label = $receipt->receiptNumber ?? $receipt->receiptDate->format('d.m.Y');
    if ($entry !== null && $entry->supplier !== null) {
        $label .= ' · ' . $entry->supplier->name;
    }

    return $label;
};

$monthLabels = [
    1 => t('articles.show.month.1'), 2 => t('articles.show.month.2'), 3 => t('articles.show.month.3'),
    4 => t('articles.show.month.4'), 5 => t('articles.show.month.5'), 6 => t('articles.show.month.6'),
    7 => t('articles.show.month.7'), 8 => t('articles.show.month.8'), 9 => t('articles.show.month.9'),
    10 => t('articles.show.month.10'), 11 => t('articles.show.month.11'), 12 => t('articles.show.month.12'),
];
$availabilityMonthLabel = $monthLabels[(int) $availabilityMonth->format('n')] . ' ' . $availabilityMonth->format('Y');
$availabilityWeekdayLabels = [
    t('articles.show.weekday.mon'), t('articles.show.weekday.tue'), t('articles.show.weekday.wed'),
    t('articles.show.weekday.thu'), t('articles.show.weekday.fri'), t('articles.show.weekday.sat'), t('articles.show.weekday.sun'),
];
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

// Statuscode (siehe ArticleAvailability::statusForDay) -> Farbe/Icon/Text.
$availabilityStatusMeta = [
    0 => ['label' => t('articles.show.status_free'), 'icon' => '✓', 'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-800'],
    1 => ['label' => t('articles.show.status_partial'), 'icon' => '!', 'classes' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800'],
    2 => ['label' => t('articles.show.status_booked_out'), 'icon' => '✗', 'classes' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800'],
    3 => ['label' => t('articles.show.status_none'), 'icon' => '–', 'classes' => 'bg-slate-50 text-slate-400 ring-slate-200 dark:bg-slate-800/60 dark:text-slate-500 dark:ring-slate-700'],
];

// Secondary sections (accessories, checks, firmware, documents, ...) are
// collapsible <details> elements: closed by default so the page leads with
// the essentials, opened automatically while one of their rows is edited.
// Which sections a user keeps open is remembered in the browser (see script below).
$sectionOpen = static function (string $key, string $title, int $count = 0, bool $forceOpen = false): string {
    $badge = $count > 0
        ? '<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300">' . $count . '</span>'
        : '';

    return '<details class="article-section group rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" data-article-section="' . e($key) . '"' . ($forceOpen ? ' open data-force-open' : '') . '>'
        . '<summary class="flex cursor-pointer select-none list-none items-center gap-2 rounded-lg px-4 py-3 transition-colors hover:bg-slate-50 active:bg-slate-100 group-open:rounded-b-none dark:hover:bg-slate-800/60 dark:active:bg-slate-800 [&::-webkit-details-marker]:hidden">'
        . '<svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200 ease-out group-open:rotate-90 motion-reduce:transition-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 010-1.06L11.94 9 7.21 4.29a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06 0z" clip-rule="evenodd"/></svg>'
        . '<h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">' . e($title) . '</h2>'
        . '<span class="ml-auto">' . $badge . '</span>'
        . '</summary>'
        . '<div class="px-4 pb-4 pt-1">';
};
$sectionClose = '</div></details>';
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        <a href="/artikel" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('articles.show.back')) ?></a>
        <h1 class="mt-1 break-words text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(ArticleDisplayName::of($article, $detail->manufacturer)) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            <?php if ($detail->category !== null): ?><?= e($detail->category->name) ?><?php endif; ?>
            <?php if ($detail->manufacturer?->prefix !== null && $article->prefix !== null): ?>
                · <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e($detail->manufacturer->prefix) ?>-<?= e($article->prefix) ?>-…</span>
            <?php endif; ?>
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
            <?php if (!$article->isActive): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-300 dark:bg-red-950/40 dark:text-red-400 dark:ring-red-700">
                    <?= e(t('articles.show.inactive_badge')) ?>
                </span>
            <?php endif; ?>
            <?php if ($article->requiresElectricalInspection): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700">
                    <?= e(t('articles.show.electrical_inspection_badge')) ?><?= $article->protectionClass !== null ? ' · ' . e($article->protectionClass->value) : '' ?>
                </span>
            <?php endif; ?>
            <?php if ($article->is19Inch): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600">
                    <?= e($article->rackWidthBadge()) ?><?= $article->heightUnits !== null ? ' · ' . $article->heightUnits . ' HE' : '' ?>
                </span>
            <?php endif; ?>
            <?php if ($powerDistributor !== null): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700">
                    Stromverteiler · <?= e($powerDistributor->inletType->shortLabel()) ?>
                </span>
            <?php endif; ?>
            <?php if ($cable !== null): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800">
                    <?php if ($cable->cable->multicore !== null && ($cable->type?->isAnalogMulticore ?? false)): ?>
                        Analoges Multicore · <?= e($cable->cable->multicore->channelSummary()) ?> · <?= e($cable->cable->lengthLabel()) ?>
                    <?php else: ?>
                        Leitung · <?= e($cable->cable->lengthLabel()) ?>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <?php if ($digitalStagebox !== null): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700" title="Eingänge/Ausgänge">
                    Digitale Stagebox · <?= e($digitalStagebox->protocol?->name ?? '–') ?> · <?= e($digitalStagebox->stagebox->channelSummary()) ?>
                </span>
            <?php endif; ?>
            <?php if ($mixingConsole !== null): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-800 ring-1 ring-inset ring-violet-300 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-700">
                    Tonmischpult · <?= e($mixingConsole->console->summary()) ?>
                </span>
            <?php endif; ?>
            <?php if ($networkOverview?->settings?->hasNetworkInterface): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-800 ring-1 ring-inset ring-sky-300 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-700">
                    Netzwerkschnittstelle<?= $networkOverview->settings->requiresDhcp ? ' · benötigt DHCP' : '' ?>
                </span>
            <?php endif; ?>
            <?php if ($article->hasFirmware): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600">
                    <?= e(t('articles.show.has_firmware_badge')) ?>
                </span>
            <?php endif; ?>
            <?php if ($article->isBundle): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800">
                    <?= e(t('articles.badge.bundle')) ?>
                </span>
            <?php elseif (!$article->isRentableIndividually): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700">
                    <?= e(t('articles.badge.not_rentable_individually')) ?>
                </span>
            <?php endif; ?>
            <?php if ($article->isBillable): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800">
                    <?= e(t('articles.show.own_line_item_badge')) ?><?= $article->dailyRentalPrice !== null ? ' · ' . e($article->dailyRentalPrice) . ' ' . e(t('articles.show.per_rental_day_short')) : '' ?>
                </span>
            <?php else: ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">
                    <?= e(t('articles.show.accessory_badge')) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if (($bundleComponentRows ?? []) !== []): ?>
            <div class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400"><?= e(t('articles.show.bundle_components')) ?>:</span>
                <?php foreach ($bundleComponentRows as $i => $componentRow): ?>
                    <?= $i > 0 ? ' · ' : '' ?><?= (int) $componentRow['quantity'] ?>× <a href="/artikel/<?= e($componentRow['article_id']) ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($componentRow['label']) ?></a><?php if (($componentRow['alternatives'] ?? []) !== []): ?> <span class="text-xs text-slate-500 dark:text-slate-400">(<?= e(t('articles.show.bundle_alternatives')) ?>: <?php foreach ($componentRow['alternatives'] as $j => $alternative): ?><?= $j > 0 ? ', ' : '' ?><a href="/artikel/<?= e($alternative['article_id']) ?>" class="hover:underline"><?= e($alternative['label']) ?></a><?php endforeach; ?>)</span><?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (($containingBundles ?? []) !== []): ?>
            <div class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400"><?= e(t('articles.show.part_of_bundles')) ?>:</span>
                <?php foreach ($containingBundles as $i => $bundleRow): ?>
                    <?= $i > 0 ? ' · ' : '' ?><a href="/artikel/<?= e($bundleRow['id']) ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($bundleRow['label']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <a href="/artikel/<?= e($article->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('common.edit')) ?>
        </a>
        <a href="/artikel/<?= e($article->id) ?>/duplizieren" title="Neuen Artikel mit denselben Angaben anlegen" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Duplizieren
        </a>
        <?php if ($article->isActive): ?>
            <form method="post" action="/artikel/<?= e($article->id) ?>/deaktivieren" data-confirm-key="articles.deactivate" data-confirm="<?= e(t('articles.show.confirm_deactivate')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <?= e(t('articles.show.deactivate')) ?>
                </button>
            </form>
        <?php else: ?>
            <form method="post" action="/artikel/<?= e($article->id) ?>/aktivieren">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                    <?= e(t('articles.show.activate')) ?>
                </button>
            </form>
        <?php endif; ?>
        <form method="post" action="/artikel/<?= e($article->id) ?>/loeschen" data-confirm-key="articles.delete" data-confirm="<?= e(t('articles.show.confirm_delete')) ?>" data-confirm-style="danger">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                <?= e(t('common.delete')) ?>
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <!-- Stammdaten -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.details_title')) ?></h2>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.weight')) ?><?= $article->isBundle ? ' (' . e(t('articles.show.weight_calculated')) . ')' : '' ?></dt>
                    <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $article->weightValue !== null ? e($article->weightValue . ' ' . $article->weightUnit) : '–' ?></dd>
                </div>
                <?php $statedPowerW = ($articlePower ?? null)?->statedW(); ?>
                <?php if ($statedPowerW !== null): ?>
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500"><?= $articlePower->isAverage() ? 'Durchschnittliche Leistung' : 'Spitzenleistung' ?></dt>
                    <dd class="font-mono text-slate-800 dark:text-slate-200"><?= e(number_format($statedPowerW, 0, ',', '.') . ' W') ?> <span class="font-sans text-xs text-slate-400 dark:text-slate-500">(≈ <?= e(number_format($statedPowerW / 230, 1, ',', '.')) ?> A bei 230 V)</span></dd>
                </div>
                <?php endif; ?>
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.dimensions')) ?></dt>
                    <dd class="font-mono text-slate-800 dark:text-slate-200"><?= e($article->dimensions ?? '–') ?></dd>
                </div>
                <?php if ($article->is19Inch): ?>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500">Einbaubreite</dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-200"><?= e($article->rackWidth->label()) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.height_units')) ?></dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $article->heightUnits !== null ? e((string) $article->heightUnits) . ' HE' : '–' ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.install_depth_cm')) ?></dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $article->installDepthCm !== null ? e($article->installDepthCm) . ' cm' : '–' ?></dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.purchase_price')) ?></dt>
                    <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $article->purchasePrice !== null ? e($article->purchasePrice) . ' €' : '–' ?></dd>
                </div>
                <?php if ($article->isBillable): ?>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.daily_rental_price')) ?></dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $article->dailyRentalPrice !== null ? e($article->dailyRentalPrice) . ' €' : '–' ?></dd>
                    </div>
                    <?php $dryHirePrice = \App\Infrastructure\Http\DryHireLookup::articlePrice($article->id); ?>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500">Dry-Hire-Tagesmietsatz</dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $dryHirePrice !== null ? e($dryHirePrice) . ' €' : '–' ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
            <?php if ($article->description !== null): ?>
                <div class="mt-4">
                    <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.description')) ?></dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= e($article->description) ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($article->documentShortText !== null): ?>
                <div class="mt-4">
                    <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.document_short_text')) ?></dt>
                    <dd class="mt-1 text-sm italic text-slate-700 dark:text-slate-300"><?= e($article->documentShortText) ?></dd>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($rentalPricesByDay !== null): ?>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.rental_prices_title')) ?></h2>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] text-left text-sm">
                        <thead class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">
                            <tr>
                                <?php foreach ($rentalPricesByDay as $day => $price): ?>
                                    <th class="px-2 py-1.5"><?= $day ?> <?= $day > 1 ? e(t('articles.show.days_short')) : e(t('articles.show.day_short')) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php foreach ($rentalPricesByDay as $price): ?>
                                    <td class="px-2 py-1.5 font-mono text-slate-800 dark:text-slate-200"><?= e($price) ?> €</td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                    <?= t('articles.show.rental_prices_hint', ['link' => '<a href="/einstellungen/preisstaffelung" class="underline">' . e(t('articles.show.price_tiers_link')) . '</a>']) ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Stromverteiler (Migration 117): Angaben werden im Artikelformular gepflegt -->
        <?php if ($powerDistributor !== null): ?>
            <div id="stromverteiler" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Stromverteiler</h2>
                    <a href="/artikel/<?= e($article->id) ?>/bearbeiten#power_distributor_fields" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                </div>
                <?= \App\Infrastructure\Http\View::render('power-distributors/_schema', ['distributor' => $powerDistributor]) ?>
            </div>
        <?php endif; ?>

        <!-- Leitung (Migration 125): Angaben werden im Artikelformular gepflegt -->
        <?php if ($cable !== null): ?>
            <div id="leitung" class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Leitung</h2>
                    <a href="/artikel/<?= e($article->id) ?>/bearbeiten#cable_section" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                </div>
                <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Leitungstyp</dt><dd class="text-slate-800 dark:text-slate-200"><?= e($cable->type?->name ?? '–') ?></dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Länge</dt><dd class="text-slate-800 dark:text-slate-200"><?= e($cable->cable->lengthLabel()) ?></dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Seite A</dt><dd class="text-slate-800 dark:text-slate-200"><?= e($cable->connectorA?->name ?? '–') ?></dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Seite B</dt><dd class="text-slate-800 dark:text-slate-200"><?= e($cable->connectorB?->name ?? '–') ?></dd></div>
                    <?php if ($cable->cable->multicore !== null && ($cable->type?->isAnalogMulticore ?? false)): ?>
                        <?php $cableMulticore = $cable->cable->multicore; ?>
                        <!-- Analog multicore (migration 157) -->
                        <div><dt class="text-xs text-slate-500 dark:text-slate-400">Sends</dt><dd class="text-slate-800 dark:text-slate-200"><?= e((string) $cableMulticore->sends) ?></dd></div>
                        <div><dt class="text-xs text-slate-500 dark:text-slate-400">Returns</dt><dd class="text-slate-800 dark:text-slate-200"><?= e((string) $cableMulticore->returns) ?></dd></div>
                        <div><dt class="text-xs text-slate-500 dark:text-slate-400">Auf Trommel</dt><dd class="text-slate-800 dark:text-slate-200"><?= $cableMulticore->onDrum ? 'ja' : 'nein' ?></dd></div>
                        <div><dt class="text-xs text-slate-500 dark:text-slate-400">Harting-Steckverbinder</dt><dd class="text-slate-800 dark:text-slate-200"><?= $cableMulticore->hasHarting ? 'ja' : 'nein' ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
        <?php endif; ?>

        <!-- Digitale Stagebox (Migration 152): Angaben werden im Artikelformular gepflegt -->
        <?php if ($digitalStagebox !== null): ?>
            <?php $sb = $digitalStagebox->stagebox; ?>
            <div id="digitale-stagebox" class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Digitale Stagebox</h2>
                    <a href="/artikel/<?= e($article->id) ?>/bearbeiten#digital_stagebox_section" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                </div>
                <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Digitales Protokoll</dt><dd class="text-slate-800 dark:text-slate-200"><?= e($digitalStagebox->protocol?->name ?? '–') ?></dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Analoge Eingänge</dt><dd class="text-slate-800 dark:text-slate-200"><?= e($sb->inputsLabel()) ?></dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Ausgänge</dt><dd class="text-slate-800 dark:text-slate-200"><?= e($sb->outputsLabel()) ?></dd></div>
                </dl>
            </div>
        <?php endif; ?>

        <!-- Tonmischpult (Migration 154): Angaben werden im Artikelformular gepflegt -->
        <?php if ($mixingConsole !== null): ?>
            <?php
                $mc = $mixingConsole->console;
                $dt = 'text-xs text-slate-500 dark:text-slate-400';
                $dd = 'text-slate-800 dark:text-slate-200';
            ?>
            <div id="tonmischpult" class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tonmischpult · <?= e($mc->type->label()) ?></h2>
                    <a href="/artikel/<?= e($article->id) ?>/bearbeiten#mixing_console_section" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                </div>
                <?php if (!$mc->isDigital()): ?>
                    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="<?= $dt ?>">Eingänge</dt><dd class="<?= $dd ?>"><?= e($mc->analogInputsLabel()) ?></dd></div>
                        <div><dt class="<?= $dt ?>">AUX-Wege</dt><dd class="<?= $dd ?>"><?= e($mc->auxLabel()) ?></dd></div>
                        <div><dt class="<?= $dt ?>">Subgruppen</dt><dd class="<?= $dd ?>"><?= $mc->subgroups > 0 ? e((string) $mc->subgroups) : 'keine' ?></dd></div>
                        <div><dt class="<?= $dt ?>">MONO-Master</dt><dd class="<?= $dd ?>"><?= $mc->hasMonoMaster ? 'ja' : 'nein' ?></dd></div>
                    </dl>
                <?php else: ?>
                    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div>
                            <dt class="<?= $dt ?>">Digitale Schnittstellen</dt>
                            <dd class="<?= $dd ?>"><?= $mc->interfaces === [] ? 'keine' : e(implode(', ', array_map(static fn ($i) => $i->quantity . ' × ' . $mixingConsole->protocolName($i->protocolId), $mc->interfaces))) ?></dd>
                        </div>
                        <div>
                            <dt class="<?= $dt ?>">Lokale analoge Ein-/Ausgänge</dt>
                            <dd class="<?= $dd ?>"><?= e($mc->localInputs . ' Eingänge / ' . $mc->localOutputs . ' Ausgänge') ?></dd>
                        </div>
                        <div>
                            <dt class="<?= $dt ?>">Erweiterungsslots</dt>
                            <dd class="<?= $dd ?>"><?= $mc->expansionSlots > 0 ? e($mc->expansionSlots . ' ×' . ($mc->expansionSlotType !== null ? ' ' . $mc->expansionSlotType : '')) : 'keine' ?></dd>
                        </div>
                    </dl>
                    <?php if ($mc->channels !== []): ?>
                        <h3 class="mt-4 text-xs font-semibold text-slate-700 dark:text-slate-300">DSP-Kanäle</h3>
                        <div class="mt-1 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead><tr class="text-left text-xs text-slate-500 dark:text-slate-400"><th class="py-1 pr-4 font-medium">Herstellerbezeichnung</th><th class="py-1 pr-4 font-medium">Kanalart</th><th class="py-1 pr-4 font-medium">Anzahl</th><th class="py-1 font-medium">Ausführung</th></tr></thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <?php foreach ($mc->channels as $channel): ?>
                                        <tr class="<?= $dd ?>"><td class="py-1 pr-4"><?= e($channel->manufacturerLabel ?? '–') ?></td><td class="py-1 pr-4"><?= e($channel->kind->label()) ?></td><td class="py-1 pr-4"><?= e((string) $channel->quantity) ?></td><td class="py-1"><?= $channel->isStereo ? 'Stereo' : 'Mono' ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if ($mc->cards !== []): ?>
                        <h3 class="mt-4 text-xs font-semibold text-slate-700 dark:text-slate-300">Mögliche Erweiterungskarten</h3>
                        <div class="mt-1 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead><tr class="text-left text-xs text-slate-500 dark:text-slate-400"><th class="py-1 pr-4 font-medium">Karte</th><th class="py-1 pr-4 font-medium">Protokoll</th><th class="py-1 pr-4 font-medium">Kanäle</th><th class="py-1 font-medium">Bemerkung</th></tr></thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <?php foreach ($mc->cards as $card): ?>
                                        <tr class="<?= $dd ?>"><td class="py-1 pr-4"><?= e($card->name) ?></td><td class="py-1 pr-4"><?= e($mixingConsole->protocolName($card->protocolId)) ?></td><td class="py-1 pr-4"><?= e($card->channelSummary()) ?></td><td class="py-1"><?= e($card->notes ?? '') ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php foreach (\App\Domain\MixingConsole\StageboxCompatibility::cases() as $compatibility): ?>
                        <?php $linked = $mixingConsole->stageboxes($compatibility); ?>
                        <?php if ($linked !== []): ?>
                            <h3 class="mt-4 text-xs font-semibold text-slate-700 dark:text-slate-300">Stageboxen – <?= e($compatibility->label()) ?></h3>
                            <ul class="mt-1 space-y-0.5">
                                <?php foreach ($linked as $stageboxOption): ?>
                                    <li><a href="/artikel/<?= e($stageboxOption->articleId) ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($stageboxOption->displayName()) ?></a> <span class="text-xs text-slate-500 dark:text-slate-400"><?= e($mixingConsole->protocolName($stageboxOption->protocolId)) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Digitale Stagebox: Tonmischpulte, die diese Stagebox als kompatibel führen (Migration 154) -->
        <?php if ($digitalStagebox !== null && $compatibleConsoles !== []): ?>
            <div id="kompatible-tonmischpulte" class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Kompatible Tonmischpulte</h2>
                <ul class="space-y-0.5">
                    <?php foreach ($compatibleConsoles as $compatibleConsole): ?>
                        <li>
                            <a href="/artikel/<?= e($compatibleConsole->articleId) ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($compatibleConsole->displayName()) ?></a>
                            <span class="text-xs <?= $compatibleConsole->compatibility === \App\Domain\MixingConsole\StageboxCompatibility::Native ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' ?>"><?= e($compatibleConsole->compatibility->label()) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Bilder -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.images_title')) ?></h2>
            <p class="mb-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.document_image_hint')) ?></p>
            <div class="flex flex-wrap gap-3">
                <?php foreach ($article->images as $image): ?>
                    <?php $isDocumentImage = $article->documentImageId === $image->id; ?>
                    <div class="flex flex-col items-center gap-1">
                    <div class="relative h-24 w-24 overflow-hidden rounded-md <?= $isDocumentImage ? 'ring-2 ring-brand-500' : 'ring-1 ring-slate-200 dark:ring-slate-700' ?>">
                        <button type="button" class="image-gallery-trigger block h-full w-full" data-gallery="article-images" data-gallery-src="/artikel-bilder/<?= e($image->filePath) ?>" data-gallery-caption="<?= e($article->name) ?>" aria-label="<?= e(t('gallery.open')) ?>">
                            <img src="/artikel-bilder/<?= e($image->filePath) ?>" alt="" class="h-full w-full object-cover">
                        </button>
                        <form method="post" action="/artikelbilder/<?= e($image->id) ?>/loeschen" data-confirm-key="articles.image.delete" data-confirm="<?= e(t('articles.show.confirm_delete_image')) ?>" data-confirm-style="danger" class="absolute right-1 top-1">
                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                            <button type="submit" aria-label="<?= e(t('common.delete')) ?>" title="<?= e(t('common.delete')) ?>" class="rounded-full bg-black/60 px-1.5 py-0.5 text-xs text-white hover:bg-black/80">×</button>
                        </form>
                    </div>
                    <!-- Dokumentbild (Migration 109): erscheint klein links neben dem Artikelnamen auf Angebot, AB und Rechnung -->
                    <form method="post" action="/artikel/<?= e($article->id) ?>/dokumentbild">
                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <input type="hidden" name="image_id" value="<?= $isDocumentImage ? '' : e($image->id) ?>">
                        <?php if ($isDocumentImage): ?>
                            <button type="submit" title="<?= e(t('articles.show.document_image_remove')) ?>" class="text-[11px] font-medium text-brand-700 hover:underline dark:text-brand-400">✓ <?= e(t('articles.show.document_image_selected')) ?></button>
                        <?php else: ?>
                            <button type="submit" class="text-[11px] text-slate-500 hover:underline dark:text-slate-400"><?= e(t('articles.show.document_image_select')) ?></button>
                        <?php endif; ?>
                    </form>
                    </div>
                <?php endforeach; ?>

                <form method="post" action="/artikel/<?= e($article->id) ?>/bilder" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <label class="flex h-24 w-24 cursor-pointer flex-col items-center justify-center rounded-md border border-dashed border-slate-300 text-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-500 dark:hover:bg-slate-800">
                        <span class="text-xl leading-none">+</span>
                        <span class="mt-1 text-[11px]"><?= e(t('articles.show.add_image')) ?></span>
                        <input type="file" name="image" accept="image/*" class="hidden" onchange="this.form.submit()">
                    </label>
                </form>
            </div>
        </div>

        <!-- Werbebilder (KI-generiert) -->
        <?= $sectionOpen('ad-images', t('articles.show.ad_images_title'), count($detail->adImages)) ?>
            <p class="mb-3 text-xs text-slate-400 dark:text-slate-500">
                <?= t('articles.show.ad_images_hint', ['link' => '<a href="/einstellungen/ki-werbebilder" class="underline">' . e(t('articles.show.ad_images_settings_link')) . '</a>']) ?>
            </p>

            <?php if ($detail->adImages === []): ?>
                <p data-ad-image-empty class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_none')) ?></p>
            <?php endif; ?>
            <div data-ad-image-gallery class="mb-4 flex-wrap gap-3 <?= $detail->adImages === [] ? 'hidden' : 'flex' ?>">
                <?php foreach ($detail->adImages as $adImage): ?>
                    <div class="w-32 space-y-1">
                        <div class="relative h-32 w-32 overflow-hidden rounded-md ring-1 ring-slate-200 dark:ring-slate-700">
                            <button type="button" class="image-gallery-trigger block h-full w-full" data-gallery="article-ad-images" data-gallery-src="/artikel-werbebilder/<?= e($adImage->filePath) ?>" data-gallery-caption="<?= e($adImage->format->label()) ?>" data-gallery-download="/artikel-werbebilder/<?= e($adImage->filePath) ?>" aria-label="<?= e(t('gallery.open')) ?>">
                                <img src="/artikel-werbebilder/<?= e($adImage->filePath) ?>" alt="" class="h-full w-full object-cover">
                            </button>
                        </div>
                        <p class="truncate text-[11px] text-slate-500 dark:text-slate-400"><?= e($adImage->format->label()) ?><?php if ($adImage->sourceAdImageId !== null): ?> · <?= e(t('articles.show.ad_images_refined_badge')) ?><?php endif; ?></p>
                        <div class="flex flex-wrap items-center gap-x-2" data-row-actions="off">
                            <a href="/artikel-werbebilder/<?= e($adImage->filePath) ?>" download class="text-[11px] font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('articles.show.ad_images_download')) ?></a>
                            <button type="button" data-ad-image-refine
                                    data-action="/werbebilder/<?= e($adImage->id) ?>/ueberarbeiten"
                                    data-src="/artikel-werbebilder/<?= e($adImage->filePath) ?>"
                                    data-format="<?= e($adImage->format->label()) ?>"
                                    class="text-[11px] font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('articles.show.ad_images_refine')) ?></button>
                            <form method="post" action="/werbebilder/<?= e($adImage->id) ?>/in-galerie">
                                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                <button type="submit" title="<?= e(t('articles.show.ad_images_add_to_gallery_title')) ?>" class="text-[11px] font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('articles.show.ad_images_add_to_gallery')) ?></button>
                            </form>
                            <form method="post" action="/werbebilder/<?= e($adImage->id) ?>/loeschen" data-confirm-key="articles.ad_image.delete" data-confirm="<?= e(t('articles.show.ad_images_confirm_delete')) ?>" data-confirm-style="danger" data-confirm-ok="<?= e(t('articles.show.ad_images_delete')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                <button type="submit" class="text-[11px] font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('articles.show.ad_images_delete')) ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            // Shared spinner markup: rendered inside the submit button and cloned
            // into the placeholder tiles by the script below.
            $adImageSpinner = '<svg class="h-4 w-4 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
                . '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"></circle>'
                . '<path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>';
            ?>
            <form method="post" action="/artikel/<?= e($article->id) ?>/werbebilder" data-ad-image-form data-no-loading-indicator
                  data-label-one="<?= e(t('articles.show.ad_images_generate')) ?>"
                  data-label-many="<?= e(t('articles.show.ad_images_generate_many')) ?>"
                  data-status-one="<?= e(t('articles.show.ad_images_generating_status_one')) ?>"
                  data-status-many="<?= e(t('articles.show.ad_images_generating_status_many')) ?>"
                  data-tile-label="<?= e(t('articles.show.ad_images_generating_tile')) ?>"
                  data-overlay-one="<?= e(t('articles.show.ad_images_overlay_one')) ?>"
                  data-overlay-many="<?= e(t('articles.show.ad_images_overlay_many')) ?>"
                  class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label for="ad_image_format" class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_format')) ?></label>
                    <select id="ad_image_format" name="format" class="mt-1 disabled:opacity-60 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <?php foreach (\App\Domain\Article\ArticleAdImageFormat::cases() as $format): ?>
                            <option value="<?= e($format->value) ?>"><?= e($format->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ad_image_count" class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_count')) ?></label>
                    <select id="ad_image_count" name="count" class="mt-1 disabled:opacity-60 rounded-md border border-slate-300 px-2 py-1.5 text-sm tabular-nums dark:border-slate-700 dark:bg-slate-900">
                        <?php for ($n = 1; $n <= \App\Application\Article\Command\GenerateArticleAdImageHandler::MAX_COUNT; $n++): ?>
                            <option value="<?= $n ?>"><?= $n ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php if (count($article->images) > 1): ?>
                    <div data-ad-image-source-wrapper>
                        <label for="ad_image_source" class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_source_photo')) ?></label>
                        <select id="ad_image_source" name="source_article_image_id" class="mt-1 disabled:opacity-60 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <option value=""><?= e(t('articles.show.ad_images_source_all', ['count' => (string) \App\Application\Article\Command\GenerateArticleAdImageHandler::MAX_REFERENCE_IMAGES])) ?></option>
                            <?php foreach ($article->images as $image): ?>
                                <option value="<?= e($image->id) ?>"><?= e(t('articles.show.ad_images_source_single', ['number' => (string) ($image->position + 1)])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="min-w-[220px] flex-1">
                    <label for="ad_image_instructions" class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_additional_instructions')) ?></label>
                    <input id="ad_image_instructions" type="text" name="additional_instructions" placeholder="<?= e(t('articles.show.ad_images_additional_instructions_placeholder')) ?>"
                           class="mt-1 w-full disabled:opacity-60 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                </div>
                <div class="space-y-1 pb-1">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="include_article_name" value="1" class="rounded border-slate-300 disabled:opacity-60 dark:border-slate-700">
                        <?= e(t('articles.show.ad_images_include_name')) ?>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300" title="<?= e(t('articles.show.ad_images_include_properties_hint')) ?>">
                        <input type="checkbox" name="include_properties" value="1" class="rounded border-slate-300 disabled:opacity-60 dark:border-slate-700">
                        <?= e(t('articles.show.ad_images_include_properties')) ?>
                    </label>
                    <?php $adImageHasPrice = $article->isBillable && $article->dailyRentalPrice !== null && trim($article->dailyRentalPrice) !== ''; ?>
                    <label class="flex items-center gap-2 text-sm <?= $adImageHasPrice ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500' ?>" title="<?= e(t($adImageHasPrice ? 'articles.show.ad_images_include_price_hint' : 'articles.show.ad_images_include_price_unavailable')) ?>">
                        <input type="checkbox" name="include_price" value="1" class="rounded border-slate-300 disabled:opacity-60 dark:border-slate-700"<?= $adImageHasPrice ? '' : ' disabled data-ad-image-unavailable' ?>>
                        <?= e(t('articles.show.ad_images_include_price')) ?>
                    </label>
                    <label class="ml-6 flex items-center gap-2 text-sm <?= $adImageHasPrice ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500' ?>" title="<?= e(t('articles.show.ad_images_price_as_from_hint')) ?>">
                        <input type="checkbox" name="price_as_from" value="1" class="rounded border-slate-300 disabled:opacity-60 dark:border-slate-700"<?= $adImageHasPrice ? ' checked' : ' disabled data-ad-image-unavailable' ?>>
                        <?= e(t('articles.show.ad_images_price_as_from')) ?>
                    </label>
                    <?php $adImageHasGallery = $article->images !== []; ?>
                    <label class="flex items-center gap-2 text-sm <?= $adImageHasGallery ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500' ?>" title="<?= e(t($adImageHasGallery ? 'articles.show.ad_images_use_gallery_hint' : 'articles.show.ad_images_use_gallery_unavailable')) ?>">
                        <input type="checkbox" name="use_gallery_images" value="1" data-ad-image-use-gallery class="rounded border-slate-300 disabled:opacity-60 dark:border-slate-700"<?= $adImageHasGallery ? ' checked' : ' disabled data-ad-image-unavailable' ?>>
                        <?= e(t('articles.show.ad_images_use_gallery')) ?>
                    </label>
                </div>
                <div>
                    <button type="submit" data-ad-image-submit
                            class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-wait disabled:border-brand-300 disabled:bg-brand-50 disabled:text-brand-700 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:disabled:border-brand-800 dark:disabled:bg-brand-950 dark:disabled:text-brand-300">
                        <span data-ad-image-spinner class="hidden"><?= $adImageSpinner ?></span>
                        <span data-ad-image-label><?= e(t('articles.show.ad_images_generate')) ?></span>
                        <span data-ad-image-busy-label class="hidden"><?= e(t('articles.show.ad_images_generating')) ?></span>
                    </button>
                    <p data-ad-image-hint class="mt-1 text-[11px] text-slate-400"><?= e(t('articles.show.ad_images_generating_hint')) ?></p>
                </div>
                <p data-ad-image-status role="status" aria-live="polite" class="hidden w-full text-xs text-brand-700 dark:text-brand-300">
                    <span data-ad-image-status-text></span>
                    <span data-ad-image-elapsed class="ml-1 tabular-nums text-slate-400 dark:text-slate-500"></span>
                </p>
            </form>

            <dialog data-ad-image-refine-dialog aria-labelledby="ad_image_refine_title" class="modal-panel w-full max-w-lg rounded-lg border border-slate-200 p-0 backdrop:bg-black/40 dark:border-slate-700 dark:bg-slate-900">
                <form method="post" action="" data-ad-image-refine-form data-no-loading-indicator data-overlay-label="<?= e(t('articles.show.ad_images_overlay_refine')) ?>" class="space-y-3 p-4">
                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                    <h3 id="ad_image_refine_title" class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e(t('articles.show.ad_images_refine_title')) ?></h3>
                    <div class="flex gap-3">
                        <img data-ad-image-refine-preview src="" alt="" class="h-24 w-24 shrink-0 rounded-md object-cover ring-1 ring-slate-200 dark:ring-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            <span data-ad-image-refine-format class="block font-medium text-slate-700 dark:text-slate-300"></span>
                            <?= e(t('articles.show.ad_images_refine_hint')) ?>
                        </p>
                    </div>
                    <div>
                        <label for="ad_image_refine_instructions" class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_refine_instructions')) ?></label>
                        <textarea id="ad_image_refine_instructions" name="instructions" rows="3" required maxlength="<?= \App\Application\Article\Command\RefineArticleAdImageHandler::MAX_INSTRUCTIONS_LENGTH ?>"
                                  placeholder="<?= e(t('articles.show.ad_images_refine_instructions_placeholder')) ?>"
                                  class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"></textarea>
                    </div>
                    <div>
                        <label for="ad_image_refine_count" class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_refine_count')) ?></label>
                        <select id="ad_image_refine_count" name="count" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm tabular-nums dark:border-slate-700 dark:bg-slate-900">
                            <?php for ($n = 1; $n <= \App\Application\Article\Command\GenerateArticleAdImageHandler::MAX_COUNT; $n++): ?>
                                <option value="<?= $n ?>"><?= $n ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <p data-ad-image-refine-hint class="text-[11px] text-slate-400"><?= e(t('articles.show.ad_images_generating_hint')) ?></p>
                    <div class="flex justify-end gap-2">
                        <button type="button" data-ad-image-refine-cancel
                                class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            <?= e(t('common.cancel')) ?>
                        </button>
                        <button type="submit" data-ad-image-refine-submit
                                class="inline-flex items-center gap-2 rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 disabled:cursor-wait disabled:opacity-70">
                            <span data-ad-image-refine-spinner class="hidden"><?= $adImageSpinner ?></span>
                            <span data-ad-image-refine-label><?= e(t('articles.show.ad_images_refine_submit')) ?></span>
                            <span data-ad-image-refine-busy class="hidden"><?= e(t('articles.show.ad_images_refining')) ?></span>
                        </button>
                    </div>
                </form>
            </dialog>
            <script>
            (function () {
                var dialog = document.querySelector('[data-ad-image-refine-dialog]');
                if (!dialog) return;
                var form = dialog.querySelector('[data-ad-image-refine-form]');
                var submit = form.querySelector('[data-ad-image-refine-submit]');
                var instructions = form.querySelector('[name="instructions"]');
                var hint = form.querySelector('[data-ad-image-refine-hint]');

                function setBusy(busy) {
                    submit.disabled = busy;
                    submit.toggleAttribute('aria-busy', busy);
                    form.querySelector('[data-ad-image-refine-spinner]').classList.toggle('hidden', !busy);
                    form.querySelector('[data-ad-image-refine-label]').classList.toggle('hidden', busy);
                    form.querySelector('[data-ad-image-refine-busy]').classList.toggle('hidden', !busy);
                }

                document.querySelectorAll('[data-ad-image-refine]').forEach(function (trigger) {
                    trigger.addEventListener('click', function () {
                        form.action = trigger.dataset.action;
                        dialog.querySelector('[data-ad-image-refine-preview]').src = trigger.dataset.src;
                        dialog.querySelector('[data-ad-image-refine-format]').textContent = trigger.dataset.format;
                        setBusy(false);
                        dialog.showModal();
                        instructions.focus();
                    });
                });
                dialog.querySelector('[data-ad-image-refine-cancel]').addEventListener('click', function () { dialog.close(); });
                // Keep the dialog open while the request runs (it takes a while).
                dialog.addEventListener('cancel', function (event) { if (submit.disabled) event.preventDefault(); });
                form.addEventListener('submit', function (event) {
                    if (event.defaultPrevented || submit.disabled) return;
                    var li = window.loadingIndicator;
                    var count = parseInt(form.querySelector('[name="count"]').value, 10) || 1;
                    if (li) li.start();
                    if (li && typeof li.showOverlay === 'function') {
                        li.showOverlay({ label: form.dataset.overlayLabel, detail: hint.textContent, maxMs: (150 + 60 * (count - 1)) * 1000 });
                    }
                    // Disable only after the browser has collected the form data.
                    setTimeout(function () { setBusy(true); }, 0);
                });
                window.addEventListener('pageshow', function (event) {
                    if (!event.persisted) return;
                    if (submit.disabled && window.loadingIndicator) {
                        window.loadingIndicator.stop();
                        if (typeof window.loadingIndicator.hideOverlay === 'function') window.loadingIndicator.hideOverlay();
                    }
                    setBusy(false);
                    dialog.close();
                });
            })();
            </script>

            <template data-ad-image-tile>
                <div class="w-32 space-y-1" data-ad-image-placeholder>
                    <div class="flex h-32 w-32 items-center justify-center rounded-md border border-dashed border-brand-300 bg-brand-50 text-brand-600 dark:border-brand-800 dark:bg-brand-950 dark:text-brand-400">
                        <span class="animate-pulse motion-reduce:animate-none"><?= str_replace('h-4 w-4', 'h-7 w-7', $adImageSpinner) ?></span>
                    </div>
                    <p class="truncate text-[11px] text-slate-500 dark:text-slate-400" data-ad-image-tile-format></p>
                    <p class="text-[11px] font-medium text-brand-700 dark:text-brand-400" data-ad-image-tile-label></p>
                </div>
            </template>

            <script>
            (function () {
                var form = document.querySelector('[data-ad-image-form]');
                if (!form) return;
                var countSelect = form.querySelector('[name="count"]');
                var formatSelect = form.querySelector('[name="format"]');
                var button = form.querySelector('[data-ad-image-submit]');
                var label = form.querySelector('[data-ad-image-label]');
                var gallery = document.querySelector('[data-ad-image-gallery]');
                var emptyText = document.querySelector('[data-ad-image-empty]');
                var tileTemplate = document.querySelector('[data-ad-image-tile]');
                var status = form.querySelector('[data-ad-image-status]');
                var elapsed = form.querySelector('[data-ad-image-elapsed]');
                var timer = null;

                function count() { return parseInt(countSelect.value, 10) || 1; }
                function fill(text, n) { return text.replace('{count}', String(n)); }
                function toggle(selector, visible) {
                    form.querySelector(selector).classList.toggle('hidden', !visible);
                }

                function updateLabel() {
                    var n = count();
                    label.textContent = n === 1 ? form.dataset.labelOne : fill(form.dataset.labelMany, n);
                }
                countSelect.addEventListener('change', updateLabel);
                updateLabel();

                // The photo choice only matters while gallery images are used.
                var useGallery = form.querySelector('[data-ad-image-use-gallery]');
                var sourceWrapper = form.querySelector('[data-ad-image-source-wrapper]');
                function updateSource() {
                    if (sourceWrapper) sourceWrapper.classList.toggle('hidden', !useGallery.checked);
                }
                if (useGallery) {
                    useGallery.addEventListener('change', updateSource);
                    updateSource();
                }

                var overlayDetail = null;
                function formatElapsed(s) { return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); }

                form.addEventListener('submit', function (event) {
                    if (event.defaultPrevented || button.disabled) return;
                    var n = count();
                    var formatLabel = formatSelect.options[formatSelect.selectedIndex].text;

                    // The form opts out of the global loading indicator (it would add a
                    // second spinner to the button and hide its large spinner after 30 s);
                    // show its top bar and large spinner here, for as long as the request
                    // may take (server timeout: 120 s + 60 s per additional image).
                    var li = window.loadingIndicator;
                    if (li) li.start();
                    if (li && typeof li.showOverlay === 'function') {
                        var shown = li.showOverlay({
                            label: n === 1 ? form.dataset.overlayOne : fill(form.dataset.overlayMany, n),
                            detail: form.querySelector('[data-ad-image-hint]').textContent,
                            maxMs: (150 + 60 * (n - 1)) * 1000,
                        });
                        overlayDetail = shown ? shown.querySelector('.page-loading-detail') : null;
                    }

                    // Disable only after the browser has collected the form data,
                    // otherwise disabled fields would be missing from the request.
                    setTimeout(function () {
                        button.disabled = true;
                        button.setAttribute('aria-busy', 'true');
                        toggle('[data-ad-image-spinner]', true);
                        toggle('[data-ad-image-label]', false);
                        toggle('[data-ad-image-busy-label]', true);
                        toggle('[data-ad-image-hint]', false);
                        form.querySelectorAll('select, input[type="text"], input[type="checkbox"]').forEach(function (field) { field.disabled = true; });

                        var hintText = form.querySelector('[data-ad-image-hint]').textContent;
                        form.querySelector('[data-ad-image-status-text]').textContent =
                            n === 1 ? form.dataset.statusOne : fill(form.dataset.statusMany, n);
                        status.classList.remove('hidden');

                        if (emptyText) emptyText.classList.add('hidden');
                        gallery.classList.remove('hidden');
                        gallery.classList.add('flex');
                        for (var i = 0; i < n; i++) {
                            var tile = tileTemplate.content.cloneNode(true);
                            tile.querySelector('[data-ad-image-tile-format]').textContent = formatLabel;
                            tile.querySelector('[data-ad-image-tile-label]').textContent = form.dataset.tileLabel;
                            gallery.appendChild(tile);
                        }

                        var start = Date.now();
                        var tick = function () {
                            var s = Math.floor((Date.now() - start) / 1000);
                            elapsed.textContent = formatElapsed(s);
                            if (overlayDetail) overlayDetail.textContent = hintText + ' (' + formatElapsed(s) + ')';
                        };
                        tick();
                        timer = setInterval(tick, 1000);
                    }, 0);
                });

                // Coming back via the browser's back button restores the page from
                // the bfcache with the loading state still visible; reset it.
                window.addEventListener('pageshow', function (event) {
                    if (!event.persisted) return;
                    clearInterval(timer);
                    if (button.disabled && window.loadingIndicator) {
                        window.loadingIndicator.stop();
                        if (typeof window.loadingIndicator.hideOverlay === 'function') window.loadingIndicator.hideOverlay();
                    }
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    toggle('[data-ad-image-spinner]', false);
                    toggle('[data-ad-image-label]', true);
                    toggle('[data-ad-image-busy-label]', false);
                    toggle('[data-ad-image-hint]', true);
                    status.classList.add('hidden');
                    form.querySelectorAll('select, input[type="text"], input[type="checkbox"]').forEach(function (field) {
                        if (!field.hasAttribute('data-ad-image-unavailable')) field.disabled = false;
                    });
                    gallery.querySelectorAll('[data-ad-image-placeholder]').forEach(function (tile) { tile.remove(); });
                    if (emptyText && !gallery.children.length) {
                        emptyText.classList.remove('hidden');
                        gallery.classList.add('hidden');
                        gallery.classList.remove('flex');
                    }
                });
            })();
            </script>
        <?= $sectionClose ?>

        <?php if (!$article->isBundle): /* Bundles haben keine Inventarnummern (Migration 111) */ ?>
        <!-- Inventurnummern -->
        <div id="inventurnummern" class="scroll-mt-20 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <?= e(t('articles.show.inventory_numbers_title')) ?>
                    <?php if ($detail->inventoryNumbers !== []): ?>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?= count($detail->inventoryNumbers) ?></span>
                    <?php endif; ?>
                </h2>
                <?php if ($detail->nextInventoryNumberPreview !== null): ?>
                    <button type="button" data-dialog-open="dlg-inventory-new"
                            class="inline-flex items-center gap-1 rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white transition-transform duration-100 hover:bg-brand-700 active:scale-[0.97] motion-reduce:transition-none">
                        <span aria-hidden="true" class="text-base leading-none">+</span> <?= e(t('articles.show.inventory_add_button')) ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($detail->inventoryNumbers === []): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.inventory_numbers_none')) ?></p>
            <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($inventoryPageEntries as $entry): ?>
                        <?php $inv = $entry->inventoryNumber; ?>
                        <div class="-mx-2 flex cursor-pointer flex-wrap items-center justify-between gap-2 rounded-md px-2 py-3 hover:bg-slate-50 dark:hover:bg-slate-800" data-row-link="/inventurnummern/<?= e($inv->id) ?>">
                            <div>
                                <a href="/inventurnummern/<?= e($inv->id) ?>" class="font-mono text-sm text-brand-700 hover:underline dark:text-brand-400"><?= e($inv->number) ?></a>
                                <?php if ($entry->sale !== null): ?>
                                    <span class="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600">
                                        <?= e(t('articles.show.sold_badge', [
                                            'date' => $entry->sale->soldAt->format('d.m.Y'),
                                            'price' => number_format((float) $entry->sale->salePrice, 2, ',', '.'),
                                        ])) ?>
                                    </span>
                                <?php endif; ?>
                                <p class="text-xs text-slate-400 dark:text-slate-500">
                                    <?= e(t('articles.show.acquired_on')) ?>: <?= $inv->acquisitionDate !== null ? e($inv->acquisitionDate->format('d.m.Y')) : '–' ?>
                                    <?php if ($inv->receiptId !== null && isset($receiptById[$inv->receiptId])): ?>
                                        <?php $linkedReceiptEntry = $receiptById[$inv->receiptId]; ?>
                                        · <?= e(t('articles.show.receipt')) ?>:
                                        <a href="/belege/<?= e($linkedReceiptEntry->receipt->id) ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($linkedReceiptEntry->receipt->receiptNumber ?? $linkedReceiptEntry->receipt->receiptDate->format('d.m.Y')) ?></a>
                                        <?php if ($linkedReceiptEntry->supplier !== null): ?>
                                            · <?= e(t('articles.show.supplier')) ?>: <?= e($linkedReceiptEntry->supplier->name) ?>
                                        <?php endif; ?>
                                    <?php elseif ($inv->supplierId !== null && isset($supplierById[$inv->supplierId])): ?>
                                        · <?= e(t('articles.show.supplier')) ?>: <?= e($supplierById[$inv->supplierId]->name) ?>
                                    <?php endif; ?>
                                    <?php $effectivePrice = $inv->effectivePurchasePrice($article->purchasePrice); ?>
                                    <?php if ($effectivePrice !== null): ?>
                                        · <?= e(t('articles.show.purchase_price_short')) ?>: <?= e($effectivePrice) ?> €<?= $inv->purchasePriceOverride !== null ? ' (' . e(t('articles.show.deviating')) . ')' : '' ?>
                                    <?php endif; ?>
                                    <?php if ($inv->storageLocation !== null): ?>
                                        · <?= e(t('articles.show.storage_location')) ?>: <?= e($inv->storageLocation) ?>
                                    <?php endif; ?>
                                    <?php if ($inv->currentValue !== null): ?>
                                        · Zeitwert: <?= e(number_format((float) $inv->currentValue, 2, ',', '.')) ?> €<?= $inv->isCurrentValueAiEstimate() ? ' (KI)' : '' ?>
                                    <?php endif; ?>
                                    <?php if ($article->hasFirmware && $entry->installedFirmwareVersion !== null): ?>
                                        · <?= e(t('articles.show.installed_firmware_version')) ?>: <?= e($entry->installedFirmwareVersion->version) ?>
                                        <?php if ($entry->firmwareOutdated): ?>
                                            <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700"><?= e(t('articles.show.firmware_update_available_badge')) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                                <?php if ($networkOverview !== null && $networkOverview->assignmentsFor($inv->id) !== []): ?>
                                    <?= \App\Infrastructure\Http\View::render('networks/_assignment-list', ['assignments' => $networkOverview->assignmentsFor($inv->id), 'networks' => $networkOverview->networks]) ?>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="/inventurnummern/<?= e($inv->id) ?>/rma" class="inline-flex items-center gap-1 rounded-full <?= $entry->openRmaCount > 0 ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700' ?> px-2.5 py-1 text-xs font-medium hover:opacity-80">
                                    RMA
                                    <?php if ($entry->totalRmaCount > 0): ?>
                                        <span class="font-mono"><?= $entry->openRmaCount > 0 ? $entry->openRmaCount . ' ' . e(t('articles.show.open_count')) : $entry->totalRmaCount ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="<?= e($inventoryListUrl($inventoryPage, $inv->id)) ?>" data-inventory-edit class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                                <?php if ($entry->sale === null): ?>
                                    <form method="post" action="/inventurnummern/<?= e($inv->id) ?>/loeschen" data-confirm-key="articles.inventory_number.delete" data-confirm="<?= e(t('articles.show.confirm_delete_inventory_number')) ?>" data-confirm-style="danger">
                                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                        <input type="hidden" name="page" value="<?= $inventoryPage ?>">
                                        <input type="hidden" name="pageSize" value="<?= $inventoryPageSize ?>">
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($detail->inventoryNumbers) > min(\App\Infrastructure\Http\Pagination::ALLOWED_PAGE_SIZES)): ?>
                    <?= \App\Infrastructure\Http\View::render('partials/pagination', [
                        'page' => $inventoryPage,
                        'pageSize' => $inventoryPageSize,
                        'totalCount' => count($detail->inventoryNumbers),
                        'totalPages' => $inventoryTotalPages,
                        'paginationBaseUrl' => '/artikel/' . $article->id,
                        'paginationQuery' => $inventoryListQuery,
                        'paginationFragment' => 'inventurnummern',
                    ]) ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($detail->nextInventoryNumberPreview === null): ?>
                <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
                        <?= t('articles.show.inventory_numbers_missing_prefixes', ['link' => '<a href="/artikel/' . e($article->id) . '/bearbeiten" class="underline">' . e(t('articles.show.edit_article_link')) . '</a>']) ?>
                    </p>
                </div>
            <?php else: ?>
                <?php
                $invLabel = 'block text-xs font-medium text-slate-500 dark:text-slate-400';
                $invInput = 'mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900';
                $maxBatch = \App\Infrastructure\Http\Controllers\ArticleController::MAX_INVENTORY_BATCH;
                ?>
                <!-- New inventory numbers: modal instead of an always-visible form; several numbers at once -->
                <dialog id="dlg-inventory-new" aria-labelledby="dlg-inventory-new-title" class="modal-panel rounded-lg border border-slate-200 bg-white p-0 dark:border-slate-700 dark:bg-slate-900">
                    <form method="post" action="/artikel/<?= e($article->id) ?>/inventurnummern"
                          class="js-inventory-number-form js-inventory-batch-form space-y-5 p-4"
                          data-next-number="<?= e($detail->nextInventoryNumberPreview) ?>"
                          data-max="<?= $maxBatch ?>"
                          data-label-one="<?= e(t('articles.show.inventory_submit_one')) ?>"
                          data-label-many="<?= e(t('articles.show.inventory_submit_many')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <div>
                            <h2 id="dlg-inventory-new-title" class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100"><?= e(t('articles.show.inventory_modal_title')) ?></h2>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.inventory_modal_subtitle')) ?></p>
                        </div>

                        <div class="flex flex-wrap items-end gap-4 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                            <div>
                                <label for="inv-new-quantity" class="<?= $invLabel ?>"><?= e(t('articles.show.inventory_quantity')) ?></label>
                                <div class="mt-1 inline-flex items-stretch overflow-hidden rounded-md border border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-900">
                                    <button type="button" data-quantity-step="-1" aria-label="<?= e(t('articles.show.inventory_quantity_decrease')) ?>"
                                            class="px-3 text-lg leading-none text-slate-600 transition-transform duration-100 hover:bg-slate-100 active:scale-95 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800">−</button>
                                    <input id="inv-new-quantity" type="number" name="quantity" value="1" min="1" max="<?= $maxBatch ?>" step="1" inputmode="numeric" required
                                           class="w-16 border-x border-y-0 border-slate-300 py-1.5 text-center text-sm font-semibold tabular-nums [appearance:textfield] dark:border-slate-700 dark:bg-slate-900 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                    <button type="button" data-quantity-step="1" aria-label="<?= e(t('articles.show.inventory_quantity_increase')) ?>"
                                            class="px-3 text-lg leading-none text-slate-600 transition-transform duration-100 hover:bg-slate-100 active:scale-95 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800">+</button>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="<?= $invLabel ?>"><?= e(t('articles.show.inventory_preview')) ?></p>
                                <p class="mt-1 py-1.5 font-mono text-sm font-medium text-slate-800 dark:text-slate-200" data-inventory-preview aria-live="polite"><?= e($detail->nextInventoryNumberPreview) ?></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="inv-new-date" class="<?= $invLabel ?>"><?= e(t('articles.show.acquisition_date')) ?></label>
                                <input id="inv-new-date" type="date" name="acquisition_date" class="<?= $invInput ?>">
                            </div>
                            <div>
                                <label for="inv-new-location" class="<?= $invLabel ?>"><?= e(t('articles.show.storage_location')) ?></label>
                                <input id="inv-new-location" type="text" name="storage_location" maxlength="255" placeholder="<?= e(t('articles.show.storage_location_placeholder')) ?>" class="<?= $invInput ?>">
                            </div>
                            <div>
                                <label for="inv-new-receipt" class="<?= $invLabel ?>"><?= e(t('articles.show.receipt')) ?></label>
                                <select id="inv-new-receipt" name="receipt_id" class="js-receipt-select <?= $invInput ?>">
                                    <option value=""><?= e(t('articles.show.no_receipt')) ?></option>
                                    <?php foreach ($receipts as $receiptEntry): ?>
                                        <option value="<?= e($receiptEntry->receipt->id) ?>"><?= e($receiptLabel($receiptEntry->receipt)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="inv-new-supplier" class="<?= $invLabel ?>"><?= e(t('articles.show.supplier')) ?></label>
                                <select id="inv-new-supplier" name="supplier_id" class="js-supplier-select <?= $invInput ?> disabled:opacity-60">
                                    <option value=""><?= e(t('articles.show.no_supplier')) ?></option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= e($supplier->id) ?>"><?= e($supplier->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('articles.show.receipt_hint')) ?></p>
                            </div>
                            <div>
                                <label for="inv-new-price" class="<?= $invLabel ?>"><?= e(t('articles.show.purchase_price_override')) ?></label>
                                <input id="inv-new-price" type="number" step="0.01" min="0" name="purchase_price" placeholder="<?= e($article->purchasePrice ?? '–') ?>" class="<?= $invInput ?>">
                            </div>
                            <?php if ($article->hasFirmware): ?>
                                <div>
                                    <label for="inv-new-firmware" class="<?= $invLabel ?>"><?= e(t('articles.show.installed_firmware_version')) ?></label>
                                    <select id="inv-new-firmware" name="installed_firmware_version_id" class="<?= $invInput ?>">
                                        <option value=""><?= e(t('articles.show.installed_firmware_version_none')) ?></option>
                                        <?php foreach ($detail->firmwareVersions as $firmwareVersion): ?>
                                            <option value="<?= e($firmwareVersion->id) ?>"><?= e($firmwareVersion->version) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($networkAddForm !== null && !$networkAddForm->isEmpty()): ?>
                            <fieldset data-inventory-network class="m-0 min-w-0 border-0 p-0">
                                <?= \App\Infrastructure\Http\View::render('networks/_inventory-fields', ['form' => $networkAddForm, 'formId' => 'inv-new']) ?>
                            </fieldset>
                            <p data-inventory-network-hint hidden class="rounded-md bg-sky-50 px-3 py-2 text-xs text-sky-800 ring-1 ring-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-800"><?= e(t('articles.show.inventory_network_batch_hint')) ?></p>
                        <?php endif; ?>

                        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                            <button type="button" data-dialog-close class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 active:scale-[0.97] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.cancel')) ?></button>
                            <button type="submit" data-inventory-submit class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium tabular-nums text-white hover:bg-brand-700 active:scale-[0.97]"><?= e(t('articles.show.inventory_submit_one')) ?></button>
                        </div>
                    </form>
                </dialog>
            <?php endif; ?>

            <?php if ($editInventoryEntry !== null): ?>
                <?php
                $inv = $editInventoryEntry->inventoryNumber;
                $editLabel = 'block text-xs font-medium text-slate-500 dark:text-slate-400';
                $editInput = 'mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900';
                $articleFixedCurrentValue = \App\Infrastructure\Http\CurrentValueLookup::articleFixedValue($article->id);
                ?>
                <!-- Edit an inventory number: modal; opened on load (?edit_inventory=…) or loaded in place by the "Bearbeiten" link -->
                <dialog id="dlg-inventory-edit" data-auto-open data-close-url="<?= e($inventoryListUrl($inventoryPage)) ?>" aria-labelledby="dlg-inventory-edit-title" class="modal-panel rounded-lg border border-slate-200 bg-white p-0 dark:border-slate-700 dark:bg-slate-900">
                    <form method="post" action="/inventurnummern/<?= e($inv->id) ?>" class="js-inventory-number-form space-y-5 p-4">
                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                        <input type="hidden" name="page" value="<?= $inventoryPage ?>">
                        <input type="hidden" name="pageSize" value="<?= $inventoryPageSize ?>">
                        <div>
                            <h2 id="dlg-inventory-edit-title" class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100"><?= e(t('articles.show.inventory_edit_modal_title')) ?></h2>
                            <p class="mt-1 font-mono text-sm text-slate-600 dark:text-slate-400"><?= e($inv->number) ?></p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="inv-edit-date" class="<?= $editLabel ?>"><?= e(t('articles.show.acquisition_date')) ?></label>
                                <input id="inv-edit-date" type="date" name="acquisition_date" value="<?= e($inv->acquisitionDate?->format('Y-m-d')) ?>" class="<?= $editInput ?>">
                            </div>
                            <div>
                                <label for="inv-edit-location" class="<?= $editLabel ?>"><?= e(t('articles.show.storage_location')) ?></label>
                                <input id="inv-edit-location" type="text" name="storage_location" maxlength="255" placeholder="<?= e(t('articles.show.storage_location_placeholder')) ?>"
                                       value="<?= e($inv->storageLocation ?? '') ?>" class="<?= $editInput ?>">
                            </div>
                            <div>
                                <label for="inv-edit-receipt" class="<?= $editLabel ?>"><?= e(t('articles.show.receipt')) ?></label>
                                <select id="inv-edit-receipt" name="receipt_id" class="js-receipt-select <?= $editInput ?>">
                                    <option value=""><?= e(t('articles.show.no_receipt')) ?></option>
                                    <?php foreach ($receipts as $receiptEntry): ?>
                                        <option value="<?= e($receiptEntry->receipt->id) ?>" <?= $inv->receiptId === $receiptEntry->receipt->id ? 'selected' : '' ?>><?= e($receiptLabel($receiptEntry->receipt)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="inv-edit-supplier" class="<?= $editLabel ?>"><?= e(t('articles.show.supplier')) ?></label>
                                <select id="inv-edit-supplier" name="supplier_id" class="js-supplier-select <?= $editInput ?> disabled:opacity-60" <?= $inv->receiptId !== null ? 'disabled' : '' ?>>
                                    <option value=""><?= e(t('articles.show.no_supplier')) ?></option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= e($supplier->id) ?>" <?= $inv->supplierId === $supplier->id ? 'selected' : '' ?>><?= e($supplier->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('articles.show.receipt_hint')) ?></p>
                            </div>
                            <div>
                                <label for="inv-edit-price" class="<?= $editLabel ?>"><?= e(t('articles.show.purchase_price_override')) ?></label>
                                <input id="inv-edit-price" type="number" step="0.01" min="0" name="purchase_price" placeholder="<?= e($article->purchasePrice ?? '–') ?>"
                                       value="<?= e($inv->purchasePriceOverride ?? '') ?>" class="<?= $editInput ?>">
                            </div>
                            <div>
                                <label for="inv-edit-current-value" class="<?= $editLabel ?>">Zeitwert</label>
                                <input type="hidden" name="current_value_section" value="1">
                                <input id="inv-edit-current-value" type="number" step="0.01" min="0" name="current_value"
                                       placeholder="<?= e($articleFixedCurrentValue ?? '–') ?>"
                                       title="<?= e($articleFixedCurrentValue !== null ? 'Leer = fester Zeitwert des Artikels' : 'Geschätzter Zeitwert für die Versicherungsliste') ?>"
                                       value="<?= e($inv->currentValue ?? '') ?>" class="<?= $editInput ?>">
                            </div>
                            <?php if ($article->hasFirmware): ?>
                                <div>
                                    <label for="inv-edit-firmware" class="<?= $editLabel ?>"><?= e(t('articles.show.installed_firmware_version')) ?></label>
                                    <select id="inv-edit-firmware" name="installed_firmware_version_id" class="<?= $editInput ?>">
                                        <option value=""><?= e(t('articles.show.installed_firmware_version_none')) ?></option>
                                        <?php foreach ($detail->firmwareVersions as $firmwareVersion): ?>
                                            <option value="<?= e($firmwareVersion->id) ?>" <?= $inv->installedFirmwareVersionId === $firmwareVersion->id ? 'selected' : '' ?>><?= e($firmwareVersion->version) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($networkEditForm !== null && !$networkEditForm->isEmpty()): ?>
                            <?= \App\Infrastructure\Http\View::render('networks/_inventory-fields', ['form' => $networkEditForm, 'formId' => 'inv-edit']) ?>
                        <?php endif; ?>

                        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                            <button type="button" data-dialog-close class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 active:scale-[0.97] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.cancel')) ?></button>
                            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 active:scale-[0.97]"><?= e(t('common.save')) ?></button>
                        </div>
                    </form>
                </dialog>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="space-y-4">
        <!-- Verfügbarkeitskalender -->
        <?php if (!$article->isBundle): /* Verfügbarkeit gilt je Bestandteil (Migration 111) */ ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.availability_title')) ?></h2>
                <div class="flex items-center gap-1">
                    <a href="/artikel/<?= e($article->id) ?>?verf_monat=<?= e($availabilityPrevMonth) ?>" aria-label="<?= e(t('articles.show.previous_month')) ?>"
                       class="rounded-md border border-slate-300 p-1 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 010 1.06L8.06 11l4.73 4.71a.75.75 0 11-1.06 1.06l-5.25-5.25a.75.75 0 010-1.06l5.25-5.25a.75.75 0 011.06 0z" clip-rule="evenodd"/></svg>
                    </a>
                    <a href="/artikel/<?= e($article->id) ?>?verf_monat=<?= e($availabilityNextMonth) ?>" aria-label="<?= e(t('articles.show.next_month')) ?>"
                       class="rounded-md border border-slate-300 p-1 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 010-1.06L11.94 9 7.21 4.29a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06 0z" clip-rule="evenodd"/></svg>
                    </a>
                </div>
            </div>
            <p class="mb-2 text-xs text-slate-400 dark:text-slate-500">
                <?= e($availabilityMonthLabel) ?> · <?= $availability->totalInventory ?> <?= $availability->totalInventory === 1 ? e(t('articles.show.inventory_total')) : e(t('articles.show.inventory_total_plural')) ?>
            </p>

            <!--
                Echtes <table>-Element (wie beim Veranstaltungs-Kalender): garantiert
                7 gleich breite Spalten unabhängig von der Container-Breite und
                sieht dadurch sofort wie ein normaler Monatskalender aus, statt
                wie ein CSS-Grid, das in einer schmalen Sidebar leicht "kaputt"
                wirken kann.
            -->
            <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="w-full table-fixed text-center" data-no-sort>
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900">
                        <?php foreach ($availabilityWeekdayLabels as $i => $label): ?>
                            <th class="border border-slate-200 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400 <?= $i >= 5 ? 'bg-slate-100 dark:bg-slate-800' : '' ?>"><?= e($label) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($availabilityWeeks as $week): ?>
                        <tr>
                            <?php foreach ($week as $i => $day): ?>
                                <?php
                                $dayKey = $day->format('Y-m-d');
                                $inCurrentMonth = $day->format('Y-m') === $availabilityMonth->format('Y-m');
                                $isToday = $dayKey === $today;
                                $isWeekend = $i >= 5;
                                $status = $availability->statusForDay($dayKey);
                                $meta = $availabilityStatusMeta[$status];
                                ?>
                                <td class="h-11 w-[14.28%] border border-slate-200 p-0.5 align-middle dark:border-slate-800 <?= $isWeekend ? 'bg-slate-50/60 dark:bg-slate-950/40' : 'bg-white dark:bg-slate-900' ?> <?= $inCurrentMonth ? '' : 'opacity-50' ?>">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="text-[10px] <?= $isToday ? 'font-semibold text-brand-700 dark:text-brand-400' : 'text-slate-500 dark:text-slate-400' ?>"><?= (int) $day->format('j') ?></span>
                                        <span title="<?= e($meta['label']) ?>" class="flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-semibold ring-1 ring-inset <?= $meta['classes'] ?>"><?= $meta['icon'] ?></span>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <div class="mt-3 flex flex-col gap-1 text-[11px] text-slate-500 dark:text-slate-400">
                <?php foreach ($availabilityStatusMeta as $meta): ?>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-semibold ring-1 ring-inset <?= $meta['classes'] ?>"><?= $meta['icon'] ?></span>
                        <?= e($meta['label']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.upcoming_rentals_title')) ?></h2>
            <?php if ($upcomingRentals === []): ?>
                <p class="text-slate-500 dark:text-slate-400"><?= e(t('articles.show.upcoming_rentals_none')) ?></p>
            <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach (array_slice($upcomingRentals, 0, 6) as $entry): ?>
                        <a href="/veranstaltungen/<?= e($entry->rental->id) ?>" class="block py-2 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <p class="font-medium text-slate-800 dark:text-slate-200"><?= e($entry->rental->eventName) ?></p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                <?= e($entry->rental->startDate->format('d.m.Y')) ?> – <?= e($entry->rental->endDate->format('d.m.Y')) ?>
                                · <?= e($entry->customer->displayName()) ?> · <?= $entry->item->quantity ?>×
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($upcomingRentals) > 6): ?>
                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.more', ['count' => (string) (count($upcomingRentals) - 6)])) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.past_rentals_title')) ?></h2>
            <?php if ($pastRentals === []): ?>
                <p class="text-slate-500 dark:text-slate-400"><?= e(t('articles.show.past_rentals_none')) ?></p>
            <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach (array_slice($pastRentals, 0, 6) as $entry): ?>
                        <a href="/veranstaltungen/<?= e($entry->rental->id) ?>" class="block py-2 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <p class="font-medium text-slate-800 dark:text-slate-200"><?= e($entry->rental->eventName) ?></p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                <?= e($entry->rental->startDate->format('d.m.Y')) ?> – <?= e($entry->rental->endDate->format('d.m.Y')) ?>
                                · <?= e($entry->customer->displayName()) ?> · <?= $entry->item->quantity ?>×
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($pastRentals) > 6): ?>
                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.more', ['count' => (string) (count($pastRentals) - 6)])) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Secondary sections: collapsed by default, opened state is remembered per browser -->
        <div class="space-y-2">
            <h2 class="px-1 pt-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('articles.show.more_sections')) ?></h2>
        <!-- Zubehör -->
        <?= $sectionOpen('accessories', t('articles.show.accessories_title'), count($detail->accessories), $editAccessoryId !== null) ?>
            <p class="mb-3 text-xs text-slate-400 dark:text-slate-500">
                <?= e(t('articles.show.accessories_hint')) ?>
            </p>

            <?php if ($detail->accessories === []): ?>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.accessories_none')) ?></p>
            <?php else: ?>
                <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($detail->accessories as $accessory): ?>
                        <?php if ($editAccessoryId === $accessory->id): ?>
                            <form method="post" action="/zubehoer/<?= e($accessory->id) ?>" class="flex flex-wrap items-end gap-3 py-3">
                              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.accessory_quantity')) ?></label>
                                    <input type="number" name="quantity" min="1" value="<?= e((string) $accessory->quantity) ?>"
                                           class="mt-1 w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.accessory_description')) ?></label>
                                    <input type="text" name="description" value="<?= e($accessory->description) ?>" required
                                           class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                </div>
                                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
                                <a href="/artikel/<?= e($article->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
                            </form>
                        <?php else: ?>
                            <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <p class="text-sm text-slate-700 dark:text-slate-300">
                                    <span class="font-mono text-slate-500 dark:text-slate-400"><?= $accessory->quantity ?>×</span>
                                    <?= e($accessory->description) ?>
                                </p>
                                <div class="flex items-center gap-3">
                                    <a href="/artikel/<?= e($article->id) ?>?edit_accessory=<?= e($accessory->id) ?>" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                                    <form method="post" action="/zubehoer/<?= e($accessory->id) ?>/loeschen" data-confirm-key="articles.accessory.delete" data-confirm="<?= e(t('articles.show.confirm_delete_accessory')) ?>" data-confirm-style="danger">
                                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/artikel/<?= e($article->id) ?>/zubehoer" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.accessory_quantity')) ?></label>
                    <input type="number" name="quantity" min="1" value="1" class="mt-1 w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.accessory_description')) ?></label>
                    <input type="text" name="description" placeholder="z. B. Netzkabel" required class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                </div>
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <?= e(t('articles.show.add')) ?>
                </button>
            </form>
        <?= $sectionClose ?>


        <!-- Vor-Ausgabe-Checks -->
        <?= $sectionOpen('pre-issue-checks', t('articles.show.pre_issue_checks_title'), count($detail->preIssueChecks), $editPreIssueCheckId !== null) ?>
            <p class="mb-3 text-xs text-slate-400 dark:text-slate-500">
                <?= e(t('articles.show.pre_issue_checks_hint')) ?>
            </p>

            <?php if ($detail->preIssueChecks === []): ?>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.pre_issue_checks_none')) ?></p>
            <?php else: ?>
                <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($detail->preIssueChecks as $preIssueCheck): ?>
                        <?php if ($editPreIssueCheckId === $preIssueCheck->id): ?>
                            <form method="post" action="/vor-ausgabe-checks/<?= e($preIssueCheck->id) ?>" class="flex flex-wrap items-end gap-3 py-3">
                              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.pre_issue_check_description')) ?></label>
                                    <input type="text" name="description" value="<?= e($preIssueCheck->description) ?>" required
                                           class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                </div>
                                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
                                <a href="/artikel/<?= e($article->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
                            </form>
                        <?php else: ?>
                            <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <p class="text-sm text-slate-700 dark:text-slate-300"><?= e($preIssueCheck->description) ?></p>
                                <div class="flex items-center gap-3">
                                    <a href="/artikel/<?= e($article->id) ?>?edit_pre_issue_check=<?= e($preIssueCheck->id) ?>" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                                    <form method="post" action="/vor-ausgabe-checks/<?= e($preIssueCheck->id) ?>/loeschen" data-confirm-key="articles.pre_issue_check.delete" data-confirm="<?= e(t('articles.show.confirm_delete_pre_issue_check')) ?>" data-confirm-style="danger">
                                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/artikel/<?= e($article->id) ?>/vor-ausgabe-checks" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.pre_issue_check_description')) ?></label>
                    <input type="text" name="description" placeholder="z. B. Default Show geladen" required class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                </div>
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <?= e(t('articles.show.add')) ?>
                </button>
            </form>
        <?= $sectionClose ?>

        <!-- Firmware-Versionen -->
        <?php if ($article->hasFirmware): ?>
            <?= $sectionOpen('firmware', t('articles.show.firmware_versions_title'), count($detail->firmwareVersions)) ?>
                <p class="mb-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.firmware_versions_hint')) ?></p>

                <?php if ($detail->firmwareVersions === []): ?>
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.firmware_versions_none')) ?></p>
                <?php else: ?>
                    <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($detail->firmwareVersions as $i => $firmwareVersion): ?>
                            <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <p class="text-sm text-slate-700 dark:text-slate-300">
                                    <span class="font-mono font-medium text-slate-900 dark:text-slate-100"><?= e($firmwareVersion->version) ?></span>
                                    <?php if ($i === 0): ?>
                                        <span class="ml-2 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-700"><?= e(t('articles.show.firmware_latest_badge')) ?></span>
                                    <?php endif; ?>
                                    <?php if ($firmwareVersion->releasedAt !== null): ?>
                                        <span class="ml-2 text-xs text-slate-400 dark:text-slate-500"><?= e($firmwareVersion->releasedAt->format('d.m.Y')) ?></span>
                                    <?php endif; ?>
                                </p>
                                <form method="post" action="/firmware-versionen/<?= e($firmwareVersion->id) ?>/loeschen" data-confirm-key="articles.firmware.delete" data-confirm="<?= e(t('articles.show.confirm_delete_firmware_version')) ?>" data-confirm-style="danger">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/artikel/<?= e($article->id) ?>/firmware-versionen" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.firmware_version_label')) ?></label>
                        <input type="text" name="version" maxlength="100" required placeholder="z. B. 2.3.1"
                               class="mt-1 w-32 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.firmware_released_at')) ?></label>
                        <input type="date" name="released_at" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        <?= e(t('articles.show.add')) ?>
                    </button>
                </form>
            <?= $sectionClose ?>
        <?php endif; ?>

        <?php if (!$article->isBundle): /* Bundles haben keinen Verkaufsbestand (Migration 111) */ ?>
        <!-- Verkaufsbestand -->
        <?= $sectionOpen('sale-stock', t('articles.show.sale_stock_title'), $saleStockQuantity, ($saleStockErrors ?? []) !== []) ?>
            <p class="mb-3 text-xs text-slate-400 dark:text-slate-500">
                <?= e(t('articles.show.sale_stock_hint')) ?>
            </p>

            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                <?= e(t('articles.show.sale_stock_quantity', ['quantity' => (string) $saleStockQuantity])) ?>
            </p>

            <?php if ($saleStockLots === []): ?>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.sale_stock_none')) ?></p>
            <?php else: ?>
                <div class="mb-4 overflow-x-auto">
                    <table class="w-full min-w-[500px] text-left text-sm">
                        <thead class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-2 py-2"><?= e(t('articles.show.sale_stock_lot_received_at')) ?></th>
                                <th class="px-2 py-2"><?= e(t('articles.show.sale_stock_lot_remaining')) ?></th>
                                <th class="px-2 py-2"><?= e(t('articles.show.sale_stock_lot_received_quantity')) ?></th>
                                <th class="px-2 py-2 text-right"><?= e(t('articles.show.sale_stock_lot_unit_price')) ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php foreach ($saleStockLots as $lot): ?>
                                <?php if ($lot->quantityRemaining <= 0) continue; ?>
                                <tr>
                                    <td class="px-2 py-2 text-slate-600 dark:text-slate-400"><?= e($lot->receivedAt->format('d.m.Y')) ?></td>
                                    <td class="px-2 py-2 text-slate-800 dark:text-slate-200"><?= $lot->quantityRemaining ?></td>
                                    <td class="px-2 py-2 text-slate-600 dark:text-slate-400"><?= $lot->quantityReceived ?></td>
                                    <td class="px-2 py-2 text-right text-slate-600 dark:text-slate-400"><?= number_format((float) $lot->unitPurchasePrice, 2, ',', '.') ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($saleStockQuantity > 0): ?>
                <form method="post" action="/artikel/<?= e($article->id) ?>/verkaufsbestand-verkaufen" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.sale_stock_form_quantity')) ?></label>
                        <input type="number" name="quantity" min="1" max="<?= $saleStockQuantity ?>" value="1"
                               class="mt-1 w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.sale_stock_form_customer')) ?></label>
                        <select name="customer_id" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                            <option value=""><?= e(t('articles.show.sale_stock_form_no_customer')) ?></option>
                            <?php foreach ($saleCustomers as $saleCustomer): ?>
                                <option value="<?= e($saleCustomer->id) ?>"><?= e($saleCustomer->displayName()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.sale_stock_form_sale_price')) ?></label>
                        <input type="number" step="0.01" min="0" name="sale_price" required
                               class="mt-1 w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.sale_stock_form_shipping')) ?></label>
                        <input type="number" step="0.01" min="0" name="shipping_amount"
                               class="mt-1 w-24 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <div class="grow">
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.sale_stock_form_note')) ?></label>
                        <input type="text" name="note" maxlength="255"
                               class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                        <?= e(t('articles.show.sale_stock_form_submit')) ?>
                    </button>
                </form>
            <?php endif; ?>
        <?= $sectionClose ?>
        <?php endif; ?>

        <!-- Bedienungsanleitungen & Hinweisdokumente -->
        <?= $sectionOpen('notice-documents', t('articles.show.notice_documents_title'), count($detail->noticeDocuments) + count($detail->inheritedNoticeDocuments)) ?>
            <p class="mb-3 text-xs text-slate-400 dark:text-slate-500">
                <?= t('articles.show.notice_documents_hint', ['link' => '<a href="/hinweisdokumente" class="underline">' . e(t('articles.show.notice_documents_settings_link')) . '</a>']) ?>
            </p>

            <?php if ($detail->inheritedNoticeDocuments !== []): ?>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400"><?= e(t('articles.show.notice_documents_inherited')) ?></p>
                <div class="mb-3 divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($detail->inheritedNoticeDocuments as $inherited): ?>
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <p class="text-sm text-slate-700 dark:text-slate-300">
                                <?= e($inherited['document']->name) ?>
                                <span class="text-xs text-slate-400 dark:text-slate-500">· <a href="/artikel/<?= e($inherited['sourceArticleId']) ?>" class="hover:underline"><?= e($inherited['sourceLabel']) ?></a></span>
                            </p>
                            <a href="/hinweisdokumente/<?= e($inherited['document']->id) ?>/herunterladen" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('articles.show.notice_document_download')) ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($detail->noticeDocuments !== []): ?>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400"><?= e(t('articles.show.notice_documents_own')) ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($detail->noticeDocuments === [] && $detail->inheritedNoticeDocuments === []): ?>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.notice_documents_none')) ?></p>
            <?php elseif ($detail->noticeDocuments === []): ?>
            <?php else: ?>
                <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($detail->noticeDocuments as $noticeDocument): ?>
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <p class="text-sm text-slate-700 dark:text-slate-300"><?= e($noticeDocument->name) ?></p>
                            <div class="flex items-center gap-3">
                                <a href="/hinweisdokumente/<?= e($noticeDocument->id) ?>/herunterladen" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('articles.show.notice_document_download')) ?></a>
                                <form method="post" action="/artikel/<?= e($article->id) ?>/hinweisdokumente/<?= e($noticeDocument->id) ?>/entfernen" data-confirm-key="articles.notice_document.remove" data-confirm="<?= e(t('articles.show.confirm_remove_notice_document')) ?>" data-confirm-style="danger">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('articles.show.notice_document_remove')) ?></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/artikel/<?= e($article->id) ?>/hinweisdokumente" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.notice_document_name')) ?></label>
                    <input type="text" name="name" placeholder="<?= e(t('articles.show.notice_document_name_placeholder')) ?>" required
                           class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.notice_document_file')) ?></label>
                    <input type="file" name="document" accept="application/pdf,image/jpeg,image/png,image/webp" required
                           class="mt-1 block text-sm text-slate-700 dark:text-slate-300">
                </div>
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <?= e(t('articles.show.notice_document_upload')) ?>
                </button>
            </form>
            <p class="mt-1 text-[11px] text-slate-400"><?= e(t('articles.show.notice_document_file_hint')) ?></p>
        <?= $sectionClose ?>

        <!-- Verkaufskanäle -->
        <?= $sectionOpen('channels', t('articles.show.channels.title'), count(array_filter($detail->channelListings, static fn ($listing) => $listing->enabled))) ?>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                <?= t('articles.show.channels.hint', ['link' => '<a href="/einstellungen/integrationen" class="underline">' . e(t('articles.show.channels.settings_link')) . '</a>']) ?>
            </p>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach (\App\Domain\Article\SalesChannel::cases() as $channel): ?>
                    <?php $listing = $detail->channelListings[$channel->value] ?? null; ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($channel->label()) ?></p>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                <?php if ($listing !== null && $listing->enabled): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full <?= $listing->status->badgeClasses() ?> px-2 py-0.5 text-xs font-medium ring-1 ring-inset">
                                        <?= e($listing->status->label()) ?>
                                    </span>
                                    <?php if ($listing->externalLink !== null): ?>
                                        <a href="<?= e($listing->externalLink) ?>" target="_blank" rel="noopener" class="text-xs text-brand-700 hover:underline dark:text-brand-400">
                                            <?= e(t('articles.show.channels.link_label')) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($listing->status === \App\Domain\Article\ChannelSyncStatus::Failed && $listing->lastError !== null): ?>
                                        <span class="text-xs text-red-600 dark:text-red-400"><?= e($listing->lastError) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.channels.not_enabled')) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <?php if ($listing !== null && $listing->enabled): ?>
                                <form method="post" action="/artikel/<?= e($article->id) ?>/kanaele/<?= e($channel->value) ?>/synchronisieren">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <?= e(t('articles.show.channels.resync')) ?>
                                    </button>
                                </form>
                                <form method="post" action="/artikel/<?= e($article->id) ?>/kanaele/<?= e($channel->value) ?>/deaktivieren">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <?= e(t('articles.show.channels.disable')) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="/artikel/<?= e($article->id) ?>/kanaele/<?= e($channel->value) ?>/aktivieren">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="rounded-md bg-brand-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-brand-700">
                                        <?= e(t('articles.show.channels.enable')) ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?= $sectionClose ?>
        </div>
    </div>
</div>

<script>
(function () {
    // Progressive-Enhancement-Hinweis: sobald in einem der Inventurnummer-Formulare
    // (Zeile bearbeiten ODER neue Zeile anlegen) ein Beleg ausgewählt wird, wird das
    // Lieferanten-Feld DESSELBEN Formulars deaktiviert und zurückgesetzt, weil der
    // Lieferant dann ohnehin über den Beleg bestimmt wird (siehe
    // AddInventoryNumberHandler/UpdateInventoryNumberHandler). Nur eine UX-Hilfe —
    // die eigentliche Vorrangregel gilt serverseitig auch ohne JavaScript.
    // closest('form') statt fester IDs, damit die Bearbeiten-Zeile und das
    // "Neu anlegen"-Formular unabhängig voneinander funktionieren, auch wenn
    // beide gleichzeitig auf der Seite vorhanden sind.
    function initReceiptSync(root) {
    root.querySelectorAll('.js-inventory-number-form .js-receipt-select').forEach(function (receiptSelect) {
        var form = receiptSelect.closest('form');
        if (!form || receiptSelect.hasAttribute('data-receipt-sync-ready')) {
            return;
        }
        receiptSelect.setAttribute('data-receipt-sync-ready', '1');
        var supplierSelect = form.querySelector('.js-supplier-select');
        if (!supplierSelect) {
            return;
        }

        var syncSupplierField = function () {
            if (receiptSelect.value !== '') {
                supplierSelect.value = '';
                supplierSelect.disabled = true;
            } else {
                supplierSelect.disabled = false;
            }
        };

        receiptSelect.addEventListener('change', syncSupplierField);
        syncSupplierField();
    });
    }
    // Also used for the edit dialog that is loaded in place (see below).
    window.initInventoryReceiptSync = initReceiptSync;
    initReceiptSync(document);
})();
</script>
<script>
(function () {
    // Modal dialogs on this page ([data-dialog-open] -> <dialog id>).
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-dialog-open]');
        if (opener) {
            var dialog = document.getElementById(opener.getAttribute('data-dialog-open'));
            if (dialog && typeof dialog.showModal === 'function') {
                dialog.showModal();
                var first = dialog.querySelector('input:not([type=hidden]), select');
                if (first) { first.focus(); first.select && first.select(); }
            }
            return;
        }
        var closer = event.target.closest('[data-dialog-close]');
        if (closer) { closer.closest('dialog').close(); return; }
        // Click on the backdrop closes the dialog as well.
        if (event.target.tagName === 'DIALOG' && event.target.open) {
            var r = event.target.getBoundingClientRect();
            if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) {
                event.target.close();
            }
        }
    });

    // Edit dialog for inventory numbers. Without JavaScript the "Bearbeiten"
    // link reloads the page with ?edit_inventory=… and the dialog is rendered
    // server-side (opened on load via data-auto-open). With JavaScript the same
    // page is fetched and only its dialog is taken over, so the list keeps its
    // scroll position.
    var inventoryCard = document.getElementById('inventurnummern');
    function setupEditDialog(dialog) {
        if (window.initInventoryReceiptSync) { window.initInventoryReceiptSync(dialog); }
        if (window.initNetworkFields) { window.initNetworkFields(dialog); }
        dialog.addEventListener('close', function () {
            // Drop ?edit_inventory=… so a reload does not reopen the dialog.
            var closeUrl = dialog.getAttribute('data-close-url');
            if (closeUrl && window.location.search.indexOf('edit_inventory=') !== -1) {
                window.history.replaceState(null, '', closeUrl);
            }
        });
    }
    function showDialog(dialog) {
        if (typeof dialog.showModal !== 'function') { return; }
        dialog.showModal();
        var first = dialog.querySelector('input:not([type=hidden]), select');
        if (first) { first.focus(); }
    }
    var initialEditDialog = document.getElementById('dlg-inventory-edit');
    if (initialEditDialog) {
        setupEditDialog(initialEditDialog);
        if (initialEditDialog.hasAttribute('data-auto-open')) { showDialog(initialEditDialog); }
    }
    if (inventoryCard) {
        inventoryCard.addEventListener('click', function (event) {
            var link = event.target.closest('a[data-inventory-edit]');
            if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) { return; }
            event.preventDefault();
            event.stopPropagation(); // the row itself is a link to the inventory number page
            link.setAttribute('aria-busy', 'true');
            fetch(link.href, { credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    return res.text();
                })
                .then(function (html) {
                    var loaded = new DOMParser().parseFromString(html, 'text/html').getElementById('dlg-inventory-edit');
                    if (!loaded) { throw new Error('dialog missing'); }
                    var dialog = document.importNode(loaded, true);
                    var existing = document.getElementById('dlg-inventory-edit');
                    if (existing) { existing.remove(); }
                    inventoryCard.appendChild(dialog);
                    setupEditDialog(dialog);
                    showDialog(dialog);
                })
                .catch(function () { window.location.href = link.href; })
                .finally(function () { link.removeAttribute('aria-busy'); });
        }, true);

        // Page size of the inventory number list (plain navigation, no AJAX list here).
        inventoryCard.querySelectorAll('[data-page-size-select]').forEach(function (select) {
            select.addEventListener('change', function () {
                var params = new URLSearchParams(window.location.search);
                params.delete('edit_inventory');
                params.set('pageSize', select.value);
                params.set('page', '1');
                window.location.href = window.location.pathname + '?' + params.toString() + '#inventurnummern';
            });
        });
    }

    // Batch creation of inventory numbers: stepper, live preview of the
    // numbers that will be assigned, submit label and network fields
    // (only meaningful for a single device).
    var form = document.querySelector('.js-inventory-batch-form');
    if (form) {
        var input = form.querySelector('input[name="quantity"]');
        var preview = form.querySelector('[data-inventory-preview]');
        var submit = form.querySelector('[data-inventory-submit]');
        var network = form.querySelector('[data-inventory-network]');
        var networkHint = form.querySelector('[data-inventory-network-hint]');
        var max = parseInt(form.getAttribute('data-max'), 10) || 50;
        var next = form.getAttribute('data-next-number') || '';
        var match = next.match(/^(.*?)(\d+)$/);

        var numberAt = function (offset) {
            if (!match) { return next; }
            var n = String(parseInt(match[2], 10) + offset);
            while (n.length < match[2].length) { n = '0' + n; }
            return match[1] + n;
        };
        var quantity = function () {
            var q = parseInt(input.value, 10);
            return isNaN(q) ? 1 : Math.min(max, Math.max(1, q));
        };
        var render = function () {
            var q = quantity();
            preview.textContent = q === 1 || !match ? numberAt(0) : numberAt(0) + ' – ' + numberAt(q - 1);
            submit.textContent = q === 1
                ? form.getAttribute('data-label-one')
                : form.getAttribute('data-label-many').replace('{count}', String(q));
            form.querySelector('[data-quantity-step="-1"]').disabled = q <= 1;
            form.querySelector('[data-quantity-step="1"]').disabled = q >= max;
            if (network) {
                network.disabled = q > 1;
                network.hidden = q > 1;
                if (networkHint) { networkHint.hidden = q === 1; }
            }
        };
        form.querySelectorAll('[data-quantity-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                input.value = String(Math.min(max, Math.max(1, quantity() + parseInt(button.getAttribute('data-quantity-step'), 10))));
                render();
            });
        });
        input.addEventListener('input', render);
        input.addEventListener('blur', function () { input.value = String(quantity()); render(); });
        render();
    }

    // Collapsible sections: remember which ones are open (per browser).
    var storageKey = 'article-sections-open';
    var stored = [];
    try { stored = JSON.parse(window.localStorage.getItem(storageKey) || '[]') || []; } catch (e) { stored = []; }
    document.querySelectorAll('details[data-article-section]').forEach(function (section) {
        var key = section.getAttribute('data-article-section');
        if (stored.indexOf(key) !== -1) { section.open = true; }
        section.addEventListener('toggle', function () {
            if (section.hasAttribute('data-force-open')) { return; }
            var list = stored.filter(function (k) { return k !== key; });
            if (section.open) { list.push(key); }
            stored = list;
            try { window.localStorage.setItem(storageKey, JSON.stringify(list)); } catch (e) { /* storage unavailable */ }
        });
    });
})();
</script>
<script src="/assets/js/network-fields.js"></script>
<?php require __DIR__ . '/../_image_gallery_modal.php'; /* Lightbox for article and ad images */ ?>
