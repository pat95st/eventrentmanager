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

// Schnellaktionen „Neu → Veranstaltung/Angebot/…“ nur für Benutzer, die diese
// Formulare auch öffnen dürfen (siehe Routen in bootstrap.php).
$canCreateDocuments = \App\Infrastructure\Http\Auth\CurrentUser::get()?->has(\App\Domain\User\Permission::AusgabeRuecknahme) ?? false;
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($customers === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('customers.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <!-- Wie die Artikelliste: automatische Spaltenbreiten, Mindestbreite und
             waagerechtes Scrollen auf kleinen Bildschirmen (Wrapper overflow-x-auto). -->
        <table class="w-full text-left text-sm" style="min-width:860px">
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="whitespace-nowrap px-4 py-2.5"><?= e(t('customers.table.number')) ?></th>
                    <th class="whitespace-nowrap px-4 py-2.5"><?= e(t('customers.table.type')) ?></th>
                    <th class="whitespace-nowrap px-4 py-2.5"><?= e(t('customers.table.name')) ?></th>
                    <th class="whitespace-nowrap px-4 py-2.5"><?= e(t('customers.table.address')) ?></th>
                    <th class="whitespace-nowrap px-4 py-2.5"><?= e(t('common.phone')) ?></th>
                    <th class="whitespace-nowrap px-4 py-2.5"><?= e(t('common.email')) ?></th>
                    <th class="whitespace-nowrap px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($customers as $index => $customer): ?>
                    <?php $openUpward = $index >= count($customers) - 2 && count($customers) > 3; ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= e($customer->customerNumber) ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium <?= $customer->type === \App\Domain\Customer\CustomerType::Geschaeftlich ? 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800' : 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600' ?>">
                                <?= e($customer->type->label()) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3" style="min-width:12rem">
                            <a href="/kunden/<?= e($customer->id) ?>/bearbeiten" class="font-medium text-slate-900 hover:text-brand-700 hover:underline dark:text-slate-100 dark:hover:text-brand-400"><?= e($customer->displayName()) ?></a>
                            <?php if ($customer->type === \App\Domain\Customer\CustomerType::Geschaeftlich): ?>
                                <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e(trim($customer->firstName . ' ' . $customer->lastName)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400" style="min-width:11rem">
                            <?= e($customer->street) ?><?php if ($customer->addressLine2 !== null): ?>, <?= e($customer->addressLine2) ?><?php endif; ?>
                            <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e($customer->postalCode . ' ' . $customer->city) ?></span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?php if ($customer->phone !== null && $customer->phone !== ''): ?>
                                <a href="tel:<?= e(preg_replace('/\s+/', '', $customer->phone)) ?>" class="hover:underline hover:text-brand-700 dark:hover:text-brand-400"><?= e($customer->phone) ?></a>
                            <?php else: ?>
                                –
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-400" style="max-width:12rem">
                            <?php if ($customer->email !== null && $customer->email !== ''): ?>
                                <a href="mailto:<?= e($customer->email) ?>" title="<?= e($customer->email) ?>" class="block truncate hover:underline hover:text-brand-700 dark:hover:text-brand-400"><?= e($customer->email) ?></a>
                            <?php else: ?>
                                –
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-1">
                                <?php if ($canCreateDocuments): ?>
                                    <details class="relative inline-block text-left" data-dropdown>
                                        <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                                            <?= e(t('customers.quick_actions.new')) ?>
                                        </summary>
                                        <div data-dropdown-menu role="menu" class="menu-fit absolute right-0 z-10 rounded-md border border-slate-200 bg-white py-1 text-left shadow-lg dark:border-slate-700 dark:bg-slate-900 <?= $openUpward ? 'bottom-full mb-1' : 'top-full mt-1' ?>">
                                            <p class="px-3 pb-1 pt-1.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('customers.quick_actions.for', ['name' => $customer->displayName()])) ?></p>
                                            <?php foreach ([
                                                ['/veranstaltungen/neu', 'customers.quick_actions.rental'],
                                                ['/angebote/neu', 'customers.quick_actions.offer'],
                                                ['/auftragsbestaetigungen/neu', 'customers.quick_actions.order_confirmation'],
                                                ['/rechnungen/neu', 'customers.quick_actions.invoice'],
                                            ] as [$quickHref, $quickLabel]): ?>
                                                <a role="menuitem" href="<?= e($quickHref) ?>?kunde=<?= e(rawurlencode($customer->id)) ?>" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t($quickLabel)) ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>
                                <details class="relative inline-block text-left" data-dropdown>
                                    <summary class="flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 14a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/></svg>
                                        <span class="sr-only"><?= e(t('customers.quick_actions.more')) ?></span>
                                    </summary>
                                    <div data-dropdown-menu role="menu" class="menu-fit absolute right-0 z-10 rounded-md border border-slate-200 bg-white py-1 text-left shadow-lg dark:border-slate-700 dark:bg-slate-900 <?= $openUpward ? 'bottom-full mb-1' : 'top-full mt-1' ?>">
                                        <a role="menuitem" href="/kunden/<?= e($customer->id) ?>/bearbeiten" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
                                        <?php if ($canCreateDocuments): ?>
                                            <a role="menuitem" href="/veranstaltungen?kunde=<?= e(rawurlencode($customer->id)) ?>" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('customers.quick_actions.show_rentals')) ?></a>
                                        <?php endif; ?>
                                        <form method="post" action="/kunden/<?= e($customer->id) ?>/loeschen" data-confirm="<?= e(t('customers.confirm_delete')) ?>" data-confirm-style="danger" data-confirm-ok="<?= e(t('customers.quick_actions.delete')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                            <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
