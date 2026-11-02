<?php
/** @var \App\Application\ArticleSet\Query\ArticleSetDetail $detail */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$set = $detail->set;
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/sets" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('sets.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($set->name) ?></h1>
        <div class="mt-2 flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800"><?= number_format((float) $set->price, 2, ',', '.') ?> € · <?= e(t('sets.days_badge', ['days' => (string) $set->deploymentDays])) ?></span>
            <?php if (!$set->isActive): ?><span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600"><?= e(t('sets.inactive')) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <a href="/sets/<?= e($set->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
        <form method="post" action="/sets/<?= e($set->id) ?>/loeschen" data-confirm="<?= e(t('sets.confirm_delete')) ?>" data-confirm-style="danger">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('sets.form.items_title')) ?></h2>
            <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                <?php foreach ($detail->items as $item): ?>
                    <li class="flex items-center justify-between gap-2 py-2">
                        <a href="/artikel/<?= e($item['articleId']) ?>" class="text-slate-700 hover:underline dark:text-slate-300"><?= e($item['label']) ?></a>
                        <span class="text-slate-500 dark:text-slate-400"><?= (int) $item['quantity'] ?>×<?= $item['isBundle'] ? ' · ' . e(t('articles.badge.bundle')) : '' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($set->description !== null): ?>
                <p class="mt-4 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300"><?= e($set->description) ?></p>
            <?php endif; ?>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('sets.form.upgrades_title')) ?></h2>
            <?php if ($detail->upgrades === []): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('sets.upgrades_none')) ?></p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <?php foreach ($detail->upgrades as $upgrade): ?>
                        <li class="py-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-slate-700 dark:text-slate-300"><?= e($upgrade['name']) ?></span>
                                <span class="text-slate-700 dark:text-slate-300">+<?= number_format((float) $upgrade['price'], 2, ',', '.') ?> €</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400"><?= e(\App\Application\ArticleSet\Query\ArticleSetDetail::describe($upgrade['items'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('sets.form.rules_title')) ?></h2>
        <?php if ($detail->rules === []): ?>
            <p class="text-slate-500 dark:text-slate-400"><?= e(t('sets.rules_none')) ?></p>
        <?php else: ?>
            <ul class="list-disc space-y-1 pl-5 text-slate-600 dark:text-slate-300">
                <?php foreach ($detail->rules as $rule): ?>
                    <li><?= e(ucfirst($rule->describe())) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="mt-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('sets.rules_explanation', ['days' => (string) $set->deploymentDays])) ?></p>
    </div>
</div>
