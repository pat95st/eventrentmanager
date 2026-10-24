<?php
/** @var \App\Application\NoticeDocument\Query\NoticeDocumentDetail|null $detail */
/** @var \App\Domain\Article\Article[] $articles */
/** @var array<string, string> $errors */

use App\Infrastructure\Http\Csrf;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$isEditing = $detail !== null;
$action = $isEditing ? '/hinweisdokumente/' . e($detail->document->id) : '/hinweisdokumente';
$selectedArticleIds = $detail?->articleIds ?? [];
?>

<div class="mb-6">
    <a href="/hinweisdokumente" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('notice_documents.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $isEditing ? e(t('notice_documents.edit')) : e(t('notice_documents.new')) ?></h1>
</div>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">

    <div>
        <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('notice_documents.form.name')) ?></label>
        <input type="text" id="name" name="name" required value="<?= e($detail?->document->name ?? '') ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <?php if (!$isEditing): ?>
    <div>
        <label for="document" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('notice_documents.form.file')) ?></label>
        <input type="file" id="document" name="document" required accept=".pdf,.jpg,.jpeg,.png,.webp"
               class="mt-1 w-full rounded-md border <?= isset($errors['document']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm">
        <?php if (isset($errors['document'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['document']) ?></p><?php endif; ?>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('notice_documents.form.file_hint')) ?></p>
    </div>
    <?php else: ?>
        <p class="text-xs text-slate-500 dark:text-slate-400"><?= e(t('notice_documents.form.current_file')) ?>: <strong><?= e($detail->document->originalFilename) ?></strong></p>
    <?php endif; ?>

    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('notice_documents.form.articles')) ?></label>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('notice_documents.form.articles_hint')) ?></p>
        <div class="mt-2 max-h-64 space-y-1 overflow-y-auto rounded-md border border-slate-200 p-3 dark:border-slate-700">
            <?php foreach ($articles as $article): ?>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="article_ids[]" value="<?= e($article->id) ?>" <?= in_array($article->id, $selectedArticleIds, true) ? 'checked' : '' ?>
                           class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <?= e($article->name) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex justify-end gap-2 pt-2">
        <a href="/hinweisdokumente" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.cancel')) ?></a>
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
    </div>
</form>
