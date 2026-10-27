<?php
/** @var \App\Domain\Customer\Customer[] $customers */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($customers === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('customers.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 10%">
                <col style="width: 8%">
                <col style="width: 20%">
                <col style="width: 22%">
                <col style="width: 12%">
                <col style="width: 16%">
                <col style="width: 12%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5"><?= e(t('customers.table.number')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('customers.table.type')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('customers.table.name')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('customers.table.address')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('common.phone')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('common.email')) ?></th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($customers as $customer): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= e($customer->customerNumber) ?>
                        </td>
                        <td class="break-words px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $customer->type === \App\Domain\Customer\CustomerType::Geschaeftlich ? 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800' : 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600' ?>">
                                <?= e($customer->type->label()) ?>
                            </span>
                        </td>
                        <td class="break-words px-4 py-3">
                            <span class="font-medium text-slate-900 dark:text-slate-100"><?= e($customer->displayName()) ?></span>
                            <?php if ($customer->type === \App\Domain\Customer\CustomerType::Geschaeftlich): ?>
                                <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e(trim($customer->firstName . ' ' . $customer->lastName)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= e($customer->street) ?><?php if ($customer->addressLine2 !== null): ?>, <?= e($customer->addressLine2) ?><?php endif; ?>
                            <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e($customer->postalCode . ' ' . $customer->city) ?></span>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?php if ($customer->phone !== null && $customer->phone !== ''): ?>
                                <a href="tel:<?= e(preg_replace('/\s+/', '', $customer->phone)) ?>" class="hover:underline hover:text-brand-700 dark:hover:text-brand-400"><?= e($customer->phone) ?></a>
                            <?php else: ?>
                                –
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?php if ($customer->email !== null && $customer->email !== ''): ?>
                                <a href="mailto:<?= e($customer->email) ?>" class="hover:underline hover:text-brand-700 dark:hover:text-brand-400"><?= e($customer->email) ?></a>
                            <?php else: ?>
                                –
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-right">
                            <a href="/kunden/<?= e($customer->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                            <form method="post" action="/kunden/<?= e($customer->id) ?>/loeschen" data-confirm="<?= e(t('customers.confirm_delete')) ?>" data-confirm-style="danger" class="mt-1">
                              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
