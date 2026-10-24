<?php
/** @var \App\Domain\Printing\LabelPrinter[] $printers */
/** @var \App\Domain\Printing\LabelPrinter|null $editing */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var array<string, string> $availableDrivers */
/** @var int[] $availableTapeWidths */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.label_printers.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.label_printers.subtitle')) ?></p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($printers === []): ?>
            <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.label_printers.empty')) ?></p>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($printers as $printer): ?>
                    <?php $isEditing = $editing !== null && $editing->id === $printer->id; ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 px-2 py-3 <?= $isEditing ? 'bg-brand-50 rounded-md ring-1 ring-brand-200 dark:bg-brand-950/40 dark:ring-brand-800' : '' ?>">
                        <div>
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($printer->name) ?></p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                <?= e($availableDrivers[$printer->driverType] ?? $printer->driverType) ?>
                                · <?= e($printer->ipAddress) ?>:<?= e((string) $printer->port) ?>
                                · <?= e((string) $printer->tapeWidthMm) ?>&nbsp;mm
                            </p>
                        </div>
                        <details class="relative inline-block text-left">
                            <summary class="flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 14a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/></svg>
                                <span class="sr-only">Aktionen</span>
                            </summary>
                            <div class="absolute right-0 top-full z-10 mt-1 w-48 rounded-md border border-slate-200 bg-white py-1 text-left shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                <form method="post" action="/einstellungen/etikettendrucker/<?= e($printer->id) ?>/testdruck">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('settings.label_printers.test_print')) ?></button>
                                </form>
                                <a href="/einstellungen/etikettendrucker/<?= e($printer->id) ?>/bearbeiten" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
                                <form method="post" action="/einstellungen/etikettendrucker/<?= e($printer->id) ?>/loeschen" data-confirm="<?= e(t('settings.label_printers.confirm_delete')) ?>" data-confirm-style="danger">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
                                </form>
                            </div>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
            <?= $editing !== null ? e(t('settings.label_printers.edit')) : e(t('settings.label_printers.new')) ?>
        </h2>
        <form
            method="post"
            action="<?= $editing !== null ? '/einstellungen/etikettendrucker/' . e($editing->id) : '/einstellungen/etikettendrucker' ?>"
            class="space-y-4"
        >
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

            <div>
                <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.label_printers.field.name')) ?> *</label>
                <input type="text" id="name" name="name" required
                       value="<?= e($old['name'] ?? $editing?->name ?? '') ?>"
                       placeholder="z. B. Lager 1"
                       class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>

            <div>
                <label for="driver_type" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.label_printers.field.driver_type')) ?> *</label>
                <select id="driver_type" name="driver_type"
                        class="mt-1 w-full rounded-md border <?= isset($errors['driverType']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php $selectedDriver = $old['driver_type'] ?? $editing?->driverType ?? array_key_first($availableDrivers); ?>
                    <?php foreach ($availableDrivers as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $selectedDriver === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['driverType'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['driverType']) ?></p><?php endif; ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.label_printers.driver_type.hint')) ?></p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="ip_address" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.label_printers.field.ip_address')) ?> *</label>
                    <input type="text" id="ip_address" name="ip_address" required placeholder="z. B. 192.168.1.50"
                           value="<?= e($old['ip_address'] ?? $editing?->ipAddress ?? '') ?>"
                           class="mt-1 w-full rounded-md border <?= isset($errors['ipAddress']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if (isset($errors['ipAddress'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['ipAddress']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="port" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.label_printers.field.port')) ?> *</label>
                    <input type="number" id="port" name="port" required min="1" max="65535"
                           value="<?= e((string) ($old['port'] ?? $editing?->port ?? 9100)) ?>"
                           class="mt-1 w-full rounded-md border <?= isset($errors['port']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if (isset($errors['port'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['port']) ?></p><?php endif; ?>
                </div>
            </div>

            <div>
                <label for="tape_width_mm" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.label_printers.field.tape_width')) ?> *</label>
                <select id="tape_width_mm" name="tape_width_mm"
                        class="mt-1 w-full rounded-md border <?= isset($errors['tapeWidthMm']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php $selectedWidth = (int) ($old['tape_width_mm'] ?? $editing?->tapeWidthMm ?? 24); ?>
                    <?php foreach ($availableTapeWidths as $width): ?>
                        <option value="<?= e((string) $width) ?>" <?= $selectedWidth === $width ? 'selected' : '' ?>><?= e((string) $width) ?>&nbsp;mm</option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['tapeWidthMm'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['tapeWidthMm']) ?></p><?php endif; ?>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    <?= e(t('common.save')) ?>
                </button>
                <?php if ($editing !== null): ?>
                    <a href="/einstellungen/etikettendrucker" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
