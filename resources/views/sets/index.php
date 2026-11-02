<?php
/** @var \App\Application\ArticleSet\Query\ArticleSetDetail[] $sets */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('sets.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('sets.subtitle')) ?></p>
    </div>
    <a href="/sets/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">+ <?= e(t('sets.new')) ?></a>
</div>

<?php if ($sets === []): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"><?= e(t('sets.none')) ?></div>
<?php else: ?>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium"><?= e(t('sets.form.name')) ?></th>
                    <th class="px-4 py-2 font-medium"><?= e(t('sets.list.contents')) ?></th>
                    <th class="px-4 py-2 font-medium"><?= e(t('sets.list.rules')) ?></th>
                    <th class="px-4 py-2 text-right font-medium"><?= e(t('sets.list.days')) ?></th>
                    <th class="px-4 py-2 text-right font-medium"><?= e(t('sets.list.price')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($sets as $detail): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="px-4 py-2">
                            <a href="/sets/<?= e($detail->set->id) ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e($detail->set->name) ?></a>
                            <?php if (!$detail->set->isActive): ?><span class="ml-1 text-xs text-slate-400"><?= e(t('sets.inactive')) ?></span><?php endif; ?>
                            <?php if ($detail->upgrades !== []): ?><div class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('sets.list.upgrades', ['count' => (string) count($detail->upgrades)])) ?></div><?php endif; ?>
                        </td>
                        <td class="px-4 py-2 text-slate-600 dark:text-slate-300"><?= e($detail->contentsDescription()) ?></td>
                        <td class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400"><?= e($detail->rulesDescription() !== '' ? $detail->rulesDescription() : t('sets.rules_none')) ?></td>
                        <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300"><?= (int) $detail->set->deploymentDays ?></td>
                        <td class="px-4 py-2 text-right font-medium text-slate-800 dark:text-slate-200"><?= number_format((float) $detail->set->price, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
