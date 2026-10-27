<?php
/** @var \App\Domain\Invoice\Invoice|null $invoice */
/** @var \App\Domain\Customer\Customer[] $customers */
/** @var \App\Domain\Venue\Venue[] $venues */
/** @var \App\Domain\Article\Article[] $articles */
/** @var array<string, string> $articleDisplayNames Artikel-ID => "Hersteller Artikelbezeichnung" */
/** @var array<int, string> $tierMultipliers Einsatztage (1..7) => Faktor bezogen auf Tagesmietsatz "1" */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var array<int, array{article_id: string, quantity: int|string, unit_price: string, line_note: string, sort_order: int|string}> $existingItems */
/** @var \App\Domain\LaborTime\LaborTimeArticle[] $laborArticles */
/** @var \App\Domain\User\User[] $users */
/** @var array<int, array{labor_article_id: string, labor_user_id: string, hours: string}> $existingLaborItems */
/** @var array<int, array{description: string, quantity: string, unit_price: string, sort_order: int|string}> $existingFreeTextItems */
/** @var array<int, array{heading: string, sort_order: int|string}> $existingSectionTexts */
/** @var bool $isSmallBusinessPreview Siehe InvoiceController::renderForm() — identisches Fallback-Muster wie InvoiceDetail::totals(). */
/** @var string $vatRatePercentPreview */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\renderLiveSearchField;
use function App\Infrastructure\Http\t;

/**
 * Rich-Text-Editorfeld für den dokumenteigenen Schlusstext (siehe
 * Invoice::$closingText) — identisches Muster (contenteditable +
 * execCommand + verstecktes Speicherfeld) wie der globale Einleitungs-/
 * Schlusstext in resources/views/settings/invoice-pdf-template.php,
 * bewusst erneut dupliziert statt per Include geteilt (siehe dortiger
 * Kommentar zum selben Muster). Das `submit`-Sync-Skript hängt sich hier
 * generisch über `closest('form')` ein statt an eine feste Formular-
 * Action zu koppeln, da dieses Formular je nach Neuanlage/Bearbeitung
 * unterschiedliche `action`-URLs hat.
 */
if (!function_exists('renderDocumentClosingTextField')) {
    function renderDocumentClosingTextField(string $name, string $value, ?string $error): void
    {
        ?>
    <div data-rt-field data-rt-name="<?= e($name) ?>" class="rt-field">
        <div data-rt-toolbar class="mb-0 hidden flex-wrap gap-1 rounded-t-md border border-b-0 border-slate-300 bg-slate-50 p-1.5 dark:border-slate-700 dark:bg-slate-800">
            <button type="button" data-cmd="bold" title="<?= e(t('rich_text.toolbar.bold')) ?>" class="rounded px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">F</button>
            <button type="button" data-cmd="italic" title="<?= e(t('rich_text.toolbar.italic')) ?>" class="rounded px-2 py-1 text-xs italic text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">K</button>
            <button type="button" data-cmd="underline" title="<?= e(t('rich_text.toolbar.underline')) ?>" class="rounded px-2 py-1 text-xs underline text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">U</button>
            <select data-heading-select title="<?= e(t('rich_text.toolbar.heading')) ?>" class="rounded border border-slate-300 bg-white px-1.5 py-1 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <option value=""><?= e(t('rich_text.toolbar.heading_placeholder')) ?></option>
                <option value="H1"><?= e(t('rich_text.toolbar.heading1')) ?></option>
                <option value="H2"><?= e(t('rich_text.toolbar.heading2')) ?></option>
                <option value="H3"><?= e(t('rich_text.toolbar.heading3')) ?></option>
                <option value="P"><?= e(t('rich_text.toolbar.paragraph')) ?></option>
            </select>
            <button type="button" data-cmd="insertUnorderedList" title="<?= e(t('rich_text.toolbar.bullet_list')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"><?= e(t('rich_text.toolbar.bullet_list')) ?></button>
            <button type="button" data-cmd="insertOrderedList" title="<?= e(t('rich_text.toolbar.numbered_list')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"><?= e(t('rich_text.toolbar.numbered_list')) ?></button>
            <span class="mx-0.5 w-px self-stretch bg-slate-300 dark:bg-slate-600"></span>
            <button type="button" data-cmd="justifyLeft" title="<?= e(t('rich_text.toolbar.align_left')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">⇤</button>
            <button type="button" data-cmd="justifyCenter" title="<?= e(t('rich_text.toolbar.align_center')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">⇔</button>
            <button type="button" data-cmd="justifyRight" title="<?= e(t('rich_text.toolbar.align_right')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">⇥</button>
        </div>
        <div
            data-rt-editable
            contenteditable="true"
            class="hidden min-h-[8rem] rounded-b-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950"
        ></div>
        <textarea
            data-rt-source
            name="<?= e($name) ?>"
            rows="6"
            class="w-full rounded-md border <?= $error !== null ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
        ><?= e($value) ?></textarea>
        <?php if ($error !== null): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($error) ?></p><?php endif; ?>
    </div>
        <?php
    }
}

/**
 * "Vorlage einfügen"-Button neben einem per renderDocumentClosingTextField()
 * gerenderten Feld — öffnet das Modal aus renderClosingTextTemplatePicker()
 * (einmal pro Formular gerendert, siehe unten) und zielt per
 * data-template-picker-trigger="<name>" auf [data-rt-name="<name>"].
 */
if (!function_exists('renderClosingTextTemplatePickerButton')) {
    function renderClosingTextTemplatePickerButton(string $targetFieldName): void
    {
        ?>
        <button
            type="button"
            data-template-picker-trigger="<?= e($targetFieldName) ?>"
            class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"
        >Vorlage einfügen</button>
        <?php
    }
}

/**
 * Modal für den Textvorlagen-Picker (siehe
 * App\Infrastructure\Http\Controllers\ClosingTextTemplateController::list()
 * für die geladenen Daten und das zugehörige <script> weiter unten in
 * dieser Datei für die Interaktion) — wird EINMAL pro Formular gerendert,
 * unabhängig davon wie viele renderClosingTextTemplatePickerButton()-Buttons
 * es gibt.
 */
if (!function_exists('renderClosingTextTemplatePickerModal')) {
    function renderClosingTextTemplatePickerModal(): void
    {
        ?>
        <div data-template-picker-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <div class="flex max-h-[80vh] w-full max-w-md flex-col rounded-lg bg-white shadow-xl dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Textvorlage einfügen</h2>
                    <button type="button" data-template-picker-close class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">&times;</button>
                </div>
                <div data-template-picker-body class="flex-1 overflow-y-auto px-4 py-3 text-sm">
                    <p class="text-slate-400 dark:text-slate-500">Lädt…</p>
                </div>
            </div>
        </div>
        <?php
    }
}

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
$locked = $invoice !== null && !$invoice->isEditable();
$items = $old['items'] ?? $existingItems;
$laborItems = $old['labor_items'] ?? $existingLaborItems;
$freeTextItems = $old['free_text_items'] ?? $existingFreeTextItems;
$sectionTexts = $old['section_texts'] ?? $existingSectionTexts;

$articlesJson = [];
foreach ($articles as $article) {
    $articlesJson[$article->id] = [
        'name' => $articleDisplayNames[$article->id] ?? $article->name,
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
                <div class="flex-1">
                    <?php
                    $selectedCustomer = $val('customer_id', $invoice?->customerId);
                    $selectedCustomerLabel = null;
                    foreach ($customers as $customer) {
                        if ($customer->id === $selectedCustomer) {
                            $selectedCustomerLabel = $customer->displayName();
                            break;
                        }
                    }
                    renderLiveSearchField(
                        'customer_id',
                        '/kunden/suche',
                        $selectedCustomer !== '' ? $selectedCustomer : null,
                        $selectedCustomerLabel,
                        t('invoices.form.customer_placeholder'),
                        $errors['customerId'] ?? null,
                        'select',
                    );
                    ?>
                </div>
                <button type="button" data-customer-quick-create-open class="shrink-0 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('customers.quick_create.title')) ?>
                </button>
            </div>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.customer_hint')) ?></p>
        </div>

        <div>
            <label for="event_location" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.event_location')) ?></label>
            <div class="mt-1">
                <?php
                renderLiveSearchField(
                    'event_location',
                    '/veranstaltungsorte/suche',
                    null,
                    $val('event_location', $invoice?->eventLocation),
                    t('invoices.form.event_location'),
                    null,
                    'freetext',
                );
                ?>
            </div>
        </div>
    </div>

    <?php
        // Veranstaltungs-Verknüpfung (optional): Live-Suche gegen eine
        // bestehende Veranstaltung (Rental), analog Kunde/Veranstaltungsort
        // oben (siehe RentalController::search()/UseCase-Entscheidung aus
        // der Aufgabenstellung "Pauschalpreis/Veranstaltung bei
        // Rechnungen"). Rein informativ/organisatorisch — ändert NICHT die
        // Kopfdaten der Rechnung (Kunde/Zeitraum bleiben unabhängig
        // editierbar), belegt aber beim Auswählen die Logistik-Termine
        // weiter unten vor, sofern diese noch leer sind (siehe Skript
        // weiter unten).
        $selectedRentalIdVal = $val('rental_id', $selectedRentalId);
    ?>
    <div>
        <label for="rental_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.rental')) ?></label>
        <div class="mt-1 max-w-md">
            <?php
            renderLiveSearchField(
                'rental_id',
                '/veranstaltungen/suche',
                $selectedRentalIdVal !== '' ? $selectedRentalIdVal : null,
                $selectedRentalLabel,
                t('invoices.form.rental_placeholder'),
                $errors['rentalId'] ?? null,
                'select',
            );
            ?>
        </div>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.rental_hint')) ?></p>
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
        // Logistik bei Vermietungen: Abholung/Rückgabe durch den Kunden
        // ('pickup', Standard) vs. Lieferung/Abholung durch uns
        // ('delivery') — siehe Invoice::$logisticsMethod-Klassendoc. Beide
        // Termine optional und unabhängig von einer verknüpften
        // Veranstaltung frei editierbar.
        $logisticsMethodVal = $val('logistics_method', $invoice?->logisticsMethod) ?: 'pickup';
        $logisticsDate1Val = $val('logistics_date1', $invoice?->logisticsDate1?->format('Y-m-d\TH:i'));
        $logisticsDate2Val = $val('logistics_date2', $invoice?->logisticsDate2?->format('Y-m-d\TH:i'));
    ?>
    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.form.logistics_title')) ?></h2>
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="logistics_method" value="pickup" data-logistics-option <?= $logisticsMethodVal === 'pickup' ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('invoices.form.logistics_pickup')) ?>
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="logistics_method" value="delivery" data-logistics-option <?= $logisticsMethodVal === 'delivery' ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('invoices.form.logistics_delivery')) ?>
            </label>
        </div>
        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="logistics_date1" id="logistics-date1-label" class="block text-xs font-medium text-slate-600 dark:text-slate-400"></label>
                <input type="datetime-local" id="logistics_date1" name="logistics_date1"
                       value="<?= e($logisticsDate1Val ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <div>
                <label for="logistics_date2" id="logistics-date2-label" class="block text-xs font-medium text-slate-600 dark:text-slate-400"></label>
                <input type="datetime-local" id="logistics_date2" name="logistics_date2"
                       value="<?= e($logisticsDate2Val ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
        </div>
        <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.logistics_hint')) ?></p>
        <script>
            (function () {
                var date1Label = document.getElementById('logistics-date1-label');
                var date2Label = document.getElementById('logistics-date2-label');
                var options = document.querySelectorAll('[data-logistics-option]');
                var LABELS = {
                    pickup: { date1: <?= json_encode(t('invoices.form.logistics_pickup_date1')) ?>, date2: <?= json_encode(t('invoices.form.logistics_pickup_date2')) ?> },
                    delivery: { date1: <?= json_encode(t('invoices.form.logistics_delivery_date1')) ?>, date2: <?= json_encode(t('invoices.form.logistics_delivery_date2')) ?> }
                };
                function applyLabels() {
                    var checked = document.querySelector('[data-logistics-option]:checked');
                    var method = checked ? checked.value : 'pickup';
                    date1Label.textContent = LABELS[method].date1;
                    date2Label.textContent = LABELS[method].date2;
                }
                options.forEach(function (option) { option.addEventListener('change', applyLabels); });
                applyLabels();

                // Vorbelegung aus der verknüpften Veranstaltung (siehe
                // RentalController::logistics()) — nur wenn beide Felder
                // noch leer sind, damit eine bereits getroffene eigene
                // Eingabe nicht überschrieben wird.
                var rentalHidden = document.querySelector('input[name="rental_id"][data-live-search-value]');
                var date1Input = document.getElementById('logistics_date1');
                var date2Input = document.getElementById('logistics_date2');

                function prefillFromRental(rentalId) {
                    if (!rentalId || date1Input.value || date2Input.value) { return; }
                    fetch('/veranstaltungen/' + encodeURIComponent(rentalId) + '/logistik')
                        .then(function (response) { return response.ok ? response.json() : null; })
                        .then(function (data) {
                            if (!data) { return; }
                            if (data.pickupAt && !date1Input.value) { date1Input.value = data.pickupAt; }
                            if (data.returnAt && !date2Input.value) { date2Input.value = data.returnAt; }
                        })
                        .catch(function () { /* Verbindungsprobleme ignorieren — Termine bleiben frei editierbar */ });
                }

                if (rentalHidden) {
                    rentalHidden.addEventListener('change', function () { prefillFromRental(rentalHidden.value); });
                }
            })();
        </script>
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

    <?php
        // Pauschalpreis für Mietpositionen (siehe Invoice::$flatRateEnabled-
        // Klassendoc, AskUserQuestion-Entscheidung "Positionen ohne Preis +
        // Pauschalsumme" / "nur für Miet-Positionen"): die Positionen
        // bleiben oben mit Menge/Bezeichnung sichtbar, nur die Einzelpreise
        // werden auf der Rechnung (nicht im Formular) ausgeblendet; statt
        // der Summe der Mietpositionen zählt flat_rate_amount. Positionen
        // mit abgewählter "Mietposition"-Checkbox (siehe Zeilen-Template
        // oben) bleiben davon unberührt und normal einzeln bepreist.
        $flatRateCheckedVal = array_key_exists('flat_rate_enabled', $old)
            ? $old['flat_rate_enabled'] === '1'
            : ($invoice?->flatRateEnabled ?? false);
        $flatRateAmountVal = $val('flat_rate_amount', $invoice?->flatRateAmount);
    ?>
    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="flex items-start gap-2">
            <input type="checkbox" id="flat_rate_enabled" name="flat_rate_enabled" value="1" <?= $flatRateCheckedVal ? 'checked' : '' ?>
                   class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
            <div class="flex-1">
                <label for="flat_rate_enabled" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    <?= e(t('invoices.form.flat_rate_enabled')) ?>
                </label>
                <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.flat_rate_hint')) ?></p>
                <div id="flat-rate-amount-wrapper" class="mt-2 max-w-[12rem] <?= $flatRateCheckedVal ? '' : 'hidden' ?>">
                    <label for="flat_rate_amount" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.form.flat_rate_amount')) ?></label>
                    <input type="text" id="flat_rate_amount" name="flat_rate_amount" inputmode="decimal"
                           value="<?= e((string) ($flatRateAmountVal ?? '')) ?>"
                           class="mt-1 w-full rounded-md border <?= isset($errors['flatRateAmount']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if (isset($errors['flatRateAmount'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['flatRateAmount']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var checkbox = document.getElementById('flat_rate_enabled');
                var wrapper = document.getElementById('flat-rate-amount-wrapper');
                if (checkbox && wrapper) {
                    checkbox.addEventListener('change', function () { wrapper.classList.toggle('hidden', !checkbox.checked); });
                }
            })();
        </script>
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
            <div data-total-sale-row class="hidden items-baseline justify-between text-slate-600 dark:text-slate-400">
                <dt><?= e(t('invoices.form.totals_sale')) ?></dt>
                <dd data-total-sale>0,00 €</dd>
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

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="flex items-baseline justify-between">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.form.closing_text')) ?></label>
            <?php renderClosingTextTemplatePickerButton('closing_text'); ?>
        </div>
        <p class="mb-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.form.closing_text_hint')) ?></p>
        <?php renderDocumentClosingTextField('closing_text', $val('closing_text', $invoice?->closingText), $errors['closingText'] ?? null); ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $invoice !== null ? '/rechnungen/' . e($invoice->id) : '/rechnungen' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<?php renderClosingTextTemplatePickerModal(); ?>

<?php require __DIR__ . '/../customers/_quick_create_modal.php'; ?>

<?php
    // Das frühere separate Artikel-Auswahl-Modal (Lupen-Button je Zeile +
    // eigenes Such-Overlay, client-seitig über alle ARTICLES gefiltert)
    // entfällt mit der Umstellung auf die Live-Suche: die Zeile hat die
    // Suche jetzt direkt inline (siehe App\Infrastructure\Http\
    // renderLiveSearchField() im Template unten, serverseitige AJAX-Suche
    // gegen /artikel/suche statt vollständig vorgeladener Artikelliste).
?>

<template id="invoice-item-row-template">
    <div class="invoice-item-row invoice-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="invoice-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('invoices.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.item_article')) ?></label>
            <div class="invoice-item-article-wrap mt-1">
                <?php
                renderLiveSearchField(
                    'article_id[]',
                    '/artikel/suche',
                    null,
                    null,
                    t('invoices.show.article_placeholder'),
                    null,
                    'select',
                    false,
                    false,
                );
                ?>
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
        <div class="flex items-center gap-1.5 pb-1.5">
            <input type="checkbox" name="is_rental_item[]" value="1" checked class="invoice-item-is-rental h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
            <label class="text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.item_is_rental')) ?></label>
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
        <div class="min-w-[160px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.form.labor_item_user')) ?></label>
            <select name="labor_user_id[]" class="invoice-labor-item-user mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                <option value=""><?= e(t('invoices.form.labor_item_user_placeholder')) ?></option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e($user->id) ?>"><?= e($user->displayName()) ?></option>
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
        var articleWidget = row.querySelector('[data-live-search]');
        var articleInput = articleWidget.querySelector('[data-live-search-input]');
        var articleHidden = articleWidget.querySelector('[data-live-search-value]');
        var quantityInput = row.querySelector('.invoice-item-quantity');
        var priceInput = row.querySelector('.invoice-item-price');
        var noteInput = row.querySelector('.invoice-item-note');
        var isRentalInput = row.querySelector('.invoice-item-is-rental');
        var sortInput = row.querySelector('.invoice-item-sort');
        var removeButton = row.querySelector('.invoice-item-remove');

        data = data || {};
        if (data.article_id) {
            articleHidden.value = data.article_id;
            var knownArticle = ARTICLES[data.article_id];
            articleInput.value = knownArticle ? knownArticle.name : '';
        }
        if (data.quantity) { quantityInput.value = data.quantity; }
        if (data.line_note) { noteInput.value = data.line_note; }
        if (isRentalInput) { isRentalInput.checked = data.is_rental_item === undefined || data.is_rental_item === '1' || data.is_rental_item === true; }
        sortInput.value = data.sort_order !== undefined ? data.sort_order : nextSortOrder++;

        if (data.unit_price !== undefined && data.unit_price !== '') {
            priceInput.value = data.unit_price;
        } else {
            var suggested = suggestPrice(articleHidden.value);
            if (suggested !== null) { priceInput.value = suggested; }
        }

        articleHidden.addEventListener('change', function () {
            var suggested = suggestPrice(articleHidden.value);
            if (suggested !== null) { priceInput.value = suggested; }
        });

        removeButton.addEventListener('click', function () { row.remove(); updateOnlyLaborState(); });

        enableRowDrag(row);
        container.appendChild(row);
        // Live-Suche erst initialisieren, nachdem Vorbelegung (article_id/
        // Name) und Event-Listener gesetzt sind, siehe public/assets/js/
        // live-search.js — die Widget-Initialisierung liest input.value/
        // hidden.value als zunächst "bestätigten" Zustand.
        window.LiveSearch.init(row);
        updateOnlyLaborState();
        return articleInput;
    }

    function refreshAllSuggestions() {
        container.querySelectorAll('.invoice-item-row').forEach(function (row) {
            var articleHidden = row.querySelector('[data-live-search-value]');
            var priceInput = row.querySelector('.invoice-item-price');
            if (!priceInput.dataset.touched) {
                var suggested = suggestPrice(articleHidden.value);
                if (suggested !== null) { priceInput.value = suggested; }
            }
        });
    }

    container.addEventListener('input', function (e) {
        if (e.target.classList.contains('invoice-item-price')) {
            e.target.dataset.touched = '1';
        }
    });

    addButton.addEventListener('click', function () {
        var articleInput = addRow();
        window.setTimeout(function () { articleInput.focus(); }, 0);
    });
    startInput.addEventListener('change', refreshAllSuggestions);
    endInput.addEventListener('change', refreshAllSuggestions);

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
            var userSelect = row.querySelector('.invoice-labor-item-user');
            var hoursInput = row.querySelector('.invoice-labor-item-hours');
            var totalInput = row.querySelector('.invoice-labor-item-total');
            var removeButton = row.querySelector('.invoice-labor-item-remove');

            data = data || {};
            if (data.labor_article_id) { articleSelect.value = data.labor_article_id; }
            if (data.labor_user_id && userSelect) { userSelect.value = data.labor_user_id; }
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
    var totalSaleRow = document.querySelector('[data-total-sale-row]');
    var totalSaleEl = document.querySelector('[data-total-sale]');
    var flatRateCheckbox = document.getElementById('flat_rate_enabled');
    var flatRateAmountInput = document.getElementById('flat_rate_amount');
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

        // Mietpositionen (is_rental_item angehakt, Standard) und Verkaufs-/
        // Sonstige Positionen (abgewählt) getrennt aufsummieren — siehe
        // Invoice::$flatRateEnabled-Klassendoc: nur die Mietpositionen
        // können durch den Pauschalbetrag ersetzt werden, Verkaufs-/
        // Sonstige Positionen zählen immer normal (wie freeTextTotal/
        // laborTotal NACH dem Kombirabatt).
        var actualRentalTotal = 0;
        var saleTotal = 0;
        container.querySelectorAll('.invoice-item-row').forEach(function (row) {
            var qty = parseAmount(row.querySelector('.invoice-item-quantity').value);
            var price = parseAmount(row.querySelector('.invoice-item-price').value);
            var isRentalCheckbox = row.querySelector('.invoice-item-is-rental');
            if (isRentalCheckbox && !isRentalCheckbox.checked) {
                saleTotal += qty * price;
            } else {
                actualRentalTotal += qty * price;
            }
        });

        var flatRateEnabled = !!(flatRateCheckbox && flatRateCheckbox.checked);
        var rentalTotal = flatRateEnabled ? parseAmount(flatRateAmountInput ? flatRateAmountInput.value : '') : actualRentalTotal;

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

        var netTotal = subtotalAfterDiscount + laborTotal + freeTextTotal + saleTotal;
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

        toggleRow(totalSaleRow, saleTotal > 0);
        if (saleTotal > 0) {
            totalSaleEl.textContent = '+' + formatEuro(saleTotal);
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

<script>
  // WYSIWYG-Editor für den dokumenteigenen Schlusstext (siehe
  // renderDocumentClosingTextField() oben in dieser Datei). Identisches
  // Muster wie das Skript in resources/views/settings/invoice-pdf-
  // template.php (bewusst dupliziert, siehe dortiger Kommentar), mit EINEM
  // Unterschied: der Submit-Sync hängt sich hier über `closest('form')`
  // an statt an eine feste `form[action="..."]`-Auswahl, da dieses
  // Formular je nach Neuanlage/Bearbeitung unterschiedliche `action`-URLs
  // hat.
  (function () {
    try {
      document.execCommand('defaultParagraphSeparator', false, 'p');
    } catch (e) { /* ältere Browser: einfach ignorieren, <div> wird ebenfalls verstanden */ }

    document.querySelectorAll('[data-rt-field]').forEach(function (field) {
      var toolbar = field.querySelector('[data-rt-toolbar]');
      var editable = field.querySelector('[data-rt-editable]');
      var textarea = field.querySelector('[data-rt-source]');

      if (!toolbar || !editable || !textarea) {
        return;
      }

      editable.innerHTML = textarea.value || '<p></p>';
      toolbar.classList.remove('hidden');
      toolbar.classList.add('flex');
      editable.classList.remove('hidden');
      textarea.classList.add('hidden');

      function sync() {
        textarea.value = editable.innerHTML;
      }

      editable.addEventListener('input', sync);
      editable.addEventListener('blur', sync);

      toolbar.querySelectorAll('[data-cmd]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          editable.focus();
          document.execCommand(btn.getAttribute('data-cmd'), false, undefined);
          sync();
        });
      });

      var headingSelect = toolbar.querySelector('[data-heading-select]');
      if (headingSelect) {
        headingSelect.addEventListener('change', function () {
          editable.focus();
          if (headingSelect.value) {
            document.execCommand('formatBlock', false, headingSelect.value);
          }
          headingSelect.value = '';
          sync();
        });
      }

      var ownForm = field.closest('form');
      if (ownForm) {
        ownForm.addEventListener('submit', function () {
          if (!editable.classList.contains('hidden')) {
            textarea.value = editable.innerHTML;
          }
        });
      }
    });
  })();
</script>

<script>
  // "Vorlage einfügen"-Picker (siehe renderClosingTextTemplatePickerButton()/
  // renderClosingTextTemplatePickerModal() oben in dieser Datei): lädt
  // einmalig /textvorlagen/liste (siehe ClosingTextTemplateController::
  // list()), zeigt Ordner/Vorlagen als Baum im Modal und fügt die gewählte
  // Vorlage beim Klick in das per data-template-picker-trigger="<name>"
  // adressierte Rich-Text-Feld (data-rt-name="<name>", siehe
  // renderDocumentClosingTextField()) ein. Identisches Muster wird (bewusst
  // dupliziert statt per Include geteilt, siehe Konvention im restlichen
  // Formular) in offers/form.php und order-confirmations/form.php verwendet.
  (function () {
    var modal = document.querySelector('[data-template-picker-modal]');
    if (!modal) {
      return;
    }

    var body = modal.querySelector('[data-template-picker-body]');
    var closeBtn = modal.querySelector('[data-template-picker-close]');
    var cachedData = null;
    var activeTargetField = null;

    function escapeHtml(value) {
      var div = document.createElement('div');
      div.textContent = value == null ? '' : value;
      return div.innerHTML;
    }

    function closeModal() {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      activeTargetField = null;
    }

    function renderTree(data) {
      var folders = data.folders || [];
      var templates = data.templates || [];
      var templatesByFolder = {};
      templates.forEach(function (tpl) {
        var key = tpl.folderId || '';
        (templatesByFolder[key] = templatesByFolder[key] || []).push(tpl);
      });
      var childrenByParent = {};
      folders.forEach(function (folder) {
        var key = folder.parentId || '';
        (childrenByParent[key] = childrenByParent[key] || []).push(folder);
      });

      function renderNode(parentKey, depth) {
        var out = '';
        (templatesByFolder[parentKey] || []).forEach(function (tpl) {
          out += '<button type="button" data-template-picker-pick="' + escapeHtml(tpl.id) + '" '
            + 'style="padding-left:' + (depth * 0.9 + 0.5) + 'rem" '
            + 'class="block w-full rounded px-2 py-1 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">'
            + '📄 ' + escapeHtml(tpl.name) + '</button>';
        });
        (childrenByParent[parentKey] || []).forEach(function (folder) {
          out += '<div style="padding-left:' + (depth * 0.9) + 'rem" '
            + 'class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">'
            + '📁 ' + escapeHtml(folder.name) + '</div>';
          out += renderNode(folder.id, depth + 1);
        });

        return out;
      }

      var html = renderNode('', 0);
      if (html === '') {
        html = '<p class="text-slate-400 dark:text-slate-500">Es sind noch keine Textvorlagen gespeichert. Unter Einstellungen &rarr; Textvorlagen lassen sich welche anlegen.</p>';
      }

      body.innerHTML = html;

      body.querySelectorAll('[data-template-picker-pick]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-template-picker-pick');
          var tpl = templates.filter(function (candidate) { return candidate.id === id; })[0];
          if (tpl) {
            insertIntoTarget(tpl.content || '');
          }
          closeModal();
        });
      });
    }

    function openModal(targetFieldName) {
      activeTargetField = targetFieldName;
      modal.classList.remove('hidden');
      modal.classList.add('flex');

      if (cachedData) {
        renderTree(cachedData);

        return;
      }

      body.innerHTML = '<p class="text-slate-400 dark:text-slate-500">Lädt…</p>';
      fetch('/textvorlagen/liste')
        .then(function (response) { return response.json(); })
        .then(function (data) {
          cachedData = data;
          renderTree(data);
        })
        .catch(function () {
          body.innerHTML = '<p class="text-red-500 dark:text-red-400">Textvorlagen konnten nicht geladen werden.</p>';
        });
    }

    function insertIntoTarget(content) {
      if (!activeTargetField) {
        return;
      }

      var field = document.querySelector('[data-rt-field][data-rt-name="' + activeTargetField + '"]');
      if (!field) {
        return;
      }

      var editable = field.querySelector('[data-rt-editable]');
      var textarea = field.querySelector('[data-rt-source]');
      if (!editable || !textarea) {
        return;
      }

      if (!editable.classList.contains('hidden')) {
        editable.focus();
        var inserted = false;
        try {
          inserted = document.execCommand('insertHTML', false, content);
        } catch (e) {
          inserted = false;
        }
        if (!inserted) {
          editable.innerHTML += content;
        }
        textarea.value = editable.innerHTML;
      } else {
        textarea.value = (textarea.value ? textarea.value + '\n' : '') + content;
      }
    }

    document.querySelectorAll('[data-template-picker-trigger]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openModal(btn.getAttribute('data-template-picker-trigger'));
      });
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeModal();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
        closeModal();
      }
    });
  })();
</script>

<?php endif; ?>
