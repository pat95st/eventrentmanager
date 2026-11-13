<?php
/** @var \App\Application\Dashboard\DashboardTile[] $tiles aufeinanderfolgende Zähler-Kacheln, als eine Karte dargestellt */
/** @var array<string, array{count: int, items: array<int, array{label: string, sub: string, href: ?string}>, href: string}> $sections */

use App\Domain\Dashboard\DashboardWidget;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>
<section class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
<?php foreach ($tiles as $tile): ?>
    <?php
    $widget = $tile->widget;
    $section = $sections[$tile->key];
    ?>
    <?php if ($widget === DashboardWidget::IncomeExpense): ?>
        <?php $income = $section['items'][0] ?? null; ?>
        <a href="<?= e($section['href']) ?>" class="block px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50">
            <span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($tile->label) ?></span>
            <?php if ($income !== null): ?>
                <span class="mt-0.5 block text-base font-semibold text-slate-900 dark:text-slate-100"><?= e($income['label']) ?></span>
                <span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($income['sub']) ?></span>
            <?php endif; ?>
        </a>
        <?php continue; ?>
    <?php endif; ?>
    <div class="px-5 py-3">
        <a href="<?= e($section['href']) ?>" class="group flex items-center justify-between gap-3 text-sm">
            <span class="font-medium text-slate-700 group-hover:text-brand-700 dark:text-slate-300 dark:group-hover:text-brand-400"><?= e($tile->label) ?></span>
            <span class="font-semibold tabular-nums <?= $section['count'] === 0 ? 'text-slate-300 dark:text-slate-600' : ($widget === DashboardWidget::OverdueInvoices ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100') ?>"><?= e((string) $section['count']) ?></span>
        </a>
        <?php if ($section['items'] !== []): ?>
            <ul class="mt-1.5 space-y-1">
                <?php foreach (array_slice($section['items'], 0, 3) as $item): ?>
                    <li class="truncate text-xs text-slate-500 dark:text-slate-400">
                        <?php if ($item['href'] !== null): ?>
                            <a href="<?= e($item['href']) ?>" class="font-medium text-slate-600 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400"><?= e($item['label']) ?></a>
                        <?php else: ?>
                            <span class="font-medium text-slate-600 dark:text-slate-300"><?= e($item['label']) ?></span>
                        <?php endif; ?>
                        <?php if ($item['sub'] !== ''): ?> <span><?= e($item['sub']) ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($section['count'] > 3): ?>
                <a href="<?= e($section['href']) ?>" class="mt-1 block text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                    <?= e(t('dashboard.more_count', ['count' => (string) ($section['count'] - 3)])) ?>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</section>
