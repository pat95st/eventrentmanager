<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Rendert das Modal für die Artikel-Mehrfachauswahl (siehe
 * public/assets/js/article-picker.js) — ermöglicht das Anhaken mehrerer
 * Artikel aus dem gesamten Katalog (serverseitig paginiert durchsuchbar,
 * ArticleController::picker()) und das gesammelte Einfügen als
 * Positionszeilen, parallel zur bestehenden Live-Suche je Zeile
 * (renderLiveSearchField()) in Angebots-/Rechnungs-/
 * Auftragsbestätigungsformularen.
 *
 * Bewusst EINE global geteilte Funktion statt des sonst für
 * Formular-Hilfsfunktionen üblichen Dupliziert-pro-View-Musters — aus
 * demselben Grund wie bei renderLiveSearchField() direkt daneben: diese
 * Komponente wird unverändert von drei Formularen genutzt. Wird EINMAL pro
 * Formular gerendert; das Öffnen/Schließen/die eigentliche Auswahl-Logik
 * steckt vollständig in article-picker.js, das jeweilige Formular übergibt
 * beim Öffnen lediglich einen Callback (siehe window.ArticlePicker.open()).
 */
if (!function_exists('renderArticlePickerModal')) {
    function renderArticlePickerModal(): void
    {
        ?>
<div data-article-picker-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= e(t('article_picker.title')) ?></h2>
            <button type="button" data-article-picker-close class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">&times;</button>
        </div>
        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
            <input
                type="text"
                data-article-picker-search
                autocomplete="off"
                placeholder="<?= e(t('article_picker.search_placeholder')) ?>"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
            >
        </div>
        <div data-article-picker-results class="flex-1 overflow-y-auto px-4 py-2 text-sm">
            <p class="py-6 text-center text-slate-400 dark:text-slate-500"><?= e(t('article_picker.loading')) ?></p>
        </div>
        <div data-article-picker-pagination class="flex items-center justify-center gap-3 border-t border-slate-100 px-4 py-2 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"></div>
        <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-800">
            <span data-article-picker-count class="text-xs text-slate-500 dark:text-slate-400"></span>
            <div class="flex gap-2">
                <button type="button" data-article-picker-cancel class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.cancel')) ?></button>
                <button type="button" data-article-picker-insert disabled class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"><?= e(t('article_picker.insert')) ?></button>
            </div>
        </div>
    </div>
</div>
        <?php
    }
}
