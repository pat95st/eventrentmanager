<?php
/** @var \App\Domain\Supplier\Supplier[] $suppliers */
/** @var string $search */

use App\Domain\Supplier\SupplierType;
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($suppliers === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">Keine Lieferanten gefunden.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 24%">
                <col style="width: 12%">
                <col style="width: 28%">
                <col style="width: 14%">
                <col style="width: 12%">
                <col style="width: 10%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5">Name</th>
                    <th class="break-words px-4 py-2.5">Art</th>
                    <th class="break-words px-4 py-2.5">Adresse</th>
                    <th class="break-words px-4 py-2.5">Telefon</th>
                    <th class="break-words px-4 py-2.5">E-Mail</th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($suppliers as $supplier): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= e($supplier->name) ?>
                            <?php if ($supplier->customerNumber !== null): ?>
                                <span class="block text-xs font-normal text-slate-400 dark:text-slate-500">Kd.-Nr. <?= e($supplier->customerNumber) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $supplier->type === SupplierType::Kauf ? 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800' : 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600' ?>">
                                <?= e($supplier->type->label()) ?>
                            </span>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= e($supplier->street) ?>
                            <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e($supplier->postalCode . ' ' . $supplier->city) ?></span>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?php if ($supplier->phone !== null && $supplier->phone !== ''): ?>
                                <a href="tel:<?= e(preg_replace('/\s+/', '', $supplier->phone)) ?>" class="hover:underline hover:text-brand-700 dark:hover:text-brand-400"><?= e($supplier->phone) ?></a>
                            <?php else: ?>
                                –
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?php if ($supplier->email !== null && $supplier->email !== ''): ?>
                                <a href="mailto:<?= e($supplier->email) ?>" class="hover:underline hover:text-brand-700 dark:hover:text-brand-400"><?= e($supplier->email) ?></a>
                            <?php else: ?>
                                –
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-right">
                            <a href="/lieferanten/<?= e($supplier->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                            <form method="post" action="/lieferanten/<?= e($supplier->id) ?>/loeschen" data-confirm="Diesen Lieferanten wirklich löschen?" data-confirm-style="danger" class="mt-1">
                              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
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
