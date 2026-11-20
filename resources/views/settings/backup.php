<?php
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var \App\Domain\Backup\BackupSettings $settings */
/** @var \App\Domain\DocumentStorage\DocumentStorageTarget[] $storageTargets */
/** @var list<\App\Domain\Backup\BackupRun> $runs */
/** @var string $cronLine */
/** @var string $serverTime */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, string $fallback = '') => array_key_exists($key, $old) && is_scalar($old[$key]) ? (string) $old[$key] : $fallback;
$hasOld = array_key_exists('backup_interval', $old);
$autoEnabled = $hasOld ? ($old['backup_enabled'] ?? '') === '1' : $settings->enabled;
$selectedTargets = $hasOld && is_array($old['backup_storage_targets'] ?? null) ? $old['backup_storage_targets'] : $settings->storageTargetIds;
$selectedInterval = $val('backup_interval', $settings->interval->value);
$inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$fieldError = function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
};
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.backup.title')) ?></h1>
</div>

<div class="space-y-6">
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.backup.export.title')) ?></h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.backup.export.description')) ?></p>

        <form method="post" action="/einstellungen/sicherung/export">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('settings.backup.export.button')) ?>
            </button>
        </form>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.backup.automatic.title')) ?></h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.backup.automatic.description')) ?></p>

        <?php if ($storageTargets === []): ?>
            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                <?= e(t('settings.backup.automatic.no_targets')) ?>
                <a href="/einstellungen/speicherziele" class="font-medium underline"><?= e(t('settings.backup.automatic.no_targets_link')) ?></a>
            </div>
        <?php endif; ?>

        <form method="post" action="/einstellungen/sicherung/automatisch" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="backup_enabled" value="1" <?= $autoEnabled ? 'checked' : '' ?>
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                <?= e(t('settings.backup.automatic.field.enabled')) ?>
            </label>

            <fieldset>
                <legend class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.automatic.field.targets')) ?></legend>
                <div class="mt-1 space-y-1">
                    <?php foreach ($storageTargets as $target): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="checkbox" name="backup_storage_targets[]" value="<?= e($target->id) ?>" <?= in_array($target->id, $selectedTargets, true) ? 'checked' : '' ?>
                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                            <?= e($target->name) ?>
                            <span class="text-xs text-slate-400 dark:text-slate-500">(<?= e($target->protocol->label()) ?><?= $target->enabled ? '' : ', ' . e(t('settings.backup.automatic.target_disabled')) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= $fieldError('backup_storage_targets') ?>
            </fieldset>

            <div>
                <label for="backup_target_path" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.automatic.field.target_path')) ?></label>
                <input type="text" id="backup_target_path" name="backup_target_path" maxlength="500"
                       value="<?= e($val('backup_target_path', $settings->targetPath)) ?>"
                       class="<?= $inputClass ?> font-mono">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.backup.automatic.field.target_path_hint')) ?></p>
                <?= $fieldError('backup_target_path') ?>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <div>
                    <label for="backup_interval" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.automatic.field.interval')) ?></label>
                    <select id="backup_interval" name="backup_interval" class="<?= $inputClass ?>">
                        <?php foreach (\App\Domain\Backup\BackupInterval::cases() as $option): ?>
                            <option value="<?= e($option->value) ?>" <?= $selectedInterval === $option->value ? 'selected' : '' ?>><?= e($option->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('backup_interval') ?>
                </div>
                <div>
                    <label for="backup_run_time" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.automatic.field.run_time')) ?></label>
                    <input type="time" id="backup_run_time" name="backup_run_time"
                           value="<?= e($val('backup_run_time', sprintf('%02d:%02d', $settings->hour, $settings->minute))) ?>"
                           class="<?= $inputClass ?>">
                    <?= $fieldError('backup_run_time') ?>
                </div>
                <div>
                    <label for="backup_weekday" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.automatic.field.weekday')) ?></label>
                    <select id="backup_weekday" name="backup_weekday" class="<?= $inputClass ?>">
                        <?php foreach (range(1, 7) as $number): ?>
                            <option value="<?= $number ?>" <?= $val('backup_weekday', (string) $settings->weekday) === (string) $number ? 'selected' : '' ?>><?= e(t('settings.backup.automatic.weekday.' . $number)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('backup_weekday') ?>
                </div>
                <div>
                    <label for="backup_month_day" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.automatic.field.month_day')) ?></label>
                    <input type="number" id="backup_month_day" name="backup_month_day" min="1" max="28"
                           value="<?= e($val('backup_month_day', (string) $settings->monthDay)) ?>"
                           class="<?= $inputClass ?>">
                    <?= $fieldError('backup_month_day') ?>
                </div>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.backup.automatic.field.schedule_hint')) ?></p>

            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('settings.backup.automatic.button.save')) ?>
            </button>
        </form>

        <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
            <h3 class="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-300"><?= e(t('settings.backup.automatic.cron.title')) ?></h3>
            <p class="mb-3 text-xs text-amber-700 dark:text-amber-400"><?= e(t('settings.backup.automatic.cron.explanation')) ?></p>
            <p class="mb-1 text-xs font-medium text-amber-800 dark:text-amber-300"><?= e(t('settings.backup.automatic.cron.line_label')) ?></p>
            <code class="block select-all break-all rounded bg-white px-3 py-2 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300"><?= e($cronLine) ?></code>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-amber-700 dark:text-amber-400">
                <li><?= e(sprintf(t('settings.backup.automatic.cron.server_time_hint'), $serverTime)) ?></li>
                <li><?= e(t('settings.backup.automatic.cron.php_binary_hint')) ?></li>
                <li><?= e(t('settings.backup.automatic.cron.saved_schedule_hint')) ?></li>
            </ul>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <form method="post" action="/einstellungen/sicherung/automatisch/ausfuehren">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" <?= $settings->storageTargetIds === [] ? 'disabled' : '' ?>>
                    <?= e(t('settings.backup.automatic.button.run_now')) ?>
                </button>
            </form>
            <p class="text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.backup.automatic.run_now_hint')) ?></p>
        </div>

        <h3 class="mb-2 mt-6 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.backup.automatic.runs.title')) ?></h3>
        <?php if ($runs === []): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.backup.automatic.runs.empty')) ?></p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" data-responsive>
                    <thead class="text-xs text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="py-2 pr-4 font-medium"><?= e(t('settings.backup.automatic.runs.started_at')) ?></th>
                            <th class="py-2 pr-4 font-medium"><?= e(t('settings.backup.automatic.runs.trigger')) ?></th>
                            <th data-priority="1" class="py-2 pr-4 font-medium"><?= e(t('settings.backup.automatic.runs.status')) ?></th>
                            <th class="py-2 font-medium"><?= e(t('settings.backup.automatic.runs.details')) ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($runs as $run): ?>
                            <?php $statusClass = match ($run->status) {
                                \App\Domain\Backup\BackupRunStatus::Success => 'text-green-700 dark:text-green-400',
                                \App\Domain\Backup\BackupRunStatus::Partial => 'text-amber-700 dark:text-amber-400',
                                \App\Domain\Backup\BackupRunStatus::Failed => 'text-red-700 dark:text-red-400',
                            }; ?>
                            <tr class="align-top">
                                <td class="whitespace-nowrap py-2 pr-4 text-slate-700 dark:text-slate-300"><?= e($run->startedAt->format('d.m.Y H:i')) ?></td>
                                <td class="py-2 pr-4 text-slate-600 dark:text-slate-400"><?= e($run->trigger->label()) ?></td>
                                <td class="whitespace-nowrap py-2 pr-4 font-medium <?= $statusClass ?>"><?= e($run->status->label()) ?></td>
                                <td class="py-2 text-xs text-slate-600 dark:text-slate-400">
                                    <?php if ($run->fileName !== null): ?>
                                        <div class="font-mono"><?= e($run->fileName) ?><?= $run->fileSize !== null ? ' (' . e(number_format($run->fileSize / 1048576, 1, ',', '.')) . ' MB)' : '' ?></div>
                                    <?php endif; ?>
                                    <?php if ($run->message !== null): ?>
                                        <div class="text-red-600 dark:text-red-400"><?= e($run->message) ?></div>
                                    <?php endif; ?>
                                    <?php foreach ($run->targetResults as $result): ?>
                                        <div class="<?= $result['success'] ? '' : 'text-red-600 dark:text-red-400' ?>">
                                            <?= $result['success'] ? '✓' : '✗' ?> <?= e($result['targetName']) ?>: <?= e($result['success'] ? $result['remotePath'] : $result['message']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.backup.import.title')) ?></h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.backup.import.description')) ?></p>

        <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400">
            <?= e(t('settings.backup.import.warning')) ?>
        </div>

        <form method="post" action="/einstellungen/sicherung/import" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

            <div>
                <label for="backup_zip" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.import.field.file')) ?></label>
                <input type="file" id="backup_zip" name="backup_zip" accept=".zip,application/zip" required
                       class="mt-1 block w-full text-sm text-slate-700 dark:text-slate-300">
                <?php if (isset($errors['backup_zip'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['backup_zip']) ?></p><?php endif; ?>
            </div>

            <div>
                <label for="confirmation_text" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.import.field.confirmation')) ?></label>
                <input type="text" id="confirmation_text" name="confirmation_text" autocomplete="off"
                       value="<?= e($val('confirmation_text')) ?>"
                       class="mt-1 w-full max-w-xs rounded-md border <?= isset($errors['confirmation_text']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['confirmation_text'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['confirmation_text']) ?></p><?php endif; ?>
            </div>

            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                <?= e(t('settings.backup.import.button')) ?>
            </button>
        </form>
    </div>
</div>
