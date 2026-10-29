<?php
/** @var \App\Application\Article\Query\ArticleListItem[] $articles */
/** @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers */
/** @var \App\Domain\Category\Category[] $categories */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use App\Domain\Article\ArticleDisplayName;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($articles === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2.5"><?= e(t('articles.table.product_name')) ?></th>
                    <th class="px-4 py-2.5"><?= e(t('articles.table.prefix')) ?></th>
                    <th class="px-4 py-2.5"><?= e(t('articles.table.category')) ?></th>
                    <th class="px-4 py-2.5"><?= e(t('articles.table.dimensions')) ?></th>
                    <th class="px-4 py-2.5"><?= e(t('articles.table.weight')) ?></th>
                    <th class="px-4 py-2.5"><?= e(t('articles.table.electrical_inspection')) ?></th>
                    <th class="px-4 py-2.5"><?= e(t('articles.table.billing')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($articles as $item): ?>
                    <?php
                    $article = $item->article;
                    $manufacturer = $item->manufacturer;
                    $category = $item->category;
                    $thumb = $article->images[0] ?? null;
                    ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/artikel/<?= e($article->id) ?>'">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <?php if ($thumb !== null): ?>
                                    <img src="/artikel-bilder/<?= e($thumb->filePath) ?>" alt="" class="h-9 w-9 rounded object-cover ring-1 ring-slate-200 dark:ring-slate-700">
                                <?php endif; ?>
                                <span class="font-medium text-slate-900 dark:text-slate-100"><?= e(ArticleDisplayName::of($article, $manufacturer)) ?></span>
                                <?php if (!$article->isActive): ?>
                                    <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-300 dark:bg-red-950/40 dark:text-red-400 dark:ring-red-700"><?= e(t('articles.table.inactive_badge')) ?></span>
                                <?php endif; ?>
                                <?php if ($article->isBundle): ?>
                                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800"><?= e(t('articles.badge.bundle')) ?></span>
                                <?php elseif (!$article->isRentableIndividually): ?>
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700"><?= e(t('articles.badge.not_rentable_individually')) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">
                            <?= ($manufacturer?->prefix !== null && $article->prefix !== null) ? e($manufacturer->prefix . '-' . $article->prefix) : '–' ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($category?->name ?? '–') ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= e($article->dimensions ?? '–') ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= $article->weightValue !== null ? e($article->weightValue . ' ' . $article->weightUnit) : '–' ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($article->requiresElectricalInspection): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700"><?= e(t('articles.table.electrical_inspection_required_short')) ?></span>
                            <?php else: ?>
                                <span class="text-xs text-slate-400 dark:text-slate-500">–</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($article->isBillable): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800">
                                    <?= $article->dailyRentalPrice !== null ? e($article->dailyRentalPrice) . ' ' . e(t('articles.table.per_day_short')) : e(t('articles.table.own_line_item')) ?>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">
                                    <?= e(t('articles.table.accessory')) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
