<?php
/** @var \App\Domain\Invoice\Invoice|null $invoice */
/** @var \App\Domain\Customer\Customer[] $customers */
/** @var \App\Domain\Venue\Venue[] $venues */
/** @var \App\Domain\Article\Article[] $articles */
/** @var array<int, string> $tierMultipliers Einsatztage (1..7) => Faktor bezogen auf Tagesmietsatz "1" */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var array<int, array{article_id: string, quantity: int|string, unit_price: string, line_note: string, sort_order: int|string}> $existingItems */
/** @var \App\Domain\LaborTime\LaborTimeArticle[] $laborArticles */
/** @var array<int, array{labor_article_id: string, hours: string}> $existingLaborItems */
/** @var array<int, array{description: string, quantity: string, unit_price: string, sort_order: int|string}> $existingFreeTextItems */
/** @var array<int, array{heading: string, sort_order: int|string}> $existingSectionTexts */
/** @var bool $isSmallBusinessPreview Siehe InvoiceController::renderForm() — identisches Fallback-Muster wie InvoiceDetail::totals(). */
/** @var string $vatRatePercentPreview */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
$locked = $invoice !== null && !$invoice->isEditable();
$items = $old['items'] ?? $existingItems;
$laborItems = $old['labor_items'] ?? $existingLaborItems;
$freeTextItems = $old['free_text_items'] ?? $existingFreeTextItems;
$sectionTexts = $old['section_texts'] ?? $existingSectionTexts;

$articlesJson = [];
foreach ($articles as $article) {
    $articlesJson[$article->id] = [
        'name' => $article->name,
        'price' => $article->isBillable && $article->dailyRentalPrice !== null ? $article->dailyRentalPrice : null,
    ];
}

$laborArticlesJson = [];
foreach ($laborArticles as $laborArticle) {
    $laborArticlesJson[$laborArticle->id] = [
        'name' => $laborArticle->name,
        'rate' => $laborArticle->hourlyRate,
    ];
}
?>

<div class="mb-6">
    <a href="<?= $invoice !== null ? '/rechnungen/' . e($invoice->id) : '/rechnungen' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('invoices.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $invoice !== null ? e(t('invoices.edit')) : e(t('invoices.new')) ?></h1>
</div>

<?php if ($locked): ?>
    <div class="mb-6 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
        <?= e(t('invoices.form.locked_notice')) ?>
    </div>
    <a href="/rechnungen/<?= e($invoice->id) ?>" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('invoices.form.back_to_detail')) ?></a>
<?php else: ?>

<form
    method="post"
    action="<?= $editingId !== null ? '/rechnungen/' . e($editingId) : '/rechnungen' ?>"
    class="max-w-5xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
    id="invoice-form"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="customer_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.customer')) ?></label>
            <div class="mt-1 flex gap-2">
                <select id="customer_id" name="customer_id"
                        class="w-full rounded-md border <?= isset($errors['customerId']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <option value=""><?= e(t('invoices.form.customer_placeholder')) ?></option>
                    <?php $selectedCustomer = $val('customer_id', $invoice?->customerId); ?>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= e($customer->id) ?>" <?= $selectedCustomer === $customer->id ? 'selected' : '' ?>><?= e($customer->displayName()) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" data-customer-quick-create-open class="shrink-0 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('customers.quick_create.title')) ?>
                </button>
            </div>
            <?php if (isset($errors['customerId'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['customerId']) ?></p><?php endif; ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.customer_hint')) ?></p>
        </div>

        <div>
            <label for="event_location" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.event_location')) ?></label>
            <input type="text" id="event_location" name="event_location" list="venue-datalist"
                   value="<?= e($val('event_location', $invoice?->eventLocation)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <datalist id="venue-datalist">
                <?php foreach ($venues as $venue): ?>
                    <option value="<?= e($venue->name) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="start_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.start_date')) ?></label>
            <input type="date" id="start_date" name="start_date" required
                   value="<?= e($val('start_date', $invoice?->startDate->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['startDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['startDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['startDate']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="end_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.end_date')) ?></label>
            <input type="date" id="end_date" name="end_date" required
                   value="<?= e($val('end_date', $invoice?->endDate->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['endDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['endDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['endDate']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="buyer_reference" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.buyer_reference')) ?></label>
            <input type="text" id="buyer_reference" name="buyer_reference"
                   value="<?= e($val('buyer_reference', $invoice?->buyerReference)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.buyer_reference_hint')) ?></p>
        </div>
    </div>

    <div>
        <label for="note" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.note')) ?></label>
        <textarea id="note" name="note" rows="3"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"><?= e($val('note', $invoice?->note)) ?></textarea>
    </div>

    <?php
        // Zubehörpauschale: standardmäßig angehakt bei Neuanlage, beim
        // Bearbeiten spiegelt sie den aktuellen Snapshot-Status wider (siehe
        // UpdateInvoiceHandler — der gesnapshotete Prozentsatz bleibt beim
        // Bearbeiten erhalten, solange die Checkbox angehakt bleibt).
        $accessoryChecked = array_key_exists('accessory_flat_rate_enabled', $old)
            ? $old['accessory_flat_rate_enabled'] === '1'
            : ($invoice?->accessoryFlatRateEnabled ?? true);
        $discountType = $val('discount_type', $invoice?->discountType);
        $discountValue = $val('discount_value', $invoice?->discountValue);
    ?>

    <?php
        // Positionen/Freitext-Posten/Zwischentexte: EIN gemeinsamer,
        // sortierbarer Bereich statt dreier getrennter Blöcke — die
        // Eingabereihenfolge (bzw. die per Drag-and-Drop hergestellte
        // Reihenfolge) wird beim Absenden 1:1 als sort_order übernommen und
        // bestimmt damit auch die Reihenfolge auf der Rechnung
        // (orderedRows()-Konzept, siehe InvoiceDetail::orderedRows()).
        // Arbeitszeit, Zubehörpauschale und Kombirabatt sind bewusst NICHT
        // Teil dieser Liste: sie erscheinen laut Aufgabenstellung immer als
        // letzte Posten auf dem Dokument und entsprechend auch hier ganz
        // unten, unterhalb dieses Bereichs (siehe DocumentTotals::calculate()
        // für die serverseitige Entsprechung).
    ?>
    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.form.items_title')) ?></h2>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="invoice-item-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('invoices.form.items_add')) ?>
                </button>
                <button type="button" id="invoice-free-text-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('invoices.form.free_text_items_add')) ?>
                </button>
                <button type="button" id="invoice-section-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('invoices.form.section_texts_add')) ?>
                </button>
            </div>
        </div>
        <?php if (isset($errors['items'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['items']) ?></p><?php endif; ?>
        <?php if (isset($errors['freeTextItems'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['freeTextItems']) ?></p><?php endif; ?>
        <div id="invoice-rows" class="space-y-2"></div>
        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.items_hint')) ?></p>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.rows_drag_hint')) ?></p>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.form.labor_items_title')) ?></h2>
            <button type="button" id="invoice-labor-item-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                + <?= e(t('invoices.form.labor_items_add')) ?>
            </button>
        </div>
        <?php if (isset($errors['laborItems'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['laborItems']) ?></p><?php endif; ?>
        <?php if ($laborArticles === []): ?>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.labor_items_none_defined')) ?> <a href="/arbeitszeit-artikel" class="text-brand-700 hover:underline dark:text-brand-400"><?= e(t('invoices.form.labor_items_manage_link')) ?></a></p>
        <?php else: ?>
            <div id="invoice-labor-items" class="space-y-2"></div>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.labor_items_hint')) ?></p>
        <?php endif; ?>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="flex items-start gap-2">
            <input type="checkbox" id="accessory_flat_rate_enabled" name="accessory_flat_rate_enabled" value="1" <?= $accessoryChecked ? 'checked' : '' ?>
                   class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
            <div>
                <label for="accessory_flat_rate_enabled" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    <?= e(t('invoices.form.accessory_flat_rate', ['percent' => e($pricingSettings->accessoryFlatRatePercent)])) ?>
                </label>
                <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.accessory_flat_rate_hint')) ?></p>
                <p id="invoice-accessory-labor-only-hint" class="mt-1 hidden text-xs text-amber-600 dark:text-amber-400"><?= e(t('invoices.form.labor_only_hint')) ?></p>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.form.discount_title')) ?></h2>
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="discount_type" value="" data-discount-option <?= $discountType === '' || $discountType === null ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('invoices.form.discount_none')) ?>
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="discount_type" value="percent" data-discount-option <?= $discountType === 'percent' ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('invoices.form.discount_percent')) ?>
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="discount_type" value="fixed" data-discount-option <?= $discountType === 'fixed' ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('invoices.form.discount_fixed')) ?>
            </label>
        </div>
        <div id="discount-value-wrapper" class="mt-2 <?= $discountType === '' || $discountType === null ? 'hidden' : '' ?>">
            <label for="discount_value" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.discount_value')) ?></label>
            <input type="text" id="discount_value" name="discount_value" inputmode="decimal"
                   value="<?= e((string) ($discountValue ?? '')) ?>"
                   class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['discountValue']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['discountValue'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['discountValue']) ?></p><?php endif; ?>
        </div>
        <p id="invoice-discount-labor-only-hint" class="mt-1 hidden text-xs text-amber-600 dark:text-amber-400"><?= e(t('invoices.form.labor_only_hint')) ?></p>
        <script>
            (function () {
                var wrapper = document.getElementById('discount-value-wrapper');
                var options = document.querySelectorAll('[data-discount-option]');
                options.forEach(function (option) {
                    option.addEventListener('change', function () {
                        wrapper.classList.toggle('hidden', option.value === '' && option.checked);
                    });
                });
            })();
        </script>
    </div>

    <?php
        // Live-Gesamtsumme (rein clientseitige Vorschau per JavaScript, siehe
        // Script-Block unten): spiegelt App\Domain\Pricing\DocumentTotals
        // wider (Mietpreise -> Zubehörpauschale -> Kombirabatt -> Arbeitszeit
        // -> Netto/USt/Brutto). NICHT die verbindliche Berechnung — die
        // bleibt serverseitig bei ValidatesInvoiceInput/DocumentTotals beim
        // Speichern; diese Anzeige dient ausschließlich der sofortigen
        // Rückmeldung beim Ausfüllen des Formulars. isSmallBusinessPreview/
        // vatRatePercentPreview sind für diesen Seitenaufruf FEST (nicht im
        // Formular änderbar, siehe InvoiceController::renderForm()) — daher
        // reicht es, die davon abhängige Verzweigung serverseitig einmal zu
        // rendern; nur die Beträge selbst werden per JS aktualisiert.
    ?>
    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.form.totals_title')) ?></h2>
        <dl class="max-w-sm space-y-1 text-sm">
            <div class="flex items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('invoices.show.rental_subtotal')) ?></dt>
                <dd data-total-rental>0,00 €</dd>
            </div>
            <div data-total-accessory-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('invoices.show.accessory_flat_rate', ['rate' => number_format((float) ($pricingSettings->accessoryFlatRatePercent ?? '0'), 2, ',', '.')])) ?></dt>
                <dd data-total-accessory>0,00 €</dd>
            </div>
            <div data-total-discount-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt data-total-discount-label></dt>
                <dd data-total-discount>0,00 €</dd>
            </div>
            <div data-total-labor-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('invoices.form.totals_labor')) ?></dt>
                <dd data-total-labor>0,00 €</dd>
            </div>
            <div data-total-free-text-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('invoices.form.totals_free_text')) ?></dt>
                <dd data-total-free-text>0,00 €</dd>
            </div>
            <?php if ($isSmallBusinessPreview): ?>
                <div class="flex items-baseline justify-between border-t border-slate-200 pt-1.5 font-semibold text-slate-900 dark:border-slate-700 dark:text-slate-100">
                    <dt><?= e(t('invoices.show.grand_total')) ?></dt>
                    <dd data-total-grand>0,00 €</dd>
                </div>
                <p class="pt-1 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('invoices.show.small_business_notice')) ?></p>
            <?php else: ?>
                <div class="flex items-baseline justify-between text-slate-600 dark:text-slate-400">
                    <dt><?= e(t('invoices.show.net_total')) ?></dt>
                    <dd data-total-net>0,00 €</dd>
                </div>
                <div class="flex items-baseline justify-between text-slate-600 dark:text-slate-400">
                    <dt><?= e(t('invoices.show.vat_amount', ['rate' => number_format((float) $vatRatePercentPreview, 2, ',', '.')])) ?></dt>
                    <dd data-total-vat>0,00 €</dd>
                </div>
                <div class="flex items-baseline justify-between border-t border-slate-200 pt-1.5 font-semibold text-slate-900 dark:border-slate-700 dark:text-slate-100">
                    <dt><?= e(t('invoices.show.gross_total')) ?></dt>
                    <dd data-total-grand>0,00 €</dd>
                </div>
            <?php endif; ?>
        </dl>
        <p class="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.totals_hint')) ?></p>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $invoice !== null ? '/rechnungen/' . e($invoice->id) : '/rechnungen' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<?php require __DIR__ . '/../customers/_quick_create_modal.php'; ?>

<?php
    // Artikel-Auswahl-Modal: siehe offers/form.php für die identische
    // Begründung/Funktionsweise (bewusst dupliziert, gleiches Muster wie der
    // restliche Formularcode dieser beiden Views).
?>
<div id="article-picker-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="flex max-h-[80vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e(t('invoices.form.picker_title')) ?></h3>
            <button type="button" id="article-picker-close" class="text-lg leading-none text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">&times;</button>
        </div>
        <div class="p-4 pb-2">
            <input type="text" id="article-picker-search" placeholder="<?= e(t('invoices.form.picker_search_placeholder')) ?>"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700" autocomplete="off">
        </div>
        <div id="article-picker-list" class="flex-1 space-y-0.5 overflow-y-auto px-2 pb-2"></div>
        <p id="article-picker-empty" class="hidden px-4 pb-4 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.picker_empty')) ?></p>
    </div>
</div>

<template id="invoice-item-row-template">
    <div class="invoice-item-row invoice-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="invoice-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('invoices.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.item_article')) ?></label>
            <div class="mt-1 flex gap-1">
                <select name="article_id[]" class="invoice-item-article w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    <option value=""><?= e(t('invoices.show.article_placeholder')) ?></option>
                    <?php foreach ($articles as $article): ?>
                        <option value="<?= e($article->id) ?>"><?= e($article->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="invoice-item-pick shrink-0 rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" title="<?= e(t('invoices.form.picker_button')) ?>">
                    🔍
                </button>
            </div>
        </div>
        <div class="w-20">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.item_quantity')) ?></label>
            <input type="number" name="quantity[]" min="1" value="1" class="invoice-item-quantity mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.item_price')) ?></label>
            <input type="text" name="unit_price[]" inputmode="decimal" class="invoice-item-price mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="min-w-[140px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.item_note')) ?></label>
            <input type="text" name="line_note[]" class="invoice-item-note mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <input type="hidden" name="sort_order[]" class="invoice-item-sort" data-row-sort value="0">
        <button type="button" class="invoice-item-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('invoices.form.item_remove')) ?>
        </button>
    </div>
</template>

<?php if ($laborArticles !== []): ?>
<template id="invoice-labor-item-row-template">
    <div class="invoice-labor-item-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.labor_item_article')) ?></label>
            <select name="labor_article_id[]" class="invoice-labor-item-article mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                <option value=""><?= e(t('invoices.form.labor_item_article_placeholder')) ?></option>
                <?php foreach ($laborArticles as $laborArticle): ?>
                    <option value="<?= e($laborArticle->id) ?>" data-rate="<?= e($laborArticle->hourlyRate) ?>">
                        <?= e($laborArticle->name) ?> (<?= e(number_format((float) $laborArticle->hourlyRate, 2, ',', '.')) ?> €/Std.)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-24">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.labor_item_hours')) ?></label>
            <input type="text" name="hours[]" inputmode="decimal" class="invoice-labor-item-hours mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.labor_item_total')) ?></label>
            <input type="text" class="invoice-labor-item-total mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400" readonly tabindex="-1">
        </div>
        <button type="button" class="invoice-labor-item-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('invoices.form.item_remove')) ?>
        </button>
    </div>
</template>
<?php endif; ?>

<template id="invoice-free-text-row-template">
    <div class="invoice-free-text-row invoice-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="invoice-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('invoices.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.free_text_description')) ?></label>
            <input type="text" name="free_text_description[]" class="invoice-free-text-description mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-20">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.item_quantity')) ?></label>
            <input type="text" name="free_text_quantity[]" inputmode="decimal" value="1" class="invoice-free-text-quantity mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.item_price')) ?></label>
            <input type="text" name="free_text_unit_price[]" inputmode="decimal" value="0" class="invoice-free-text-price mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <input type="hidden" name="free_text_sort_order[]" class="invoice-free-text-sort" data-row-sort value="1000">
        <button type="button" class="invoice-free-text-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('invoices.form.item_remove')) ?>
        </button>
    </div>
</template>

<template id="invoice-section-row-template">
    <div class="invoice-section-row invoice-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="invoice-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('invoices.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.section_heading')) ?></label>
            <input type="text" name="section_heading[]" class="invoice-section-heading mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <input type="hidden" name="section_sort_order[]" class="invoice-section-sort" data-row-sort value="2000">
        <button type="button" class="invoice-section-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('invoices.form.item_remove')) ?>
        </button>
    </div>
</template>

<script>
(function () {
    var ARTICLES = <?= json_encode($articlesJson, JSON_THROW_ON_ERROR) ?>;
    var TIER_MULTIPLIERS = <?= json_encode($tierMultipliers, JSON_THROW_ON_ERROR) ?>;
    var EXISTING_ITEMS = <?= json_encode(array_values($items), JSON_THROW_ON_ERROR) ?>;

    // Früh deklariert (statt erst im Block "Live-Gesamtsumme" weiter unten),
    // damit der Submit-Handler für die sort_order-Festschreibung (siehe
    // weiter unten im Freitext-/Zwischentext-Block) bereits beim ersten
    // Durchlauf des Skripts einen gültigen Wert sieht — "var" würde sonst
    // erst zum Zeitpunkt der (textuell späteren) Zuweisung befüllt.
    var invoiceForm = document.getElementById('invoice-form');

    var container = document.getElementById('invoice-rows');
    var template = document.getElementById('invoice-item-row-template');
    var addButton = document.getElementById('invoice-item-add');
    var startInput = document.getElementById('start_date');
    var endInput = document.getElementById('end_date');
    var nextSortOrder = 0;

    function dayCount() {
        if (!startInput.value || !endInput.value) {
            return 1;
        }
        var start = new Date(startInput.value);
        var end = new Date(endInput.value);
        var diff = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;
        if (isNaN(diff) || diff < 1) {
            return 1;
        }
        return Math.min(diff, 7);
    }

    function suggestPrice(articleId) {
        var article = ARTICLES[articleId];
        if (!article || article.price === null) {
            return null;
        }
        var factor = TIER_MULTIPLIERS[dayCount()] || TIER_MULTIPLIERS[1] || '1';
        var price = parseFloat(article.price) * parseFloat(factor);
        return isNaN(price) ? null : price.toFixed(2);
    }

    function addRow(data) {
        var fragment = template.content.cloneNode(true);
        var row = fragment.querySelector('.invoice-item-row');
        var articleSelect = row.querySelector('.invoice-item-article');
        var quantityInput = row.querySelector('.invoice-item-quantity');
        var priceInput = row.querySelector('.invoice-item-price');
        var noteInput = row.querySelector('.invoice-item-note');
        var sortInput = row.querySelector('.invoice-item-sort');
        var removeButton = row.querySelector('.invoice-item-remove');
        var pickButton = row.querySelector('.invoice-item-pick');

        data = data || {};
        if (data.article_id) { articleSelect.value = data.article_id; }
        if (data.quantity) { quantityInput.value = data.quantity; }
        if (data.line_note) { noteInput.value = data.line_note; }
        sortInput.value = data.sort_order !== undefined ? data.sort_order : nextSortOrder++;

        if (data.unit_price !== undefined && data.unit_price !== '') {
            priceInput.value = data.unit_price;
        } else {
            var suggested = suggestPrice(articleSelect.value);
            if (suggested !== null) { priceInput.value = suggested; }
        }

        articleSelect.addEventListener('change', function () {
            var suggested = suggestPrice(articleSelect.value);
            if (suggested !== null) { priceInput.value = suggested; }
        });

        removeButton.addEventListener('click', function () { row.remove(); updateOnlyLaborState(); });

        if (pickButton) {
            pickButton.addEventListener('click', function () { openArticlePicker(articleSelect); });
        }

        enableRowDrag(row);
        container.appendChild(row);
        updateOnlyLaborState();
        return articleSelect;
    }

    function refreshAllSuggestions() {
        container.querySelectorAll('.invoice-item-row').forEach(function (row) {
            var articleSelect = row.querySelector('.invoice-item-article');
            var priceInput = row.querySelector('.invoice-item-price');
            if (!priceInput.dataset.touched) {
                var suggested = suggestPrice(articleSelect.value);
                if (suggested !== null) { priceInput.value = suggested; }
            }
        });
    }

    container.addEventListener('input', function (e) {
        if (e.target.classList.contains('invoice-item-price')) {
            e.target.dataset.touched = '1';
        }
    });

    addButton.addEventListener('click', function () { openArticlePicker(addRow()); });
    startInput.addEventListener('change', refreshAllSuggestions);
    endInput.addEventListener('change', refreshAllSuggestions);

    // --- Artikel-Auswahl-Modal ---------------------------------------------
    // Siehe offers/form.php für die identische Begründung/Funktionsweise
    // (bewusst dupliziert, gleiches Muster wie der restliche Formularcode
    // dieser beiden Views).
    var ARTICLE_ENTRIES = Object.keys(ARTICLES).map(function (id) {
        return { id: id, name: ARTICLES[id].name, price: ARTICLES[id].price };
    }).sort(function (a, b) { return a.name.localeCompare(b.name, 'de'); });

    var articlePickerModal = document.getElementById('article-picker-modal');
    var articlePickerSearch = document.getElementById('article-picker-search');
    var articlePickerList = document.getElementById('article-picker-list');
    var articlePickerEmpty = document.getElementById('article-picker-empty');
    var articlePickerCloseButton = document.getElementById('article-picker-close');
    var articlePickerTarget = null;

    function formatPickerPrice(price) {
        if (price === null) { return ''; }
        var n = parseFloat(price);
        if (isNaN(n)) { return ''; }
        return n.toFixed(2).replace('.', ',') + ' €';
    }

    function renderArticlePickerList(filter) {
        var term = (filter || '').trim().toLowerCase();
        var matches = term === ''
            ? ARTICLE_ENTRIES
            : ARTICLE_ENTRIES.filter(function (entry) { return entry.name.toLowerCase().indexOf(term) !== -1; });

        articlePickerList.innerHTML = '';
        matches.forEach(function (entry) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800';

            var nameSpan = document.createElement('span');
            nameSpan.textContent = entry.name;
            button.appendChild(nameSpan);

            var priceText = formatPickerPrice(entry.price);
            if (priceText !== '') {
                var priceSpan = document.createElement('span');
                priceSpan.className = 'shrink-0 text-xs text-slate-400 dark:text-slate-500';
                priceSpan.textContent = priceText;
                button.appendChild(priceSpan);
            }

            button.addEventListener('click', function () {
                if (articlePickerTarget) {
                    articlePickerTarget.value = entry.id;
                    articlePickerTarget.dispatchEvent(new Event('change', { bubbles: true }));
                }
                closeArticlePicker();
            });

            articlePickerList.appendChild(button);
        });

        articlePickerEmpty.classList.toggle('hidden', matches.length > 0);
    }

    function openArticlePicker(targetSelect) {
        if (!targetSelect || !articlePickerModal) { return; }
        articlePickerTarget = targetSelect;
        articlePickerSearch.value = '';
        renderArticlePickerList('');
        articlePickerModal.classList.remove('hidden');
        articlePickerModal.classList.add('flex');
        window.setTimeout(function () { articlePickerSearch.focus(); }, 0);
    }

    function closeArticlePicker() {
        if (!articlePickerModal) { return; }
        articlePickerModal.classList.add('hidden');
        articlePickerModal.classList.remove('flex');
        articlePickerTarget = null;
    }

    if (articlePickerModal) {
        articlePickerSearch.addEventListener('input', function () { renderArticlePickerList(articlePickerSearch.value); });
        articlePickerCloseButton.addEventListener('click', closeArticlePicker);
        articlePickerModal.addEventListener('click', function (e) {
            if (e.target === articlePickerModal) { closeArticlePicker(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !articlePickerModal.classList.contains('hidden')) { closeArticlePicker(); }
        });
    }

    if (document.getElementById('invoice-labor-item-row-template')) {
        var LABOR_ARTICLES = <?= json_encode($laborArticlesJson, JSON_THROW_ON_ERROR) ?>;
        var EXISTING_LABOR_ITEMS = <?= json_encode(array_values($laborItems), JSON_THROW_ON_ERROR) ?>;

        var laborContainer = document.getElementById('invoice-labor-items');
        var laborTemplate = document.getElementById('invoice-labor-item-row-template');
        var laborAddButton = document.getElementById('invoice-labor-item-add');

        var computeLaborTotal = function (laborArticleId, hours) {
            var article = LABOR_ARTICLES[laborArticleId];
            if (!article) { return ''; }
            var rate = parseFloat(article.rate);
            var h = parseFloat(hours);
            if (isNaN(rate) || isNaN(h)) { return ''; }
            return (rate * h).toFixed(2);
        };

        var addLaborRow = function (data) {
            var fragment = laborTemplate.content.cloneNode(true);
            var row = fragment.querySelector('.invoice-labor-item-row');
            var articleSelect = row.querySelector('.invoice-labor-item-article');
            var hoursInput = row.querySelector('.invoice-labor-item-hours');
            var totalInput = row.querySelector('.invoice-labor-item-total');
            var removeButton = row.querySelector('.invoice-labor-item-remove');

            data = data || {};
            if (data.labor_article_id) { articleSelect.value = data.labor_article_id; }
            if (data.hours) { hoursInput.value = data.hours; }
            totalInput.value = computeLaborTotal(articleSelect.value, hoursInput.value);

            function refreshTotal() { totalInput.value = computeLaborTotal(articleSelect.value, hoursInput.value); }
            articleSelect.addEventListener('change', refreshTotal);
            hoursInput.addEventListener('input', refreshTotal);
            removeButton.addEventListener('click', function () { row.remove(); updateOnlyLaborState(); });

            laborContainer.appendChild(row);
            updateOnlyLaborState();
        };

        if (laborAddButton) {
            laborAddButton.addEventListener('click', function () { addLaborRow(); });
        }
        if (laborContainer) {
            EXISTING_LABOR_ITEMS.forEach(function (item) { addLaborRow(item); });
        }
    }

    // --- Freitext-Posten/Zwischentexte (orderedRows()-Konzept) -------------
    // Siehe order-confirmations/form.php für die identische Begründung
    // dieses Musters (dort zuerst eingeführt).
    var EXISTING_FREE_TEXT_ITEMS = <?= json_encode(array_values($freeTextItems), JSON_THROW_ON_ERROR) ?>;
    var EXISTING_SECTION_TEXTS = <?= json_encode(array_values($sectionTexts), JSON_THROW_ON_ERROR) ?>;

    // freeTextContainer/sectionContainer sind bewusst DASSELBE Element wie
    // "container" (= #invoice-rows): Positionen, Freitext-Posten und
    // Zwischentexte leben seit der Umstellung auf einen gemeinsamen,
    // sortierbaren Bereich im selben Container, damit sie sich per
    // Drag-and-Drop gegenseitig umsortieren lassen. Die bestehenden, nach
    // Zeilentyp gefilterten querySelectorAll()-Aufrufe (addRow/updateTotals)
    // funktionieren dadurch unverändert weiter.
    var freeTextContainer = container;
    var sectionContainer = container;
    var freeTextTemplate = document.getElementById('invoice-free-text-row-template');
    var freeTextAddButton = document.getElementById('invoice-free-text-add');
    var sectionTemplate = document.getElementById('invoice-section-row-template');
    var sectionAddButton = document.getElementById('invoice-section-add');

    function addFreeTextRow(data) {
        var fragment = freeTextTemplate.content.cloneNode(true);
        var row = fragment.querySelector('.invoice-free-text-row');
        data = data || {};
        if (data.description) { row.querySelector('.invoice-free-text-description').value = data.description; }
        if (data.quantity) { row.querySelector('.invoice-free-text-quantity').value = data.quantity; }
        if (data.unit_price !== undefined) { row.querySelector('.invoice-free-text-price').value = data.unit_price; }
        row.querySelector('.invoice-free-text-sort').value = 0;
        row.querySelector('.invoice-free-text-remove').addEventListener('click', function () { row.remove(); updateOnlyLaborState(); });
        enableRowDrag(row);
        freeTextContainer.appendChild(row);
        updateOnlyLaborState();
    }

    if (freeTextAddButton) {
        freeTextAddButton.addEventListener('click', function () { addFreeTextRow(); });
    }

    function addSectionRow(data) {
        var fragment = sectionTemplate.content.cloneNode(true);
        var row = fragment.querySelector('.invoice-section-row');
        data = data || {};
        if (data.heading) { row.querySelector('.invoice-section-heading').value = data.heading; }
        row.querySelector('.invoice-section-sort').value = 0;
        row.querySelector('.invoice-section-remove').addEventListener('click', function () { row.remove(); });
        enableRowDrag(row);
        sectionContainer.appendChild(row);
    }

    if (sectionAddButton) {
        sectionAddButton.addEventListener('click', function () { addSectionRow(); });
    }

    // --- Drag-and-Drop-Umsortierung -----------------------------------------
    // Nur der Griff (.invoice-row-handle) löst das native HTML5-Drag aus
    // (draggable wird erst bei dessen mousedown gesetzt) — ein Klick/Drag in
    // einem Eingabefeld der Zeile soll z. B. weiterhin die Textauswahl
    // bedienen, statt versehentlich die Zeile zu verschieben.
    var draggingRow = null;

    function enableRowDrag(row) {
        var handle = row.querySelector('.invoice-row-handle');
        if (!handle) { return; }
        handle.addEventListener('mousedown', function () { row.draggable = true; });
        row.addEventListener('dragend', function () { row.draggable = false; draggingRow = null; });
    }

    container.addEventListener('dragstart', function (e) {
        var row = e.target.closest('.invoice-row');
        if (!row) { return; }
        draggingRow = row;
        e.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragover', function (e) {
        if (!draggingRow) { return; }
        e.preventDefault();
        var row = e.target.closest('.invoice-row');
        if (!row || row === draggingRow) { return; }
        var rect = row.getBoundingClientRect();
        var placeBefore = (e.clientY - rect.top) < rect.height / 2;
        container.insertBefore(draggingRow, placeBefore ? row : row.nextSibling);
    });

    // --- Reihenfolge beim Absenden festschreiben ----------------------------
    // sort_order aller Zeilen (Position/Freitext-Posten/Zwischentext) wird
    // erst hier, unmittelbar vor dem Absenden, aus der tatsächlichen
    // DOM-Reihenfolge von #invoice-rows berechnet (0, 1, 2, ...) — die
    // Eingabe-/Drag-and-Drop-Reihenfolge wird damit 1:1 zur sort_order und
    // damit zur Reihenfolge auf der Rechnung (orderedRows()-Konzept).
    if (invoiceForm) {
        invoiceForm.addEventListener('submit', function () {
            var order = 0;
            container.querySelectorAll('[data-row-sort]').forEach(function (input) {
                input.value = order++;
            });
        });
    }

    // --- Vorhandene Zeilen in der tatsächlichen Dokumentreihenfolge rendern -
    // EXISTING_ITEMS/EXISTING_FREE_TEXT_ITEMS/EXISTING_SECTION_TEXTS werden
    // zu EINER Liste zusammengeführt und nach sort_order sortiert, statt wie
    // zuvor blockweise (erst alle Positionen, dann alle Freitext-Posten, dann
    // alle Zwischentexte) ins DOM gehängt zu werden — sonst würde die
    // Bearbeitungsansicht eines bereits (z. B. künftig per Drag-and-Drop)
    // interleavten Dokuments die wahre Reihenfolge nicht mehr zeigen.
    var mergedRows = []
        .concat(EXISTING_ITEMS.map(function (item) { return { type: 'item', sort: Number(item.sort_order || 0), data: item }; }))
        .concat(EXISTING_FREE_TEXT_ITEMS.map(function (item) { return { type: 'free_text', sort: Number(item.sort_order || 0), data: item }; }))
        .concat(EXISTING_SECTION_TEXTS.map(function (item) { return { type: 'section', sort: Number(item.sort_order || 0), data: item }; }))
        .sort(function (a, b) { return a.sort - b.sort; });

    if (mergedRows.length > 0) {
        mergedRows.forEach(function (row) {
            if (row.type === 'item') { addRow(row.data); }
            else if (row.type === 'free_text') { addFreeTextRow(row.data); }
            else { addSectionRow(row.data); }
        });
    } else {
        addRow();
    }

    // --- Live-Gesamtsumme -------------------------------------------------
    // Siehe offers/form.php für die identische Begründung/Funktionsweise
    // (bewusst dupliziert, gleiches Muster wie der restliche Formularcode
    // dieser beiden Views) — hier zusätzlich mit Netto/USt/Brutto statt nur
    // einer einzelnen Gesamtsumme, da isSmallBusinessPreview/
    // vatRatePercentPreview für diesen Seitenaufruf feststehen (siehe
    // InvoiceController::renderForm()) und daher bereits serverseitig
    // entschieden wurde, welcher der beiden Fälle (Kleinunternehmer vs.
    // Netto+USt) im Markup gerendert ist.
    var IS_SMALL_BUSINESS = <?= json_encode($isSmallBusinessPreview) ?>;
    var VAT_RATE_PERCENT = <?= json_encode($vatRatePercentPreview) ?>;
    var ACCESSORY_PERCENT = <?= json_encode($pricingSettings->accessoryFlatRatePercent ?? '0') ?>;
    var DISCOUNT_PERCENT_LABEL = <?= json_encode(t('invoices.show.discount_percent')) ?>;
    var DISCOUNT_FIXED_LABEL = <?= json_encode(t('invoices.show.discount_fixed')) ?>;

    var invoiceForm = document.getElementById('invoice-form');
    var accessoryCheckbox = document.getElementById('accessory_flat_rate_enabled');
    var discountValueInput = document.getElementById('discount_value');

    var totalRentalEl = document.querySelector('[data-total-rental]');
    var totalAccessoryRow = document.querySelector('[data-total-accessory-row]');
    var totalAccessoryEl = document.querySelector('[data-total-accessory]');
    var totalDiscountRow = document.querySelector('[data-total-discount-row]');
    var totalDiscountLabelEl = document.querySelector('[data-total-discount-label]');
    var totalDiscountEl = document.querySelector('[data-total-discount]');
    var totalLaborRow = document.querySelector('[data-total-labor-row]');
    var totalLaborEl = document.querySelector('[data-total-labor]');
    var totalFreeTextRow = document.querySelector('[data-total-free-text-row]');
    var totalFreeTextEl = document.querySelector('[data-total-free-text]');
    var totalNetEl = document.querySelector('[data-total-net]');
    var totalVatEl = document.querySelector('[data-total-vat]');
    var totalGrandEl = document.querySelector('[data-total-grand]');

    function parseAmount(value) {
        var n = parseFloat(String(value || '').replace(',', '.'));
        return isNaN(n) ? 0 : n;
    }

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    function formatEuro(n) {
        var fixed = round2(n).toFixed(2);
        var parts = fixed.split('.');
        var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return intPart + ',' + parts[1] + ' €';
    }

    function formatRate(n) {
        return round2(n).toFixed(2).replace('.', ',');
    }

    function toggleRow(rowEl, visible) {
        if (!rowEl) { return; }
        rowEl.classList.toggle('hidden', !visible);
        rowEl.classList.toggle('flex', visible);
    }

    // --- "Nur Arbeitszeit"-Regel --------------------------------------------
    // Siehe ValidatesInvoiceInput::validateInvoiceInput() für die
    // serverseitige Entsprechung (dort verbindlich erzwungen): sind weder
    // Mietpositionen noch Freitext-Posten vorhanden, dafür aber mindestens
    // eine Arbeitszeit-Position, dürfen Zubehörpauschale und Kombirabatt
    // nicht gesetzt werden — hier rein clientseitig als sofortiges visuelles
    // Feedback (deaktivierte Steuerelemente + Hinweistext), ohne das zu
    // ersetzen.
    function updateOnlyLaborState() {
        var hasRentalOrFreeText = container.querySelectorAll('.invoice-item-row, .invoice-free-text-row').length > 0;
        var hasLabor = typeof laborContainer !== 'undefined' && laborContainer
            ? laborContainer.querySelectorAll('.invoice-labor-item-row').length > 0
            : false;
        var onlyLabor = !hasRentalOrFreeText && hasLabor;

        if (accessoryCheckbox) {
            accessoryCheckbox.disabled = onlyLabor;
            if (onlyLabor) { accessoryCheckbox.checked = false; }
        }

        document.querySelectorAll('[data-discount-option]').forEach(function (option) {
            option.disabled = onlyLabor;
            if (onlyLabor) { option.checked = option.value === ''; }
        });

        if (onlyLabor) {
            var wrapper = document.getElementById('discount-value-wrapper');
            if (wrapper) { wrapper.classList.add('hidden'); }
        }

        var accessoryHint = document.getElementById('invoice-accessory-labor-only-hint');
        var discountHint = document.getElementById('invoice-discount-labor-only-hint');
        if (accessoryHint) { accessoryHint.classList.toggle('hidden', !onlyLabor); }
        if (discountHint) { discountHint.classList.toggle('hidden', !onlyLabor); }
    }

    function updateTotals() {
        updateOnlyLaborState();

        if (!totalRentalEl || !invoiceForm) { return; }

        var rentalTotal = 0;
        container.querySelectorAll('.invoice-item-row').forEach(function (row) {
            var qty = parseAmount(row.querySelector('.invoice-item-quantity').value);
            var price = parseAmount(row.querySelector('.invoice-item-price').value);
            rentalTotal += qty * price;
        });

        var accessoryEnabled = !!(accessoryCheckbox && accessoryCheckbox.checked);
        var accessoryAmount = accessoryEnabled ? round2(rentalTotal * parseAmount(ACCESSORY_PERCENT) / 100) : 0;
        var subtotalAfterAccessory = rentalTotal + accessoryAmount;

        var discountTypeInput = invoiceForm.querySelector('input[name="discount_type"]:checked');
        var discountType = discountTypeInput ? discountTypeInput.value : '';
        var discountRaw = parseAmount(discountValueInput ? discountValueInput.value : '');
        var discountAmount = 0;
        if (discountType === 'percent') {
            discountAmount = round2(subtotalAfterAccessory * discountRaw / 100);
        } else if (discountType === 'fixed') {
            discountAmount = discountRaw;
        }
        discountAmount = Math.max(0, Math.min(discountAmount, subtotalAfterAccessory));
        var subtotalAfterDiscount = subtotalAfterAccessory - discountAmount;

        var laborTotal = 0;
        if (laborContainer) {
            laborContainer.querySelectorAll('.invoice-labor-item-row').forEach(function (row) {
                laborTotal += parseAmount(row.querySelector('.invoice-labor-item-total').value);
            });
        }

        var freeTextTotal = 0;
        if (freeTextContainer) {
            freeTextContainer.querySelectorAll('.invoice-free-text-row').forEach(function (row) {
                var qty = parseAmount(row.querySelector('.invoice-free-text-quantity').value);
                var price = parseAmount(row.querySelector('.invoice-free-text-price').value);
                freeTextTotal += qty * price;
            });
        }

        var netTotal = subtotalAfterDiscount + laborTotal + freeTextTotal;
        var vatAmount = IS_SMALL_BUSINESS ? 0 : round2(netTotal * parseAmount(VAT_RATE_PERCENT) / 100);
        var grossTotal = netTotal + vatAmount;

        totalRentalEl.textContent = formatEuro(rentalTotal);

        toggleRow(totalAccessoryRow, accessoryEnabled);
        if (accessoryEnabled) {
            totalAccessoryEl.textContent = '+' + formatEuro(accessoryAmount);
        }

        var discountVisible = discountType === 'percent' || discountType === 'fixed';
        toggleRow(totalDiscountRow, discountVisible);
        if (discountVisible) {
            totalDiscountLabelEl.textContent = discountType === 'percent'
                ? DISCOUNT_PERCENT_LABEL.replace('{rate}', formatRate(discountRaw))
                : DISCOUNT_FIXED_LABEL;
            totalDiscountEl.textContent = '−' + formatEuro(discountAmount);
        }

        toggleRow(totalLaborRow, laborTotal > 0);
        if (laborTotal > 0) {
            totalLaborEl.textContent = '+' + formatEuro(laborTotal);
        }

        toggleRow(totalFreeTextRow, freeTextTotal > 0);
        if (freeTextTotal > 0) {
            totalFreeTextEl.textContent = '+' + formatEuro(freeTextTotal);
        }

        if (totalNetEl) { totalNetEl.textContent = formatEuro(netTotal); }
        if (totalVatEl) { totalVatEl.textContent = formatEuro(vatAmount); }
        totalGrandEl.textContent = formatEuro(IS_SMALL_BUSINESS ? netTotal : grossTotal);
    }

    if (invoiceForm) {
        invoiceForm.addEventListener('input', updateTotals);
        invoiceForm.addEventListener('change', updateTotals);
        invoiceForm.addEventListener('click', updateTotals);
    }

    updateTotals();
})();
</script>

<?php endif; ?>
