<?php
/** @var \App\Domain\Offer\Offer|null $offer */
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
/** @var array<int, array{article_id: string, source: string, inventory_number_id: string, quantity: int|string, unit_price: string, line_note: string}> $existingSaleItems */
/** @var array<int, array{description: string, quantity: string, unit_price: string, sort_order: int|string}> $existingFreeTextItems */
/** @var array<int, array{heading: string, sort_order: int|string}> $existingSectionTexts */
/** @var bool $saleItemsFulfilled */
/** @var array<string, array<int, array{id: string, number: string}>> $saleInventoryNumbers Artikel-ID => verkaufbare Exemplare aus dem Vermietpool */
/** @var array<string, int> $saleStock Artikel-ID => aktueller Lagerbestand des Verkaufsbestands */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
// Ein Entwurf mit bereits abgerechneten Verkaufspositionen wird hier
// vorsorglich ebenfalls als gesperrt behandelt (OfferController::edit()
// leitet diesen Fall normalerweise bereits vorher auf die Detailseite um,
// siehe dortige Begründung) — dies ist nur ein zusätzliches Sicherheitsnetz
// für den seltenen Fall, dass der Status zwischen dem Laden des Formulars
// und dem fehlgeschlagenen Absenden "umklappt".
$locked = ($offer !== null && !$offer->isEditable()) || $saleItemsFulfilled;
$items = $old['items'] ?? $existingItems;
$laborItems = $old['labor_items'] ?? $existingLaborItems;
$saleItems = $old['sale_items'] ?? $existingSaleItems;
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
    <a href="<?= $offer !== null ? '/angebote/' . e($offer->id) : '/angebote' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('offers.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $offer !== null ? e(t('offers.edit')) : e(t('offers.new')) ?></h1>
</div>

<?php $statusLocked = $offer !== null && !$offer->isEditable(); ?>
<?php if ($locked): ?>
    <div class="mb-6 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
        <?= e(t($statusLocked ? 'offers.form.locked_notice' : 'offers.form.sale_items_locked_notice')) ?>
    </div>

    <?php if ($statusLocked): ?>
    <form method="post" action="/angebote/<?= e($editingId) ?>" class="max-w-sm space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div>
            <label for="valid_until" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.form.valid_until')) ?></label>
            <input type="date" id="valid_until" name="valid_until"
                   value="<?= e($val('valid_until', $offer?->validUntil?->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['validUntil']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['validUntil'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['validUntil']) ?></p><?php endif; ?>
        </div>
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
    </form>
    <?php else: ?>
    <a href="/angebote/<?= e($editingId) ?>" class="inline-block rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('offers.show.back')) ?></a>
    <?php endif; ?>
<?php else: ?>

<form
    method="post"
    action="<?= $editingId !== null ? '/angebote/' . e($editingId) : '/angebote' ?>"
    class="max-w-5xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
    id="offer-form"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="customer_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.form.customer')) ?></label>
            <div class="mt-1 flex gap-2">
                <select id="customer_id" name="customer_id"
                        class="w-full rounded-md border <?= isset($errors['customerId']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <option value=""><?= e(t('offers.form.customer_placeholder')) ?></option>
                    <?php $selectedCustomer = $val('customer_id', $offer?->customerId); ?>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= e($customer->id) ?>" <?= $selectedCustomer === $customer->id ? 'selected' : '' ?>><?= e($customer->displayName()) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" data-customer-quick-create-open class="shrink-0 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('customers.quick_create.title')) ?>
                </button>
            </div>
            <?php if (isset($errors['customerId'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['customerId']) ?></p><?php endif; ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('offers.form.customer_hint')) ?></p>
        </div>

        <div>
            <label for="event_location" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.form.event_location')) ?></label>
            <input type="text" id="event_location" name="event_location" list="venue-datalist"
                   value="<?= e($val('event_location', $offer?->eventLocation)) ?>"
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
            <label for="start_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.form.start_date')) ?></label>
            <input type="date" id="start_date" name="start_date" required
                   value="<?= e($val('start_date', $offer?->startDate->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['startDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['startDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['startDate']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="end_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.form.end_date')) ?></label>
            <input type="date" id="end_date" name="end_date" required
                   value="<?= e($val('end_date', $offer?->endDate->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['endDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['endDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['endDate']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="valid_until" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.form.valid_until')) ?></label>
            <input type="date" id="valid_until" name="valid_until"
                   value="<?= e($val('valid_until', $offer?->validUntil?->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['validUntil']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['validUntil'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['validUntil']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="note" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.form.note')) ?></label>
        <textarea id="note" name="note" rows="3"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"><?= e($val('note', $offer?->note)) ?></textarea>
    </div>

    <?php
        // Zubehörpauschale/Kombirabatt: siehe invoices/form.php für die
        // identische Begründung (bewusst dupliziert, gleiches Muster wie
        // der restliche Formularcode dieser beiden Views).
        $accessoryChecked = array_key_exists('accessory_flat_rate_enabled', $old)
            ? $old['accessory_flat_rate_enabled'] === '1'
            : ($offer?->accessoryFlatRateEnabled ?? true);
        $discountType = $val('discount_type', $offer?->discountType);
        $discountValue = $val('discount_value', $offer?->discountValue);
    ?>

    <?php
        // Positionen/Freitext-Posten/Zwischentexte: siehe invoices/form.php
        // für die identische Begründung (ein gemeinsamer, sortierbarer
        // Bereich statt dreier getrennter Blöcke — orderedRows()-Konzept).
    ?>
    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('offers.form.items_title')) ?></h2>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="offer-item-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('offers.form.items_add')) ?>
                </button>
                <button type="button" id="offer-free-text-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('offers.form.free_text_items_add')) ?>
                </button>
                <button type="button" id="offer-section-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('offers.form.section_texts_add')) ?>
                </button>
            </div>
        </div>
        <?php if (isset($errors['items'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['items']) ?></p><?php endif; ?>
        <?php if (isset($errors['freeTextItems'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['freeTextItems']) ?></p><?php endif; ?>
        <div id="offer-rows" class="space-y-2"></div>
        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('offers.form.items_hint')) ?></p>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('offers.form.rows_drag_hint')) ?></p>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('offers.form.labor_items_title')) ?></h2>
            <button type="button" id="offer-labor-item-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                + <?= e(t('offers.form.labor_items_add')) ?>
            </button>
        </div>
        <?php if (isset($errors['laborItems'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['laborItems']) ?></p><?php endif; ?>
        <?php if ($laborArticles === []): ?>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('offers.form.labor_items_none_defined')) ?> <a href="/arbeitszeit-artikel" class="text-brand-700 hover:underline dark:text-brand-400"><?= e(t('offers.form.labor_items_manage_link')) ?></a></p>
        <?php else: ?>
            <div id="offer-labor-items" class="space-y-2"></div>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('offers.form.labor_items_hint')) ?></p>
        <?php endif; ?>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('offers.form.sale_items_title')) ?></h2>
            <button type="button" id="offer-sale-item-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                + <?= e(t('offers.form.sale_items_add')) ?>
            </button>
        </div>
        <?php if (isset($errors['saleItems'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['saleItems']) ?></p><?php endif; ?>
        <div id="offer-sale-items" class="space-y-2"></div>
        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('offers.form.sale_items_hint')) ?></p>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="flex items-start gap-2">
            <input type="checkbox" id="accessory_flat_rate_enabled" name="accessory_flat_rate_enabled" value="1" <?= $accessoryChecked ? 'checked' : '' ?>
                   class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
            <div>
                <label for="accessory_flat_rate_enabled" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    <?= e(t('offers.form.accessory_flat_rate', ['percent' => e($pricingSettings->accessoryFlatRatePercent)])) ?>
                </label>
                <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('offers.form.accessory_flat_rate_hint')) ?></p>
                <p id="offer-accessory-labor-only-hint" class="mt-1 hidden text-xs text-amber-600 dark:text-amber-400"><?= e(t('offers.form.labor_only_hint')) ?></p>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('offers.form.discount_title')) ?></h2>
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="discount_type" value="" data-discount-option <?= $discountType === '' || $discountType === null ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('offers.form.discount_none')) ?>
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="discount_type" value="percent" data-discount-option <?= $discountType === 'percent' ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('offers.form.discount_percent')) ?>
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="discount_type" value="fixed" data-discount-option <?= $discountType === 'fixed' ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('offers.form.discount_fixed')) ?>
            </label>
        </div>
        <div id="discount-value-wrapper" class="mt-2 <?= $discountType === '' || $discountType === null ? 'hidden' : '' ?>">
            <label for="discount_value" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.form.discount_value')) ?></label>
            <input type="text" id="discount_value" name="discount_value" inputmode="decimal"
                   value="<?= e((string) ($discountValue ?? '')) ?>"
                   class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['discountValue']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['discountValue'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['discountValue']) ?></p><?php endif; ?>
        </div>
        <p id="offer-discount-labor-only-hint" class="mt-1 hidden text-xs text-amber-600 dark:text-amber-400"><?= e(t('offers.form.labor_only_hint')) ?></p>
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
        // -> Gesamtsumme). NICHT die verbindliche Berechnung — die bleibt
        // serverseitig bei ValidatesOfferInput/DocumentTotals beim Speichern;
        // diese Anzeige dient ausschließlich der sofortigen Rückmeldung beim
        // Ausfüllen des Formulars, ohne erst speichern zu müssen.
    ?>
    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('offers.form.totals_title')) ?></h2>
        <dl class="max-w-sm space-y-1 text-sm">
            <div class="flex items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('offers.show.rental_subtotal')) ?></dt>
                <dd data-total-rental>0,00 €</dd>
            </div>
            <div data-total-accessory-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('offers.show.accessory_flat_rate', ['rate' => number_format((float) ($pricingSettings->accessoryFlatRatePercent ?? '0'), 2, ',', '.')])) ?></dt>
                <dd data-total-accessory>0,00 €</dd>
            </div>
            <div data-total-discount-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt data-total-discount-label></dt>
                <dd data-total-discount>0,00 €</dd>
            </div>
            <div data-total-labor-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('offers.form.totals_labor')) ?></dt>
                <dd data-total-labor>0,00 €</dd>
            </div>
            <div data-total-sale-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('offers.form.totals_sale')) ?></dt>
                <dd data-total-sale>0,00 €</dd>
            </div>
            <div data-total-free-text-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('offers.form.totals_free_text')) ?></dt>
                <dd data-total-free-text>0,00 €</dd>
            </div>
            <div class="flex items-baseline justify-between border-t border-slate-200 pt-1.5 font-semibold text-slate-900 dark:border-slate-700 dark:text-slate-100">
                <dt><?= e(t('offers.show.grand_total')) ?></dt>
                <dd data-total-grand>0,00 €</dd>
            </div>
        </dl>
        <p class="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('offers.form.totals_hint')) ?></p>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $offer !== null ? '/angebote/' . e($offer->id) : '/angebote' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<?php require __DIR__ . '/../customers/_quick_create_modal.php'; ?>

<?php
    // Artikel-Auswahl-Modal: durchsuchbare Alternative zum <select> je
    // Positionszeile, damit man sich bei vielen Artikeln leichter das
    // Richtige zusammensuchen kann (Name-Suche, Live-Filterung). Das
    // <select> bleibt als eigentliche Datenquelle (name="article_id[]")
    // bestehen — das Modal setzt beim Klick auf einen Treffer nur dessen
    // value und feuert ein change-Event, damit Preisvorschlag und
    // Live-Gesamtsumme wie gewohnt reagieren.
?>
<div id="article-picker-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="flex max-h-[80vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e(t('offers.form.picker_title')) ?></h3>
            <button type="button" id="article-picker-close" class="text-lg leading-none text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">&times;</button>
        </div>
        <div class="p-4 pb-2">
            <input type="text" id="article-picker-search" placeholder="<?= e(t('offers.form.picker_search_placeholder')) ?>"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700" autocomplete="off">
        </div>
        <div id="article-picker-list" class="flex-1 space-y-0.5 overflow-y-auto px-2 pb-2"></div>
        <p id="article-picker-empty" class="hidden px-4 pb-4 text-xs text-slate-400 dark:text-slate-500"><?= e(t('offers.form.picker_empty')) ?></p>
    </div>
</div>

<template id="offer-item-row-template">
    <div class="offer-item-row offer-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="offer-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('offers.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.item_article')) ?></label>
            <div class="mt-1 flex gap-1">
                <select name="article_id[]" class="offer-item-article w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    <option value=""><?= e(t('offers.show.article_placeholder')) ?></option>
                    <?php foreach ($articles as $article): ?>
                        <option value="<?= e($article->id) ?>"><?= e($article->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="offer-item-pick shrink-0 rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" title="<?= e(t('offers.form.picker_button')) ?>">
                    🔍
                </button>
            </div>
        </div>
        <div class="w-20">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.item_quantity')) ?></label>
            <input type="number" name="quantity[]" min="1" value="1" class="offer-item-quantity mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.item_price')) ?></label>
            <input type="text" name="unit_price[]" inputmode="decimal" class="offer-item-price mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="min-w-[140px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.item_note')) ?></label>
            <input type="text" name="line_note[]" class="offer-item-note mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <input type="hidden" name="sort_order[]" class="offer-item-sort" data-row-sort value="0">
        <button type="button" class="offer-item-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('offers.form.item_remove')) ?>
        </button>
    </div>
</template>

<?php if ($laborArticles !== []): ?>
<template id="offer-labor-item-row-template">
    <div class="offer-labor-item-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.labor_item_article')) ?></label>
            <select name="labor_article_id[]" class="offer-labor-item-article mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                <option value=""><?= e(t('offers.form.labor_item_article_placeholder')) ?></option>
                <?php foreach ($laborArticles as $laborArticle): ?>
                    <option value="<?= e($laborArticle->id) ?>" data-rate="<?= e($laborArticle->hourlyRate) ?>">
                        <?= e($laborArticle->name) ?> (<?= e(number_format((float) $laborArticle->hourlyRate, 2, ',', '.')) ?> €/Std.)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-24">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.labor_item_hours')) ?></label>
            <input type="text" name="hours[]" inputmode="decimal" class="offer-labor-item-hours mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.labor_item_total')) ?></label>
            <input type="text" class="offer-labor-item-total mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400" readonly tabindex="-1">
        </div>
        <button type="button" class="offer-labor-item-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('offers.form.item_remove')) ?>
        </button>
    </div>
</template>
<?php endif; ?>

<?php
    // Verkaufsposition: Quelle entscheidet, ob ein konkretes Exemplar aus
    // dem Vermietpool (Select, dynamisch je Artikel befüllt, siehe Skript
    // unten) oder eine Menge aus dem Verkaufsbestand (Zahleneingabe)
    // angegeben wird — siehe ValidatesOfferInput::validateOfferInput() für
    // die serverseitige Entsprechung dieser beiden Zweige.
?>
<template id="offer-sale-item-row-template">
    <div class="offer-sale-item-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <div class="min-w-[180px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.sale_item_article')) ?></label>
            <select name="sale_article_id[]" class="offer-sale-item-article mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                <option value=""><?= e(t('offers.show.article_placeholder')) ?></option>
                <?php foreach ($articles as $article): ?>
                    <option value="<?= e($article->id) ?>"><?= e($article->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-[180px]">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.sale_item_source')) ?></label>
            <select name="sale_source[]" class="offer-sale-item-source mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                <option value="inventory_number"><?= e(t('offers.form.sale_item_source_pool')) ?></option>
                <option value="stock"><?= e(t('offers.form.sale_item_source_stock')) ?></option>
            </select>
        </div>
        <div class="offer-sale-item-inventory-wrapper min-w-[160px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.sale_item_inventory_number')) ?></label>
            <select name="sale_inventory_number_id[]" class="offer-sale-item-inventory mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                <option value=""><?= e(t('offers.form.sale_item_inventory_number_placeholder')) ?></option>
            </select>
        </div>
        <div class="offer-sale-item-quantity-wrapper hidden w-24">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.sale_item_quantity')) ?></label>
            <input type="number" name="sale_quantity[]" min="1" value="1" class="offer-sale-item-quantity mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
            <p class="offer-sale-item-stock-hint mt-0.5 text-[11px] text-slate-400 dark:text-slate-500"></p>
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.sale_item_price')) ?></label>
            <input type="text" name="sale_unit_price[]" inputmode="decimal" class="offer-sale-item-price mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="min-w-[140px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.sale_item_note')) ?></label>
            <input type="text" name="sale_line_note[]" class="offer-sale-item-note mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <button type="button" class="offer-sale-item-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('offers.form.sale_item_remove')) ?>
        </button>
    </div>
</template>

<template id="offer-free-text-row-template">
    <div class="offer-free-text-row offer-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="offer-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('offers.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.free_text_description')) ?></label>
            <input type="text" name="free_text_description[]" class="offer-free-text-description mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-20">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.item_quantity')) ?></label>
            <input type="text" name="free_text_quantity[]" inputmode="decimal" value="1" class="offer-free-text-quantity mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.item_price')) ?></label>
            <input type="text" name="free_text_unit_price[]" inputmode="decimal" value="0" class="offer-free-text-price mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <input type="hidden" name="free_text_sort_order[]" class="offer-free-text-sort" data-row-sort value="1000">
        <button type="button" class="offer-free-text-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('offers.form.item_remove')) ?>
        </button>
    </div>
</template>

<template id="offer-section-row-template">
    <div class="offer-section-row offer-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="offer-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('offers.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.form.section_heading')) ?></label>
            <input type="text" name="section_heading[]" class="offer-section-heading mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <input type="hidden" name="section_sort_order[]" class="offer-section-sort" data-row-sort value="2000">
        <button type="button" class="offer-section-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('offers.form.item_remove')) ?>
        </button>
    </div>
</template>

<script>
(function () {
    var ARTICLES = <?= json_encode($articlesJson, JSON_THROW_ON_ERROR) ?>;
    var TIER_MULTIPLIERS = <?= json_encode($tierMultipliers, JSON_THROW_ON_ERROR) ?>;
    var EXISTING_ITEMS = <?= json_encode(array_values($items), JSON_THROW_ON_ERROR) ?>;

    // Früh deklariert — siehe invoices/form.php für die identische
    // Begründung (Submit-Handler für die sort_order-Festschreibung weiter
    // unten braucht bereits beim ersten Durchlauf einen gültigen Wert).
    var offerForm = document.getElementById('offer-form');

    var container = document.getElementById('offer-rows');
    var template = document.getElementById('offer-item-row-template');
    var addButton = document.getElementById('offer-item-add');
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
        var row = fragment.querySelector('.offer-item-row');
        var articleSelect = row.querySelector('.offer-item-article');
        var quantityInput = row.querySelector('.offer-item-quantity');
        var priceInput = row.querySelector('.offer-item-price');
        var noteInput = row.querySelector('.offer-item-note');
        var sortInput = row.querySelector('.offer-item-sort');
        var removeButton = row.querySelector('.offer-item-remove');
        var pickButton = row.querySelector('.offer-item-pick');

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
        container.querySelectorAll('.offer-item-row').forEach(function (row) {
            var articleSelect = row.querySelector('.offer-item-article');
            var priceInput = row.querySelector('.offer-item-price');
            if (!priceInput.dataset.touched) {
                var suggested = suggestPrice(articleSelect.value);
                if (suggested !== null) { priceInput.value = suggested; }
            }
        });
    }

    container.addEventListener('input', function (e) {
        if (e.target.classList.contains('offer-item-price')) {
            e.target.dataset.touched = '1';
        }
    });

    addButton.addEventListener('click', function () { openArticlePicker(addRow()); });
    startInput.addEventListener('change', refreshAllSuggestions);
    endInput.addEventListener('change', refreshAllSuggestions);

    // --- Artikel-Auswahl-Modal ---------------------------------------------
    // Durchsuchbare Alternative zum <select> je Zeile (siehe Kommentar beim
    // Modal-Markup weiter unten). ARTICLE_ENTRIES wird aus dem bereits für
    // die Preisvorschläge vorhandenen ARTICLES-Objekt abgeleitet, damit die
    // Artikelliste nicht doppelt aus PHP serialisiert werden muss.
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

    if (document.getElementById('offer-labor-item-row-template')) {
        var LABOR_ARTICLES = <?= json_encode($laborArticlesJson, JSON_THROW_ON_ERROR) ?>;
        var EXISTING_LABOR_ITEMS = <?= json_encode(array_values($laborItems), JSON_THROW_ON_ERROR) ?>;

        var laborContainer = document.getElementById('offer-labor-items');
        var laborTemplate = document.getElementById('offer-labor-item-row-template');
        var laborAddButton = document.getElementById('offer-labor-item-add');

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
            var row = fragment.querySelector('.offer-labor-item-row');
            var articleSelect = row.querySelector('.offer-labor-item-article');
            var hoursInput = row.querySelector('.offer-labor-item-hours');
            var totalInput = row.querySelector('.offer-labor-item-total');
            var removeButton = row.querySelector('.offer-labor-item-remove');

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

    // --- Verkaufspositionen ------------------------------------------------
    // Quelle je Zeile: "inventory_number" (Vermietpool, konkretes Exemplar,
    // Menge immer 1) oder "stock" (Verkaufsbestand, freie Menge) — siehe
    // ValidatesOfferInput::validateOfferInput() für die serverseitige
    // Entsprechung. SALE_INVENTORY_NUMBERS/SALE_STOCK werden je Artikel aus
    // OfferController::renderForm() mitgegeben.
    if (document.getElementById('offer-sale-item-row-template')) {
        var SALE_INVENTORY_NUMBERS = <?= json_encode($saleInventoryNumbers, JSON_THROW_ON_ERROR) ?>;
        var SALE_STOCK = <?= json_encode($saleStock, JSON_THROW_ON_ERROR) ?>;
        var SALE_STOCK_HINT_TEMPLATE = <?= json_encode(t('offers.form.sale_item_stock_hint')) ?>;
        var SALE_INVENTORY_NONE_LABEL = <?= json_encode(t('offers.form.sale_item_inventory_number_none')) ?>;
        var SALE_INVENTORY_PLACEHOLDER_LABEL = <?= json_encode(t('offers.form.sale_item_inventory_number_placeholder')) ?>;
        var EXISTING_SALE_ITEMS = <?= json_encode(array_values($saleItems), JSON_THROW_ON_ERROR) ?>;

        var saleContainer = document.getElementById('offer-sale-items');
        var saleTemplate = document.getElementById('offer-sale-item-row-template');
        var saleAddButton = document.getElementById('offer-sale-item-add');

        var refreshSaleInventoryOptions = function (row, selectedInventoryNumberId) {
            var articleSelect = row.querySelector('.offer-sale-item-article');
            var inventorySelect = row.querySelector('.offer-sale-item-inventory');
            var numbers = SALE_INVENTORY_NUMBERS[articleSelect.value] || [];

            inventorySelect.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = numbers.length > 0 ? SALE_INVENTORY_PLACEHOLDER_LABEL : SALE_INVENTORY_NONE_LABEL;
            inventorySelect.appendChild(placeholder);

            numbers.forEach(function (entry) {
                var option = document.createElement('option');
                option.value = entry.id;
                option.textContent = entry.number;
                inventorySelect.appendChild(option);
            });

            if (selectedInventoryNumberId) {
                inventorySelect.value = selectedInventoryNumberId;
            }
        };

        var refreshSaleStockHint = function (row) {
            var articleSelect = row.querySelector('.offer-sale-item-article');
            var hintEl = row.querySelector('.offer-sale-item-stock-hint');
            var quantity = SALE_STOCK[articleSelect.value];
            hintEl.textContent = quantity !== undefined ? SALE_STOCK_HINT_TEMPLATE.replace('{quantity}', quantity) : '';
        };

        var refreshSaleSourceVisibility = function (row) {
            var sourceSelect = row.querySelector('.offer-sale-item-source');
            var isStock = sourceSelect.value === 'stock';
            row.querySelector('.offer-sale-item-inventory-wrapper').classList.toggle('hidden', isStock);
            row.querySelector('.offer-sale-item-quantity-wrapper').classList.toggle('hidden', !isStock);
        };

        var addSaleRow = function (data) {
            var fragment = saleTemplate.content.cloneNode(true);
            var row = fragment.querySelector('.offer-sale-item-row');
            var articleSelect = row.querySelector('.offer-sale-item-article');
            var sourceSelect = row.querySelector('.offer-sale-item-source');
            var quantityInput = row.querySelector('.offer-sale-item-quantity');
            var priceInput = row.querySelector('.offer-sale-item-price');
            var noteInput = row.querySelector('.offer-sale-item-note');
            var removeButton = row.querySelector('.offer-sale-item-remove');

            data = data || {};
            if (data.article_id) { articleSelect.value = data.article_id; }
            if (data.source) { sourceSelect.value = data.source === 'inventory_number' ? 'inventory_number' : 'stock'; }
            if (data.quantity) { quantityInput.value = data.quantity; }
            if (data.unit_price !== undefined) { priceInput.value = data.unit_price; }
            if (data.line_note) { noteInput.value = data.line_note; }

            refreshSaleInventoryOptions(row, data.inventory_number_id || '');
            refreshSaleStockHint(row);
            refreshSaleSourceVisibility(row);

            articleSelect.addEventListener('change', function () {
                refreshSaleInventoryOptions(row, '');
                refreshSaleStockHint(row);
            });
            sourceSelect.addEventListener('change', function () { refreshSaleSourceVisibility(row); });
            removeButton.addEventListener('click', function () { row.remove(); });

            saleContainer.appendChild(row);
        };

        if (saleAddButton) {
            saleAddButton.addEventListener('click', function () { addSaleRow(); });
        }
        if (saleContainer) {
            EXISTING_SALE_ITEMS.forEach(function (item) { addSaleRow(item); });
        }
    }

    // --- Freitext-Posten/Zwischentexte (orderedRows()-Konzept) -------------
    // Siehe order-confirmations/form.php für die identische Begründung
    // dieses Musters (dort zuerst eingeführt).
    var EXISTING_FREE_TEXT_ITEMS = <?= json_encode(array_values($freeTextItems), JSON_THROW_ON_ERROR) ?>;
    var EXISTING_SECTION_TEXTS = <?= json_encode(array_values($sectionTexts), JSON_THROW_ON_ERROR) ?>;

    // freeTextContainer/sectionContainer sind bewusst DASSELBE Element wie
    // "container" (= #offer-rows) — siehe invoices/form.php für die
    // identische Begründung (gemeinsamer, per Drag-and-Drop sortierbarer
    // Bereich für Positionen/Freitext-Posten/Zwischentexte).
    var freeTextContainer = container;
    var sectionContainer = container;
    var freeTextTemplate = document.getElementById('offer-free-text-row-template');
    var freeTextAddButton = document.getElementById('offer-free-text-add');
    var sectionTemplate = document.getElementById('offer-section-row-template');
    var sectionAddButton = document.getElementById('offer-section-add');

    function addFreeTextRow(data) {
        var fragment = freeTextTemplate.content.cloneNode(true);
        var row = fragment.querySelector('.offer-free-text-row');
        data = data || {};
        if (data.description) { row.querySelector('.offer-free-text-description').value = data.description; }
        if (data.quantity) { row.querySelector('.offer-free-text-quantity').value = data.quantity; }
        if (data.unit_price !== undefined) { row.querySelector('.offer-free-text-price').value = data.unit_price; }
        row.querySelector('.offer-free-text-sort').value = 0;
        row.querySelector('.offer-free-text-remove').addEventListener('click', function () { row.remove(); updateOnlyLaborState(); });
        enableRowDrag(row);
        freeTextContainer.appendChild(row);
        updateOnlyLaborState();
    }

    if (freeTextAddButton) {
        freeTextAddButton.addEventListener('click', function () { addFreeTextRow(); });
    }

    function addSectionRow(data) {
        var fragment = sectionTemplate.content.cloneNode(true);
        var row = fragment.querySelector('.offer-section-row');
        data = data || {};
        if (data.heading) { row.querySelector('.offer-section-heading').value = data.heading; }
        row.querySelector('.offer-section-sort').value = 0;
        row.querySelector('.offer-section-remove').addEventListener('click', function () { row.remove(); });
        enableRowDrag(row);
        sectionContainer.appendChild(row);
    }

    if (sectionAddButton) {
        sectionAddButton.addEventListener('click', function () { addSectionRow(); });
    }

    // --- Drag-and-Drop-Umsortierung -----------------------------------------
    // Siehe invoices/form.php für die identische Begründung/Funktionsweise.
    var draggingRow = null;

    function enableRowDrag(row) {
        var handle = row.querySelector('.offer-row-handle');
        if (!handle) { return; }
        handle.addEventListener('mousedown', function () { row.draggable = true; });
        row.addEventListener('dragend', function () { row.draggable = false; draggingRow = null; });
    }

    container.addEventListener('dragstart', function (e) {
        var row = e.target.closest('.offer-row');
        if (!row) { return; }
        draggingRow = row;
        e.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragover', function (e) {
        if (!draggingRow) { return; }
        e.preventDefault();
        var row = e.target.closest('.offer-row');
        if (!row || row === draggingRow) { return; }
        var rect = row.getBoundingClientRect();
        var placeBefore = (e.clientY - rect.top) < rect.height / 2;
        container.insertBefore(draggingRow, placeBefore ? row : row.nextSibling);
    });

    // --- Reihenfolge beim Absenden festschreiben ----------------------------
    // Siehe invoices/form.php für die identische Begründung/Funktionsweise.
    if (offerForm) {
        offerForm.addEventListener('submit', function () {
            var order = 0;
            container.querySelectorAll('[data-row-sort]').forEach(function (input) {
                input.value = order++;
            });
        });
    }

    // --- Vorhandene Zeilen in der tatsächlichen Dokumentreihenfolge rendern -
    // Siehe invoices/form.php für die identische Begründung/Funktionsweise.
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
    // Rein clientseitige Vorschau (siehe Aufgabenstellung: die Summe soll
    // sich direkt in der Ansicht aktualisieren, nicht erst nach dem
    // Speichern), spiegelt App\Domain\Pricing\DocumentTotals wider
    // (Mietpreise -> Zubehörpauschale -> Kombirabatt -> Arbeitszeit ->
    // Gesamtsumme). Die serverseitige Berechnung (ValidatesOfferInput /
    // DocumentTotals beim Speichern) bleibt die einzige verbindliche Quelle
    // — diese Anzeige dient ausschließlich der sofortigen Rückmeldung.
    //
    // Statt jede einzelne Stelle, die Zeilen hinzufügt/entfernt/befüllt, um
    // einen expliziten updateTotals()-Aufruf zu ergänzen, wird hier bewusst
    // EIN delegierter Listener je Ereignistyp auf dem Formular selbst
    // registriert: "input"/"change" decken Mengen-/Preis-/Stunden-/
    // Rabatt-Eingaben sowie Artikel-/Checkbox-/Radio-Änderungen ab (auch für
    // später per addRow()/addLaborRow() hinzugefügte Zeilen, da das
    // Formular deren Vorfahre ist), "click" deckt das Hinzufügen/Entfernen
    // einer Zeile ab (die jeweilige Änderung am DOM ist zum Zeitpunkt, an
    // dem das Ereignis am Formular ankommt, bereits erfolgt, da sie im
    // eigenen — näher am Ereignisziel liegenden — Klick-Handler der
    // jeweiligen Schaltfläche synchron VOR dem Weiterblubbern passiert).
    var ACCESSORY_PERCENT = <?= json_encode($pricingSettings->accessoryFlatRatePercent ?? '0') ?>;
    var DISCOUNT_PERCENT_LABEL = <?= json_encode(t('offers.show.discount_percent')) ?>;
    var DISCOUNT_FIXED_LABEL = <?= json_encode(t('offers.show.discount_fixed')) ?>;

    var offerForm = document.getElementById('offer-form');
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
    var totalSaleRow = document.querySelector('[data-total-sale-row]');
    var totalSaleEl = document.querySelector('[data-total-sale]');
    var totalFreeTextRow = document.querySelector('[data-total-free-text-row]');
    var totalFreeTextEl = document.querySelector('[data-total-free-text]');
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
    // Siehe invoices/form.php für die identische Begründung/Funktionsweise.
    // Verkaufspositionen (saleContainer) bleiben hier bewusst unberücksichtigt
    // — siehe ValidatesOfferInput::validateOfferInput() für die serverseitige
    // Entsprechung.
    function updateOnlyLaborState() {
        var hasRentalOrFreeText = container.querySelectorAll('.offer-item-row, .offer-free-text-row').length > 0;
        var hasLabor = typeof laborContainer !== 'undefined' && laborContainer
            ? laborContainer.querySelectorAll('.offer-labor-item-row').length > 0
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

        var accessoryHint = document.getElementById('offer-accessory-labor-only-hint');
        var discountHint = document.getElementById('offer-discount-labor-only-hint');
        if (accessoryHint) { accessoryHint.classList.toggle('hidden', !onlyLabor); }
        if (discountHint) { discountHint.classList.toggle('hidden', !onlyLabor); }
    }

    function updateTotals() {
        updateOnlyLaborState();

        if (!totalRentalEl || !offerForm) { return; }

        var rentalTotal = 0;
        container.querySelectorAll('.offer-item-row').forEach(function (row) {
            var qty = parseAmount(row.querySelector('.offer-item-quantity').value);
            var price = parseAmount(row.querySelector('.offer-item-price').value);
            rentalTotal += qty * price;
        });

        var accessoryEnabled = !!(accessoryCheckbox && accessoryCheckbox.checked);
        var accessoryAmount = accessoryEnabled ? round2(rentalTotal * parseAmount(ACCESSORY_PERCENT) / 100) : 0;
        var subtotalAfterAccessory = rentalTotal + accessoryAmount;

        var discountTypeInput = offerForm.querySelector('input[name="discount_type"]:checked');
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
            laborContainer.querySelectorAll('.offer-labor-item-row').forEach(function (row) {
                laborTotal += parseAmount(row.querySelector('.offer-labor-item-total').value);
            });
        }

        var saleTotal = 0;
        if (saleContainer) {
            saleContainer.querySelectorAll('.offer-sale-item-row').forEach(function (row) {
                var qty = parseAmount(row.querySelector('.offer-sale-item-quantity').value);
                var price = parseAmount(row.querySelector('.offer-sale-item-price').value);
                saleTotal += qty * price;
            });
        }

        var freeTextTotal = 0;
        if (freeTextContainer) {
            freeTextContainer.querySelectorAll('.offer-free-text-row').forEach(function (row) {
                var qty = parseAmount(row.querySelector('.offer-free-text-quantity').value);
                var price = parseAmount(row.querySelector('.offer-free-text-price').value);
                freeTextTotal += qty * price;
            });
        }

        var netTotal = subtotalAfterDiscount + laborTotal + saleTotal + freeTextTotal;

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

        toggleRow(totalSaleRow, saleTotal > 0);
        if (saleTotal > 0) {
            totalSaleEl.textContent = '+' + formatEuro(saleTotal);
        }

        toggleRow(totalFreeTextRow, freeTextTotal > 0);
        if (freeTextTotal > 0) {
            totalFreeTextEl.textContent = '+' + formatEuro(freeTextTotal);
        }

        totalGrandEl.textContent = formatEuro(netTotal);
    }

    if (offerForm) {
        offerForm.addEventListener('input', updateTotals);
        offerForm.addEventListener('change', updateTotals);
        offerForm.addEventListener('click', updateTotals);
    }

    updateTotals();
})();
</script>

<?php endif; ?>
