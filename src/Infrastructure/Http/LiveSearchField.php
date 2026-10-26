<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Rendert ein Live-Suche-Auswahlfeld (siehe public/assets/js/live-search.js)
 * als Ersatz für ein einfaches <select> mit potenziell vielen Optionen.
 *
 * Bewusst EINE global geteilte Funktion (anders als das sonst in diesem
 * Projekt für Formular-Hilfsfunktionen übliche Dupliziert-pro-View-Muster,
 * siehe z. B. renderDocumentClosingTextField() in resources/views/
 * invoices/form.php) — diese Komponente wird von sehr vielen,
 * unterschiedlichen Formularen für fünf verschiedene Entitätstypen
 * (Kunden, Veranstaltungsorte, Artikel, Hersteller, Lieferanten) genutzt;
 * eine Duplizierung wäre hier reine Wartungslast. Liegt neben e()/t()
 * (siehe View.php/Translator.php) als dritte global verfügbare View-
 * Hilfsfunktion.
 *
 * @param string $name Formularfeld-Name. Im "select"-Modus der Name des
 *     versteckten ID-Feldes (z. B. "customer_id"); im "freetext"-Modus der
 *     Name des sichtbaren Textfeldes selbst (z. B. "event_location").
 * @param string $url Such-Endpunkt, z. B. "/kunden/suche" (siehe
 *     CustomerController::search() und Geschwister).
 * @param string $mode "select" (Standard, nur Auswahl aus Vorschlägen,
 *     siehe live-search.js) oder "freetext" (Vorschlag füllt das Feld nur
 *     aus, beliebiger Text bleibt weiterhin möglich — für den
 *     Veranstaltungsort, siehe App\Application\Common\VenueResolver).
 * @param bool $withId Ob das sichtbare Textfeld die id="$name" bekommt
 *     (für ein <label for="..."> auf Formularebene). Bei Feldern, die als
 *     Teil einer per JS geklonten Zeilen-Vorlage mehrfach auf derselben
 *     Seite vorkommen können (z. B. Artikel-Zeilen in Rechnungen/Angeboten),
 *     MUSS dies false sein, da sonst doppelte IDs entstehen — diese Zeilen
 *     verwenden ohnehin kein <label for="...">.
 */
function renderLiveSearchField(
    string $name,
    string $url,
    ?string $selectedId,
    ?string $selectedLabel,
    string $placeholder,
    ?string $error = null,
    string $mode = 'select',
    bool $required = false,
    bool $withId = true,
): void {
    ?>
<div data-live-search data-url="<?= e($url) ?>" data-mode="<?= e($mode) ?>" class="relative">
    <input
        type="text"
        <?= $withId ? 'id="' . e($name) . '"' : '' ?>
        data-live-search-input
        <?= $mode === 'freetext' ? 'name="' . e($name) . '"' : '' ?>
        autocomplete="off"
        value="<?= e($selectedLabel ?? '') ?>"
        placeholder="<?= e($placeholder) ?>"
        <?= $required ? 'required' : '' ?>
        class="w-full rounded-md border <?= $error !== null ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
    >
    <?php if ($mode !== 'freetext'): ?>
    <input type="hidden" name="<?= e($name) ?>" data-live-search-value value="<?= e($selectedId ?? '') ?>">
    <?php endif; ?>
    <div data-live-search-results class="absolute left-0 right-0 top-full z-20 mt-1 hidden max-h-64 overflow-y-auto rounded-md border border-slate-300 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"></div>
    <?php if ($error !== null): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($error) ?></p><?php endif; ?>
</div>
    <?php
}
