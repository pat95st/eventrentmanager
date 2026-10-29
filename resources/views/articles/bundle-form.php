<?php
/**
 * Eigenes Formular für Bundles (Nutzerwunsch): nur die Angaben, die ein
 * Bundle braucht — Name, Preis pro Einsatztag, Bestandteile (inkl.
 * Alternativen), Beschreibung und Kurztext. Hersteller, Präfix, Kategorie,
 * Abmessungen, Einkaufspreis, Elektroprüfung, Firmware und 19 Zoll gibt es
 * bei Bundles nicht; das Gewicht wird aus den Bestandteilen berechnet
 * (Migration 111). Abgeschickt wird an dieselben Routen wie das
 * Artikelformular (is_bundle=1).
 *
 * @var \App\Domain\Article\Article|null $article
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var string|null $editingId
 * @var list<array{article_id: string, label: string, quantity: int, alternatives?: list<array{article_id: string, label: string}>}> $bundleComponentRows
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => $old[$field] ?? $fallback ?? '';
$bundleComponentRows ??= [];
$inputClass = fn (string $errorKey) => 'mt-1 w-full rounded-md border ' . (isset($errors[$errorKey]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500';
?>

<div class="mb-6">
    <a href="<?= $article !== null ? '/artikel/' . e($article->id) : '/artikel' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('articles.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $article !== null ? e(t('articles.bundle.edit')) : e(t('articles.bundle.new')) ?></h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('articles.bundle.subtitle')) ?></p>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/artikel/' . e($editingId) : '/artikel' ?>"
    class="max-w-4xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <input type="hidden" name="is_bundle" value="1">
    <input type="hidden" name="is_billable" value="1">

    <?php if (isset($errors['isBundle'])): ?>
        <p class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-400"><?= e($errors['isBundle']) ?></p>
    <?php endif; ?>

    <div>
        <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.bundle.name')) ?></label>
        <input type="text" id="name" name="name" required value="<?= e($val('name', $article?->name)) ?>" placeholder="<?= e(t('articles.bundle.name_placeholder')) ?>" class="<?= $inputClass('name') ?>">
        <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="daily_rental_price" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.daily_rental_price')) ?></label>
            <input type="number" step="0.01" min="0" id="daily_rental_price" name="daily_rental_price" required placeholder="z. B. 89.00"
                   value="<?= e($val('daily_rental_price', $article?->dailyRentalPrice)) ?>" class="<?= $inputClass('dailyRentalPrice') ?>">
            <?php if (isset($errors['dailyRentalPrice'])): ?>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['dailyRentalPrice']) ?></p>
            <?php else: ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.bundle.price_hint')) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <span class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.weight')) ?></span>
            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">
                <?= $article?->weightValue !== null ? e($article->weightValue . ' ' . $article->weightUnit) : '–' ?>
            </p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.bundle_weight_hint')) ?></p>
        </div>
    </div>

    <?php require __DIR__ . '/_bundle_components.php'; ?>

    <div>
        <label for="description" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.description')) ?></label>
        <textarea id="description" name="description" rows="4"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
        ><?= e($val('description', $article?->description)) ?></textarea>
    </div>

    <div>
        <label for="document_short_text" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.document_short_text')) ?></label>
        <input type="text" id="document_short_text" name="document_short_text" maxlength="500"
               value="<?= e((string) $val('document_short_text', $article?->documentShortText)) ?>"
               placeholder="<?= e(t('articles.form.document_short_text_placeholder')) ?>" class="<?= $inputClass('documentShortText') ?>">
        <?php if (isset($errors['documentShortText'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['documentShortText']) ?></p><?php endif; ?>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.document_short_text_hint')) ?></p>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $article !== null ? '/artikel/' . e($article->id) : '/artikel' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>
