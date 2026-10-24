<?php
/** @var \App\Domain\NoticeDocument\NoticeDocument[] $documents */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$formatSize = static function (int $bytes): string {
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }

    return number_format($bytes / 1024, 0, ',', '.') . ' KB';
};
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('notice_documents.title')) ?></h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('notice_documents.hint')) ?></p>
    </div>
    <a href="/hinweisdokumente/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('notice_documents.new')) ?></a>
</div>

<?php if ($documents === []): ?>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('notice_documents.empty')) ?></p>
<?php else: ?>
<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            <tr>
                <th class="px-4 py-2"><?= e(t('notice_documents.col_name')) ?></th>
                <th class="px-4 py-2"><?= e(t('notice_documents.col_file')) ?></th>
                <th class="px-4 py-2"><?= e(t('notice_documents.col_size')) ?></th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($documents as $document): ?>
            <tr>
                <td class="px-4 py-2"><a href="/hinweisdokumente/<?= e($document->id) ?>/bearbeiten" class="font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e($document->name) ?></a></td>
                <td class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400"><?= e($document->originalFilename) ?></td>
                <td class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400"><?= e($formatSize($document->sizeBytes)) ?></td>
                <td class="px-4 py-2 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="/hinweisdokumente/<?= e($document->id) ?>/herunterladen" class="text-xs font-medium text-slate-500 hover:underline dark:text-slate-400"><?= e(t('notice_documents.download')) ?></a>
                        <a href="/hinweisdokumente/<?= e($document->id) ?>/bearbeiten" class="text-xs font-medium text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.edit')) ?></a>
                        <form method="post" action="/hinweisdokumente/<?= e($document->id) ?>/loeschen" onsubmit="return confirm('<?= e(t('notice_documents.delete_confirm')) ?>')">
                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
