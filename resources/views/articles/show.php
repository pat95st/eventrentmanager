<?php
/** @var \App\Application\Article\Query\ArticleDetail $detail */
/** @var string|null $editInventoryId */
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
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/artikel" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('articles.show.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(ArticleDisplayName::of($article, $detail->manufacturer)) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            <?php if ($detail->category !== null): ?><?= e($detail->category->name) ?><?php endif; ?>
            <?php if ($detail->manufacturer->prefix !== null && $article->prefix !== null): ?>
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
                    <?= e(t('articles.show.is_19_inch_badge')) ?><?= $article->heightUnits !== null ? ' · ' . $article->heightUnits . ' HE' : '' ?>
                </span>
            <?php endif; ?>
            <?php if ($article->hasFirmware): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600">
                    <?= e(t('articles.show.has_firmware_badge')) ?>
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
    </div>
    <div class="flex items-center gap-3">
        <a href="/artikel/<?= e($article->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('common.edit')) ?>
        </a>
        <?php if ($article->isActive): ?>
            <form method="post" action="/artikel/<?= e($article->id) ?>/deaktivieren" data-confirm="<?= e(t('articles.show.confirm_deactivate')) ?>">
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
        <form method="post" action="/artikel/<?= e($article->id) ?>/loeschen" data-confirm="<?= e(t('articles.show.confirm_delete')) ?>" data-confirm-style="danger">
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
                    <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.weight')) ?></dt>
                    <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $article->weightValue !== null ? e($article->weightValue . ' ' . $article->weightUnit) : '–' ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.dimensions')) ?></dt>
                    <dd class="font-mono text-slate-800 dark:text-slate-200"><?= e($article->dimensions ?? '–') ?></dd>
                </div>
                <?php if ($article->is19Inch): ?>
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
                <?php endif; ?>
            </dl>
            <?php if ($article->description !== null): ?>
                <div class="mt-4">
                    <dt class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.show.description')) ?></dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= e($article->description) ?></dd>
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

        <!-- Bilder -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.images_title')) ?></h2>
            <div class="flex flex-wrap gap-3">
                <?php foreach ($article->images as $image): ?>
                    <div class="relative h-24 w-24 overflow-hidden rounded-md ring-1 ring-slate-200 dark:ring-slate-700">
                        <img src="/artikel-bilder/<?= e($image->filePath) ?>" alt="" class="h-full w-full object-cover">
                        <form method="post" action="/artikelbilder/<?= e($image->id) ?>/loeschen" data-confirm="<?= e(t('articles.show.confirm_delete_image')) ?>" data-confirm-style="danger" class="absolute right-1 top-1">
                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                            <button type="submit" class="rounded-full bg-black/60 px-1.5 py-0.5 text-xs text-white hover:bg-black/80">×</button>
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
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.ad_images_title')) ?></h2>
            <p class="mb-3 text-xs text-slate-400 dark:text-slate-500">
                <?= t('articles.show.ad_images_hint', ['link' => '<a href="/einstellungen/ki-werbebilder" class="underline">' . e(t('articles.show.ad_images_settings_link')) . '</a>']) ?>
            </p>

            <?php if ($detail->adImages === []): ?>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_none')) ?></p>
            <?php else: ?>
                <div class="mb-4 flex flex-wrap gap-3">
                    <?php foreach ($detail->adImages as $adImage): ?>
                        <div class="w-32 space-y-1">
                            <div class="relative h-32 w-32 overflow-hidden rounded-md ring-1 ring-slate-200 dark:ring-slate-700">
                                <img src="/artikel-werbebilder/<?= e($adImage->filePath) ?>" alt="" class="h-full w-full object-cover">
                                <form method="post" action="/werbebilder/<?= e($adImage->id) ?>/loeschen" data-confirm="<?= e(t('articles.show.ad_images_confirm_delete')) ?>" data-confirm-style="danger" class="absolute right-1 top-1">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                    <button type="submit" class="rounded-full bg-black/60 px-1.5 py-0.5 text-xs text-white hover:bg-black/80">×</button>
                                </form>
                            </div>
                            <p class="truncate text-[11px] text-slate-500 dark:text-slate-400"><?= e($adImage->format->label()) ?></p>
                            <a href="/artikel-werbebilder/<?= e($adImage->filePath) ?>" download class="text-[11px] font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('articles.show.ad_images_download')) ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/artikel/<?= e($article->id) ?>/werbebilder" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_format')) ?></label>
                    <select name="format" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <?php foreach (\App\Domain\Article\ArticleAdImageFormat::cases() as $format): ?>
                            <option value="<?= e($format->value) ?>"><?= e($format->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($article->images !== []): ?>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_source_photo')) ?></label>
                        <select name="source_article_image_id" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <option value=""><?= e(t('articles.show.ad_images_source_photo_none')) ?></option>
                            <?php foreach ($article->images as $image): ?>
                                <option value="<?= e($image->id) ?>"><?= e(t('articles.show.ad_images_source_photo')) ?> <?= $image->position + 1 ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="min-w-[220px] flex-1">
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.ad_images_additional_instructions')) ?></label>
                    <input type="text" name="additional_instructions" placeholder="<?= e(t('articles.show.ad_images_additional_instructions_placeholder')) ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                </div>
                <div>
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        <?= e(t('articles.show.ad_images_generate')) ?>
                    </button>
                    <p class="mt-1 text-[11px] text-slate-400"><?= e(t('articles.show.ad_images_generating_hint')) ?></p>
                </div>
            </form>
        </div>

        <!-- Bedienungsanleitungen & Hinweisdokumente -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.notice_documents_title')) ?></h2>
            <p class="mb-3 text-xs text-slate-400 dark:text-slate-500">
                <?= t('articles.show.notice_documents_hint', ['link' => '<a href="/hinweisdokumente" class="underline">' . e(t('articles.show.notice_documents_settings_link')) . '</a>']) ?>
            </p>

            <?php if ($detail->noticeDocuments === []): ?>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.notice_documents_none')) ?></p>
            <?php else: ?>
                <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($detail->noticeDocuments as $noticeDocument): ?>
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <p class="text-sm text-slate-700 dark:text-slate-300"><?= e($noticeDocument->name) ?></p>
                            <div class="flex items-center gap-3">
                                <a href="/hinweisdokumente/<?= e($noticeDocument->id) ?>/herunterladen" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('articles.show.notice_document_download')) ?></a>
                                <form method="post" action="/artikel/<?= e($article->id) ?>/hinweisdokumente/<?= e($noticeDocument->id) ?>/entfernen" data-confirm="<?= e(t('articles.show.confirm_remove_notice_document')) ?>" data-confirm-style="danger">
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
        </div>

        <!-- Verkaufskanäle -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.channels.title')) ?></h2>
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
        </div>

        <!-- Firmware-Versionen -->
        <?php if ($article->hasFirmware): ?>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.firmware_versions_title')) ?></h2>
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
                                <form method="post" action="/firmware-versionen/<?= e($firmwareVersion->id) ?>/loeschen" data-confirm="<?= e(t('articles.show.confirm_delete_firmware_version')) ?>" data-confirm-style="danger">
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
            </div>
        <?php endif; ?>

        <!-- Inventurnummern -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.inventory_numbers_title')) ?></h2>

            <?php if ($detail->inventoryNumbers === []): ?>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.show.inventory_numbers_none')) ?></p>
            <?php else: ?>
                <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($detail->inventoryNumbers as $entry): ?>
                        <?php $inv = $entry->inventoryNumber; ?>
                        <?php if ($editInventoryId === $inv->id): ?>
                            <form method="post" action="/inventurnummern/<?= e($inv->id) ?>" class="js-inventory-number-form flex flex-wrap items-end gap-3 py-3">
                              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.inventory_number_label')) ?></label>
                                    <p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 font-mono text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-400"><?= e($inv->number) ?></p>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.acquisition_date')) ?></label>
                                    <input type="date" name="acquisition_date" value="<?= e($inv->acquisitionDate?->format('Y-m-d')) ?>"
                                           class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.receipt')) ?></label>
                                    <select name="receipt_id" class="js-receipt-select mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                        <option value=""><?= e(t('articles.show.no_receipt')) ?></option>
                                        <?php foreach ($receipts as $receiptEntry): ?>
                                            <option value="<?= e($receiptEntry->receipt->id) ?>" <?= $inv->receiptId === $receiptEntry->receipt->id ? 'selected' : '' ?>><?= e($receiptLabel($receiptEntry->receipt)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.supplier')) ?></label>
                                    <select name="supplier_id" class="js-supplier-select mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700" <?= $inv->receiptId !== null ? 'disabled' : '' ?>>
                                        <option value=""><?= e(t('articles.show.no_supplier')) ?></option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?= e($supplier->id) ?>" <?= $inv->supplierId === $supplier->id ? 'selected' : '' ?>><?= e($supplier->name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('articles.show.receipt_hint')) ?></p>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.purchase_price_override')) ?></label>
                                    <input type="number" step="0.01" min="0" name="purchase_price" placeholder="<?= e($article->purchasePrice ?? '–') ?>"
                                           value="<?= e($inv->purchasePriceOverride ?? '') ?>"
                                           class="mt-1 w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.storage_location')) ?></label>
                                    <input type="text" name="storage_location" maxlength="255" placeholder="<?= e(t('articles.show.storage_location_placeholder')) ?>"
                                           value="<?= e($inv->storageLocation ?? '') ?>"
                                           class="mt-1 w-44 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                </div>
                                <?php if ($article->hasFirmware): ?>
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.installed_firmware_version')) ?></label>
                                        <select name="installed_firmware_version_id" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                            <option value=""><?= e(t('articles.show.installed_firmware_version_none')) ?></option>
                                            <?php foreach ($detail->firmwareVersions as $firmwareVersion): ?>
                                                <option value="<?= e($firmwareVersion->id) ?>" <?= $inv->installedFirmwareVersionId === $firmwareVersion->id ? 'selected' : '' ?>><?= e($firmwareVersion->version) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
                                <a href="/artikel/<?= e($article->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
                            </form>
                        <?php else: ?>
                            <div class="flex flex-wrap items-center justify-between gap-2 py-3">
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
                                        <?php if ($article->hasFirmware && $entry->installedFirmwareVersion !== null): ?>
                                            · <?= e(t('articles.show.installed_firmware_version')) ?>: <?= e($entry->installedFirmwareVersion->version) ?>
                                            <?php if ($entry->firmwareOutdated): ?>
                                                <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700"><?= e(t('articles.show.firmware_update_available_badge')) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <a href="/inventurnummern/<?= e($inv->id) ?>/rma" class="inline-flex items-center gap-1 rounded-full <?= $entry->openRmaCount > 0 ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700' ?> px-2.5 py-1 text-xs font-medium hover:opacity-80">
                                        RMA
                                        <?php if ($entry->totalRmaCount > 0): ?>
                                            <span class="font-mono"><?= $entry->openRmaCount > 0 ? $entry->openRmaCount . ' ' . e(t('articles.show.open_count')) : $entry->totalRmaCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <a href="/artikel/<?= e($article->id) ?>?edit_inventory=<?= e($inv->id) ?>" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                                    <?php if ($entry->sale === null): ?>
                                        <form method="post" action="/inventurnummern/<?= e($inv->id) ?>/loeschen" data-confirm="<?= e(t('articles.show.confirm_delete_inventory_number')) ?>" data-confirm-style="danger">
                                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                            <input type="hidden" name="article_id" value="<?= e($article->id) ?>">
                                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($detail->nextInventoryNumberPreview === null): ?>
                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
                        <?= t('articles.show.inventory_numbers_missing_prefixes', ['link' => '<a href="/artikel/' . e($article->id) . '/bearbeiten" class="underline">' . e(t('articles.show.edit_article_link')) . '</a>']) ?>
                    </p>
                </div>
            <?php else: ?>
                <form method="post" action="/artikel/<?= e($article->id) ?>/inventurnummern" class="js-inventory-number-form flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.new_inventory_number')) ?></label>
                        <p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 font-mono text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-400"><?= e($detail->nextInventoryNumberPreview) ?></p>
                        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('articles.show.assigned_automatically')) ?></p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.acquisition_date')) ?></label>
                        <input type="date" name="acquisition_date" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.receipt')) ?></label>
                        <select name="receipt_id" class="js-receipt-select mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                            <option value=""><?= e(t('articles.show.no_receipt')) ?></option>
                            <?php foreach ($receipts as $receiptEntry): ?>
                                <option value="<?= e($receiptEntry->receipt->id) ?>"><?= e($receiptLabel($receiptEntry->receipt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.supplier')) ?></label>
                        <select name="supplier_id" class="js-supplier-select mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                            <option value=""><?= e(t('articles.show.no_supplier')) ?></option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= e($supplier->id) ?>"><?= e($supplier->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('articles.show.receipt_hint')) ?></p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.purchase_price_override')) ?></label>
                        <input type="number" step="0.01" min="0" name="purchase_price" placeholder="<?= e($article->purchasePrice ?? '–') ?>"
                               class="mt-1 w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.storage_location')) ?></label>
                        <input type="text" name="storage_location" maxlength="255" placeholder="<?= e(t('articles.show.storage_location_placeholder')) ?>"
                               class="mt-1 w-44 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <?php if ($article->hasFirmware): ?>
                        <div>
                            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('articles.show.installed_firmware_version')) ?></label>
                            <select name="installed_firmware_version_id" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                <option value=""><?= e(t('articles.show.installed_firmware_version_none')) ?></option>
                                <?php foreach ($detail->firmwareVersions as $firmwareVersion): ?>
                                    <option value="<?= e($firmwareVersion->id) ?>"><?= e($firmwareVersion->version) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        <?= e(t('articles.show.add')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Verkaufsbestand -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.sale_stock_title')) ?></h2>
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
        </div>

        <!-- Zubehör -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.accessories_title')) ?></h2>
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
                                    <form method="post" action="/zubehoer/<?= e($accessory->id) ?>/loeschen" data-confirm="<?= e(t('articles.show.confirm_delete_accessory')) ?>" data-confirm-style="danger">
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
        </div>

        <!-- Vor-Ausgabe-Checks -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.pre_issue_checks_title')) ?></h2>
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
                                    <form method="post" action="/vor-ausgabe-checks/<?= e($preIssueCheck->id) ?>/loeschen" data-confirm="<?= e(t('articles.show.confirm_delete_pre_issue_check')) ?>" data-confirm-style="danger">
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
        </div>
    </div>

    <div class="space-y-4">
        <!-- Verfügbarkeitskalender -->
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
            <table class="w-full table-fixed text-center">
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

        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('articles.show.note_title')) ?></h2>
            <p class="text-slate-500 dark:text-slate-400">
                <?= e(t('articles.show.note_text')) ?>
            </p>
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
    document.querySelectorAll('.js-inventory-number-form .js-receipt-select').forEach(function (receiptSelect) {
        var form = receiptSelect.closest('form');
        if (!form) {
            return;
        }
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
})();
</script>
