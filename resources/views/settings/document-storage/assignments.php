<?php
/** @var \App\Domain\DocumentStorage\DocumentStorageAssignment[] $assignments */
/** @var \App\Domain\DocumentStorage\DocumentStorageTarget[] $targets */
/** @var array<string, \App\Domain\DocumentStorage\DocumentStorageTarget> $targetsById */
/** @var \App\Domain\DocumentStorage\DocumentType[] $documentTypes */
/** @var \App\Domain\DocumentStorage\DocumentStorageUploadLogEntry[] $log */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use App\Domain\DocumentStorage\DocumentType;
use App\Domain\DocumentStorage\UploadStatus;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.document_storage.assignments.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.document_storage.assignments.subtitle')) ?></p>
    </div>
    <a href="/einstellungen/speicherziele" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-400">&larr; <?= e(t('settings.document_storage.targets.title')) ?></a>
</div>

<?php if ($targets === []): ?>
    <div class="mb-6 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
        <?= e(t('settings.document_storage.assignments.no_targets_hint')) ?>
        <a href="/einstellungen/speicherziele" class="font-medium underline"><?= e(t('settings.document_storage.targets.new')) ?></a>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <?php foreach ($documentTypes as $documentType): ?>
        <?php $forType = array_values(array_filter($assignments, static fn ($a) => $a->documentType === $documentType)); ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e($documentType->label()) ?></h2>

            <?php if ($forType === []): ?>
                <p class="mb-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.document_storage.assignments.empty_for_type')) ?></p>
            <?php else: ?>
                <div class="mb-3 divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($forType as $assignment): ?>
                        <?php $target = $targetsById[$assignment->storageTargetId] ?? null; ?>
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <div class="text-sm text-slate-700 dark:text-slate-300">
                                <span class="font-medium"><?= e($target?->name ?? '?') ?></span>
                                <span class="text-slate-400 dark:text-slate-500">&rarr;</span>
                                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800"><?= e($assignment->targetPath) ?></code>
                                <?php if (!$assignment->enabled): ?>
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e(t('common.disabled')) ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="post" action="/einstellungen/speicherziele/zuordnungen/<?= e($assignment->id) ?>/loeschen" data-confirm="<?= e(t('settings.document_storage.assignments.confirm_delete')) ?>" data-confirm-style="danger">
                                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($targets !== []): ?>
                <form method="post" action="/einstellungen/speicherziele/zuordnungen" class="grid grid-cols-1 gap-3 sm:grid-cols-5 sm:items-end">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <input type="hidden" name="document_type" value="<?= e($documentType->value) ?>">

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.assignments.field.storage_target')) ?></label>
                        <select name="storage_target_id" class="mt-1 w-full rounded-md border <?= isset($errors['storageTargetId']) && ($old['document_type'] ?? '') === $documentType->value ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                            <?php foreach ($targets as $target): ?>
                                <option value="<?= e($target->id) ?>"><?= e($target->name) ?> (<?= e($target->protocol->label()) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.assignments.field.target_path')) ?></label>
                        <input type="text" name="target_path" placeholder="Rechnungen/{year}/{month}/{filename}"
                               value="<?= ($old['document_type'] ?? '') === $documentType->value ? e($old['target_path'] ?? '') : '' ?>"
                               class="mt-1 w-full rounded-md border <?= isset($errors['targetPath']) && ($old['document_type'] ?? '') === $documentType->value ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 font-mono text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="enabled" value="1">
                        <button type="submit" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            <?= e(t('settings.document_storage.assignments.add')) ?>
                        </button>
                    </div>
                </form>
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.document_storage.assignments.path_placeholder_hint')) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="mt-8 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.document_storage.log.title')) ?></h2>
    <p class="mb-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.document_storage.log.hint')) ?></p>

    <?php if ($log === []): ?>
        <p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.document_storage.log.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <th class="py-2 pr-3 font-medium"><?= e(t('settings.document_storage.log.column.date')) ?></th>
                        <th class="py-2 pr-3 font-medium"><?= e(t('settings.document_storage.log.column.document_type')) ?></th>
                        <th class="py-2 pr-3 font-medium"><?= e(t('settings.document_storage.log.column.target')) ?></th>
                        <th class="py-2 pr-3 font-medium"><?= e(t('settings.document_storage.log.column.path')) ?></th>
                        <th class="py-2 pr-3 font-medium"><?= e(t('settings.document_storage.log.column.status')) ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($log as $entry): ?>
                        <?php $target = $entry->storageTargetId !== null ? ($targetsById[$entry->storageTargetId] ?? null) : null; ?>
                        <tr>
                            <td class="whitespace-nowrap py-2 pr-3 text-slate-500 dark:text-slate-400"><?= e($entry->createdAt->format('d.m.Y H:i')) ?></td>
                            <td class="py-2 pr-3 text-slate-700 dark:text-slate-300"><?= e($entry->documentType->label()) ?></td>
                            <td class="py-2 pr-3 text-slate-700 dark:text-slate-300"><?= e($target?->name ?? '—') ?></td>
                            <td class="py-2 pr-3"><code class="rounded bg-slate-100 px-1 py-0.5 dark:bg-slate-800"><?= e($entry->remotePath) ?></code></td>
                            <td class="py-2 pr-3">
                                <?php if ($entry->status === UploadStatus::Success): ?>
                                    <span class="rounded bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"><?= e(t('settings.document_storage.log.status.success')) ?></span>
                                <?php else: ?>
                                    <span class="rounded bg-red-50 px-1.5 py-0.5 font-medium text-red-700 dark:bg-red-950/40 dark:text-red-400" title="<?= e($entry->errorMessage ?? '') ?>"><?= e(t('settings.document_storage.log.status.failed')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($entry->status === UploadStatus::Failed && $entry->errorMessage !== null): ?>
                            <tr>
                                <td colspan="5" class="pb-2 pl-0 pr-3 text-red-600 dark:text-red-400"><?= e($entry->errorMessage) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
