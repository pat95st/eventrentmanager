<?php
/**
 * @var \App\Application\Network\Query\NetworkDetail $detail
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$network = $detail->network;
$input = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 font-mono text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$labelCls = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$v4 = $network->supports(4);
$v6 = $network->supports(6);
$hostRange = static function (int $version) use ($network): ?string {
    $range = $network->hostRange($version);

    return $range !== null ? inet_ntop($range[0]) . ' – ' . inet_ntop($range[1]) : null;
};
?>
<div class="mb-6">
    <a href="/einstellungen/netzwerke" class="text-xs text-brand-700 hover:underline dark:text-brand-400">← Alle Netzwerke</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($network->name) ?></h1>
    <p class="font-mono text-sm text-slate-500 dark:text-slate-400"><?= e($network->summary()) ?></p>
</div>

<div class="space-y-6">
    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Netzwerk</h2>
        <dl class="mb-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <?php if ($v4): ?>
                <div><dt class="text-xs text-slate-400 dark:text-slate-500">IPv4 (CIDR)</dt><dd class="font-mono text-slate-800 dark:text-slate-200"><?= e($network->cidr(4)) ?></dd></div>
                <div><dt class="text-xs text-slate-400 dark:text-slate-500">Subnetzmaske</dt><dd class="font-mono text-slate-800 dark:text-slate-200"><?= e($network->ipv4SubnetMask()) ?></dd></div>
                <div class="sm:col-span-2"><dt class="text-xs text-slate-400 dark:text-slate-500">Nutzbare IPv4-Adressen</dt><dd class="font-mono text-slate-800 dark:text-slate-200"><?= e($hostRange(4) ?? '–') ?></dd></div>
            <?php endif; ?>
            <?php if ($v6): ?>
                <div><dt class="text-xs text-slate-400 dark:text-slate-500">IPv6 (CIDR)</dt><dd class="font-mono text-slate-800 dark:text-slate-200"><?= e($network->cidr(6)) ?></dd></div>
                <div><dt class="text-xs text-slate-400 dark:text-slate-500">Standard-Präfixlänge</dt><dd class="font-mono text-slate-800 dark:text-slate-200">/<?= e((string) ($network->ipv6DefaultPrefix ?? $network->ipv6Prefix)) ?></dd></div>
                <div class="sm:col-span-2"><dt class="text-xs text-slate-400 dark:text-slate-500">Nutzbare IPv6-Adressen</dt><dd class="break-all font-mono text-slate-800 dark:text-slate-200"><?= e($hostRange(6) ?? '–') ?></dd></div>
            <?php endif; ?>
        </dl>

        <?php if (isset($errors['network'])): ?>
            <p class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800"><?= e($errors['network']) ?></p>
        <?php endif; ?>
        <details <?= $errors !== [] ? 'open' : '' ?>>
            <summary class="cursor-pointer text-sm font-medium text-brand-700 dark:text-brand-400">Netzwerk bearbeiten</summary>
            <form method="post" action="/einstellungen/netzwerke/<?= e($network->id) ?>" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <?= View::render('settings/_network-fields', ['network' => $network, 'errors' => $errors, 'old' => $old]) ?>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
                </div>
            </form>
            <?php if ($detail->addresses === []): ?>
                <form method="post" action="/einstellungen/netzwerke/<?= e($network->id) ?>/loeschen" class="mt-3 text-right" data-confirm-key="settings.networks.delete" data-confirm="Netzwerk „<?= e($network->name) ?>“ löschen? Die Adressbereiche der Kategorien werden mit entfernt." data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Netzwerk löschen</button>
                </form>
            <?php endif; ?>
        </details>
    </section>

    <section id="kategorien" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">Adressbereiche je Kategorie</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
            Geräte dieser Kategorien (und ihrer Unterkategorien ohne eigenen Bereich) bekommen standardmäßig eine Adresse aus diesem Netzwerk und Bereich.
            Bei der Adressvergabe lässt sich das überschreiben.
        </p>
        <?php if ($detail->ranges !== []): ?>
            <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($detail->ranges as $entry): ?>
                    <?php $range = $entry['range']; ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div>
                            <p class="text-sm text-slate-800 dark:text-slate-200"><?= e($entry['category']) ?></p>
                            <p class="font-mono text-xs text-slate-500 dark:text-slate-400">
                                <?php if (($label = $range->label(4)) !== null): ?>IPv4 <?= e($label) ?><?php endif; ?>
                                <?php if (($label6 = $range->label(6)) !== null): ?><?= $range->label(4) !== null ? ' · ' : '' ?>IPv6 <?= e($label6) ?><?php endif; ?>
                            </p>
                        </div>
                        <form method="post" action="/einstellungen/netzwerke/<?= e($network->id) ?>/kategorien/<?= e($range->categoryId) ?>/loeschen" data-confirm-key="settings.networks.category.delete" data-confirm="Adressbereich der Kategorie „<?= e($entry['category']) ?>“ entfernen?" data-confirm-style="danger">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/einstellungen/netzwerke/<?= e($network->id) ?>/kategorien" class="rounded-md border border-dashed border-slate-300 p-3 dark:border-slate-700">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Bereich hinzufügen oder ändern (eine bereits eingetragene Kategorie wird überschrieben):</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 <?= $v4 && $v6 ? 'lg:grid-cols-5' : 'lg:grid-cols-3' ?>">
                <div>
                    <label class="<?= $labelCls ?>" for="range-category">Kategorie *</label>
                    <select id="range-category" name="category_id" required class="<?= $input ?> font-sans">
                        <option value="">– bitte wählen –</option>
                        <?php foreach ($detail->categoryOptions as $categoryId => $categoryLabel): ?>
                            <option value="<?= e($categoryId) ?>"><?= e($categoryLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($v4): ?>
                    <div>
                        <label class="<?= $labelCls ?>" for="range-v4-start">IPv4 von</label>
                        <input id="range-v4-start" name="ipv4_start" placeholder="<?= e($network->hostRange(4) !== null ? inet_ntop($network->hostRange(4)[0]) : '') ?>" class="<?= $input ?>">
                    </div>
                    <div>
                        <label class="<?= $labelCls ?>" for="range-v4-end">IPv4 bis</label>
                        <input id="range-v4-end" name="ipv4_end" placeholder="<?= e($network->hostRange(4) !== null ? inet_ntop($network->hostRange(4)[1]) : '') ?>" class="<?= $input ?>">
                    </div>
                <?php endif; ?>
                <?php if ($v6): ?>
                    <div>
                        <label class="<?= $labelCls ?>" for="range-v6-start">IPv6 von</label>
                        <input id="range-v6-start" name="ipv6_start" placeholder="z. B. <?= e($network->ipv6Network) ?>100" class="<?= $input ?>">
                    </div>
                    <div>
                        <label class="<?= $labelCls ?>" for="range-v6-end">IPv6 bis</label>
                        <input id="range-v6-end" name="ipv6_end" placeholder="z. B. <?= e($network->ipv6Network) ?>1ff" class="<?= $input ?>">
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-3 text-right">
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Bereich speichern</button>
            </div>
        </form>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Vergebene Adressen (<?= count($detail->addresses) ?>)</h2>
        <?php if ($detail->addresses === []): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">In diesem Netzwerk sind noch keine Adressen vergeben.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 dark:text-slate-400">
                            <?php if ($v4): ?><th class="py-1 pr-3 font-medium">IPv4</th><?php endif; ?>
                            <?php if ($v6): ?><th class="py-1 pr-3 font-medium">IPv6</th><?php endif; ?>
                            <th class="py-1 pr-3 font-medium">Inventarnummer</th>
                            <th class="py-1 pr-3 font-medium">Artikel</th>
                            <th class="py-1 pr-3 font-medium">Schnittstelle</th>
                            <th class="py-1 font-medium">MAC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($detail->addresses as $entry): ?>
                            <?php $a = $entry->assignment; ?>
                            <tr>
                                <?php if ($v4): ?><td class="py-1.5 pr-3 font-mono text-slate-800 dark:text-slate-200"><?= e($a->ipv4Address ?? '–') ?></td><?php endif; ?>
                                <?php if ($v6): ?><td class="py-1.5 pr-3 font-mono text-slate-800 dark:text-slate-200"><?= e($a->ipv6Address ?? '–') ?></td><?php endif; ?>
                                <td class="py-1.5 pr-3"><a href="/inventurnummern/<?= e($a->inventoryNumberId) ?>" class="font-mono text-brand-700 hover:underline dark:text-brand-400"><?= e($entry->inventoryNumber) ?></a></td>
                                <td class="py-1.5 pr-3"><a href="/artikel/<?= e($entry->articleId) ?>" class="text-slate-700 hover:underline dark:text-slate-300"><?= e($entry->articleName) ?></a></td>
                                <td class="py-1.5 pr-3 text-slate-600 dark:text-slate-400"><?= e($a->label) ?></td>
                                <td class="py-1.5 font-mono text-slate-600 dark:text-slate-400"><?= e($a->macAddress ?? '–') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
