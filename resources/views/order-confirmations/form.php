<?php
/** @var \App\Domain\OrderConfirmation\OrderConfirmation|null $orderConfirmation */
/** @var \App\Domain\Customer\Customer[] $customers */
/** @var \App\Domain\Venue\Venue[] $venues */
/** @var \App\Domain\Article\Article[] $articles */
/** @var array<string, string> $articleDisplayNames Artikel-ID => "Hersteller Artikelbezeichnung" */
/** @var array<int, string> $tierMultipliers */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var array<int, array{article_id: string, quantity: int|string, unit_price: string, line_note: string, sort_order: int|string}> $existingItems */
/** @var array<int, array{description: string, quantity: string, unit_price: string, sort_order: int|string}> $existingFreeTextItems */
/** @var array<int, array{heading: string, sort_order: int|string}> $existingSectionTexts */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\renderLiveSearchField;
use function App\Infrastructure\Http\t;

/**
 * Rich-Text-Editorfeld für den dokumenteigenen Schlusstext (siehe
 * OrderConfirmation::$closingText) — identisches Muster wie
 * renderDocumentClosingTextField() in resources/views/invoices/form.php
 * und resources/views/offers/form.php, bewusst erneut dupliziert statt
 * per Include geteilt (siehe dortiger Kommentar zum selben Muster).
 */
if (!function_exists('renderDocumentClosingTextField')) {
    function renderDocumentClosingTextField(string $name, string $value, ?string $error): void
    {
        ?>
    <div data-rt-field class="rt-field">
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

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
$locked = $orderConfirmation !== null && !$orderConfirmation->isEditable();
$items = $old['items'] ?? $existingItems;
$freeTextItems = $old['free_text_items'] ?? $existingFreeTextItems;
$sectionTexts = $old['section_texts'] ?? $existingSectionTexts;

$articlesJson = [];
foreach ($articles as $article) {
    $articlesJson[$article->id] = [
        'name' => $articleDisplayNames[$article->id] ?? $article->name,
        'price' => $article->isBillable && $article->dailyRentalPrice !== null ? $article->dailyRentalPrice : null,
    ];
}
?>

<div class="mb-6">
    <a href="<?= $orderConfirmation !== null ? '/auftragsbestaetigungen/' . e($orderConfirmation->id) : '/auftragsbestaetigungen' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('order_confirmations.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $orderConfirmation !== null ? e(t('order_confirmations.edit')) : e(t('order_confirmations.new')) ?></h1>
</div>

<?php if ($locked): ?>
    <div class="mb-6 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
        <?= e(t('order_confirmations.form.locked_notice')) ?>
    </div>
    <a href="/auftragsbestaetigungen/<?= e($editingId) ?>" class="inline-block rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('order_confirmations.show.back')) ?></a>
<?php else: ?>

<form
    method="post"
    action="<?= $editingId !== null ? '/auftragsbestaetigungen/' . e($editingId) : '/auftragsbestaetigungen' ?>"
    class="max-w-5xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
    id="order-confirmation-form"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="customer_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('order_confirmations.form.customer')) ?></label>
            <div class="mt-1 flex gap-2">
                <div class="flex-1">
                    <?php
                    $selectedCustomer = $val('customer_id', $orderConfirmation?->customerId);
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
                        t('order_confirmations.form.customer_placeholder'),
                        $errors['customerId'] ?? null,
                        'select',
                    );
                    ?>
                </div>
                <button type="button" data-customer-quick-create-open class="shrink-0 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('customers.quick_create.title')) ?>
                </button>
            </div>
        </div>

        <div>
            <label for="event_location" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('order_confirmations.form.event_location')) ?></label>
            <div class="mt-1">
                <?php
                renderLiveSearchField(
                    'event_location',
                    '/veranstaltungsorte/suche',
                    null,
                    $val('event_location', $orderConfirmation?->eventLocation),
                    t('order_confirmations.form.event_location'),
                    null,
                    'freetext',
                );
                ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="start_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('order_confirmations.form.start_date')) ?></label>
            <input type="date" id="start_date" name="start_date" required
                   value="<?= e($val('start_date', $orderConfirmation?->startDate->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['startDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['startDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['startDate']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="end_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('order_confirmations.form.end_date')) ?></label>
            <input type="date" id="end_date" name="end_date" required
                   value="<?= e($val('end_date', $orderConfirmation?->endDate->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['endDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['endDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['endDate']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="note" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('order_confirmations.form.note')) ?></label>
        <textarea id="note" name="note" rows="3"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"><?= e($val('note', $orderConfirmation?->note)) ?></textarea>
    </div>

    <?php
        $accessoryChecked = array_key_exists('accessory_flat_rate_enabled', $old)
            ? $old['accessory_flat_rate_enabled'] === '1'
            : ($orderConfirmation?->accessoryFlatRateEnabled ?? true);
        $discountType = $val('discount_type', $orderConfirmation?->discountType);
        $discountValue = $val('discount_value', $orderConfirmation?->discountValue);
    ?>
    <?php
        // Positionen/Freitext-Posten/Zwischentexte: siehe invoices/form.php
        // für die identische Begründung (ein gemeinsamer, sortierbarer
        // Bereich statt dreier getrennter Blöcke — orderedRows()-Konzept).
        // Zubehörpauschale/Kombirabatt erscheinen hier bewusst erst danach
        // (siehe Aufgabenstellung: diese Posten stehen immer zuletzt).
    ?>
    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('order_confirmations.form.items_title')) ?></h2>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="oc-item-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('order_confirmations.form.items_add')) ?>
                </button>
                <button type="button" id="oc-free-text-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('order_confirmations.form.free_text_items_add')) ?>
                </button>
                <button type="button" id="oc-section-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    + <?= e(t('order_confirmations.form.section_texts_add')) ?>
                </button>
            </div>
        </div>
        <?php if (isset($errors['items'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['items']) ?></p><?php endif; ?>
        <?php if (isset($errors['freeTextItems'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['freeTextItems']) ?></p><?php endif; ?>
        <div id="oc-rows" class="space-y-2"></div>
        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('order_confirmations.form.rows_drag_hint')) ?></p>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="flex items-start gap-2">
            <input type="checkbox" id="accessory_flat_rate_enabled" name="accessory_flat_rate_enabled" value="1" <?= $accessoryChecked ? 'checked' : '' ?>
                   class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
            <div>
                <label for="accessory_flat_rate_enabled" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    <?= e(t('order_confirmations.form.accessory_flat_rate', ['percent' => e($pricingSettings->accessoryFlatRatePercent)])) ?>
                </label>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('order_confirmations.form.discount_title')) ?></h2>
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="discount_type" value="" data-discount-option <?= $discountType === '' || $discountType === null ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('order_confirmations.form.discount_none')) ?>
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="discount_type" value="percent" data-discount-option <?= $discountType === 'percent' ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('order_confirmations.form.discount_percent')) ?>
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="discount_type" value="fixed" data-discount-option <?= $discountType === 'fixed' ? 'checked' : '' ?> class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('order_confirmations.form.discount_fixed')) ?>
            </label>
        </div>
        <div id="discount-value-wrapper" class="mt-2 <?= $discountType === '' || $discountType === null ? 'hidden' : '' ?>">
            <label for="discount_value" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('order_confirmations.form.discount_value')) ?></label>
            <input type="text" id="discount_value" name="discount_value" inputmode="decimal"
                   value="<?= e((string) ($discountValue ?? '')) ?>"
                   class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['discountValue']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['discountValue'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['discountValue']) ?></p><?php endif; ?>
        </div>
        <script>
            (function () {
                var wrapper = document.getElementById('discount-value-wrapper');
                document.querySelectorAll('[data-discount-option]').forEach(function (option) {
                    option.addEventListener('change', function () {
                        wrapper.classList.toggle('hidden', option.value === '' && option.checked);
                    });
                });
            })();
        </script>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('order_confirmations.form.closing_text')) ?></label>
        <p class="mb-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('order_confirmations.form.closing_text_hint')) ?></p>
        <?php renderDocumentClosingTextField('closing_text', $val('closing_text', $orderConfirmation?->closingText), $errors['closingText'] ?? null); ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $orderConfirmation !== null ? '/auftragsbestaetigungen/' . e($orderConfirmation->id) : '/auftragsbestaetigungen' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<?php require __DIR__ . '/../customers/_quick_create_modal.php'; ?>

<template id="oc-item-row-template">
    <div class="oc-item-row oc-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="oc-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('order_confirmations.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.form.item_article')) ?></label>
            <div class="mt-1">
                <?php
                renderLiveSearchField(
                    'article_id[]',
                    '/artikel/suche',
                    null,
                    null,
                    t('order_confirmations.form.item_article_placeholder'),
                    null,
                    'select',
                    false,
                    false,
                );
                ?>
            </div>
        </div>
        <div class="w-20">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.form.item_quantity')) ?></label>
            <input type="number" name="quantity[]" min="1" value="1" class="oc-item-quantity mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.form.item_price')) ?></label>
            <input type="text" name="unit_price[]" inputmode="decimal" class="oc-item-price mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="min-w-[140px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.form.item_note')) ?></label>
            <input type="text" name="line_note[]" class="oc-item-note mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <input type="hidden" name="sort_order[]" class="oc-item-sort" data-row-sort value="0">
        <button type="button" class="oc-item-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('order_confirmations.form.item_remove')) ?>
        </button>
    </div>
</template>

<template id="oc-free-text-row-template">
    <div class="oc-free-text-row oc-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="oc-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('order_confirmations.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.form.free_text_description')) ?></label>
            <input type="text" name="free_text_description[]" class="oc-free-text-description mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-20">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.form.item_quantity')) ?></label>
            <input type="text" name="free_text_quantity[]" inputmode="decimal" value="1" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.form.item_price')) ?></label>
            <input type="text" name="free_text_unit_price[]" inputmode="decimal" value="0" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <input type="hidden" name="free_text_sort_order[]" class="oc-free-text-sort" data-row-sort value="1000">
        <button type="button" class="oc-free-text-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('order_confirmations.form.item_remove')) ?>
        </button>
    </div>
</template>

<template id="oc-section-row-template">
    <div class="oc-section-row oc-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <span class="oc-row-handle mt-5 shrink-0 cursor-move select-none text-slate-400 dark:text-slate-500" title="<?= e(t('order_confirmations.form.rows_drag_hint')) ?>">⠿</span>
        <div class="min-w-[200px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.form.section_heading')) ?></label>
            <input type="text" name="section_heading[]" class="oc-section-heading mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <input type="hidden" name="section_sort_order[]" class="oc-section-sort" data-row-sort value="2000">
        <button type="button" class="oc-section-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('order_confirmations.form.item_remove')) ?>
        </button>
    </div>
</template>

<script>
(function () {
    var ARTICLES = <?= json_encode($articlesJson, JSON_THROW_ON_ERROR) ?>;
    var TIER_MULTIPLIERS = <?= json_encode($tierMultipliers, JSON_THROW_ON_ERROR) ?>;
    var EXISTING_ITEMS = <?= json_encode(array_values($items), JSON_THROW_ON_ERROR) ?>;
    var EXISTING_FREE_TEXT_ITEMS = <?= json_encode(array_values($freeTextItems), JSON_THROW_ON_ERROR) ?>;
    var EXISTING_SECTION_TEXTS = <?= json_encode(array_values($sectionTexts), JSON_THROW_ON_ERROR) ?>;

    var ocForm = document.getElementById('order-confirmation-form');
    var container = document.getElementById('oc-rows');
    var template = document.getElementById('oc-item-row-template');
    var addButton = document.getElementById('oc-item-add');
    var startInput = document.getElementById('start_date');
    var endInput = document.getElementById('end_date');
    var nextSortOrder = 0;

    function dayCount() {
        if (!startInput.value || !endInput.value) { return 1; }
        var diff = Math.round((new Date(endInput.value) - new Date(startInput.value)) / 86400000) + 1;
        return isNaN(diff) || diff < 1 ? 1 : Math.min(diff, 7);
    }

    function suggestPrice(articleId) {
        var article = ARTICLES[articleId];
        if (!article || article.price === null) { return null; }
        var factor = TIER_MULTIPLIERS[dayCount()] || TIER_MULTIPLIERS[1] || '1';
        var price = parseFloat(article.price) * parseFloat(factor);
        return isNaN(price) ? null : price.toFixed(2);
    }

    function addRow(data) {
        var fragment = template.content.cloneNode(true);
        var row = fragment.querySelector('.oc-item-row');
        var articleWidget = row.querySelector('[data-live-search]');
        var articleInput = articleWidget.querySelector('[data-live-search-input]');
        var articleHidden = articleWidget.querySelector('[data-live-search-value]');
        var quantityInput = row.querySelector('.oc-item-quantity');
        var priceInput = row.querySelector('.oc-item-price');
        var noteInput = row.querySelector('.oc-item-note');
        var sortInput = row.querySelector('.oc-item-sort');
        var removeButton = row.querySelector('.oc-item-remove');

        data = data || {};
        if (data.article_id) {
            articleHidden.value = data.article_id;
            var knownArticle = ARTICLES[data.article_id];
            articleInput.value = knownArticle ? knownArticle.name : '';
        }
        if (data.quantity) { quantityInput.value = data.quantity; }
        if (data.line_note) { noteInput.value = data.line_note; }
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
        removeButton.addEventListener('click', function () { row.remove(); });

        enableRowDrag(row);
        container.appendChild(row);
        window.LiveSearch.init(row);
        return articleInput;
    }

    addButton.addEventListener('click', function () {
        var articleInput = addRow();
        window.setTimeout(function () { articleInput.focus(); }, 0);
    });

    // --- Freitext-Posten -----------------------------------------------
    // freeTextContainer/sectionContainer sind bewusst DASSELBE Element wie
    // "container" (= #oc-rows) — siehe invoices/form.php für die identische
    // Begründung (gemeinsamer, per Drag-and-Drop sortierbarer Bereich).
    var freeTextContainer = container;
    var sectionContainer = container;
    var freeTextTemplate = document.getElementById('oc-free-text-row-template');
    var freeTextAddButton = document.getElementById('oc-free-text-add');

    function addFreeTextRow(data) {
        var fragment = freeTextTemplate.content.cloneNode(true);
        var row = fragment.querySelector('.oc-free-text-row');
        data = data || {};
        if (data.description) { row.querySelector('.oc-free-text-description').value = data.description; }
        if (data.quantity) { row.querySelector('input[name="free_text_quantity[]"]').value = data.quantity; }
        if (data.unit_price !== undefined) { row.querySelector('input[name="free_text_unit_price[]"]').value = data.unit_price; }
        row.querySelector('.oc-free-text-sort').value = 0;
        row.querySelector('.oc-free-text-remove').addEventListener('click', function () { row.remove(); });
        enableRowDrag(row);
        freeTextContainer.appendChild(row);
    }

    freeTextAddButton.addEventListener('click', function () { addFreeTextRow(); });

    // --- Zwischentexte ---------------------------------------------------
    var sectionTemplate = document.getElementById('oc-section-row-template');
    var sectionAddButton = document.getElementById('oc-section-add');

    function addSectionRow(data) {
        var fragment = sectionTemplate.content.cloneNode(true);
        var row = fragment.querySelector('.oc-section-row');
        data = data || {};
        if (data.heading) { row.querySelector('.oc-section-heading').value = data.heading; }
        row.querySelector('.oc-section-sort').value = 0;
        row.querySelector('.oc-section-remove').addEventListener('click', function () { row.remove(); });
        enableRowDrag(row);
        sectionContainer.appendChild(row);
    }

    sectionAddButton.addEventListener('click', function () { addSectionRow(); });

    // --- Drag-and-Drop-Umsortierung -----------------------------------------
    // Siehe invoices/form.php für die identische Begründung/Funktionsweise.
    var draggingRow = null;

    function enableRowDrag(row) {
        var handle = row.querySelector('.oc-row-handle');
        if (!handle) { return; }
        handle.addEventListener('mousedown', function () { row.draggable = true; });
        row.addEventListener('dragend', function () { row.draggable = false; draggingRow = null; });
    }

    container.addEventListener('dragstart', function (e) {
        var row = e.target.closest('.oc-row');
        if (!row) { return; }
        draggingRow = row;
        e.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragover', function (e) {
        if (!draggingRow) { return; }
        e.preventDefault();
        var row = e.target.closest('.oc-row');
        if (!row || row === draggingRow) { return; }
        var rect = row.getBoundingClientRect();
        var placeBefore = (e.clientY - rect.top) < rect.height / 2;
        container.insertBefore(draggingRow, placeBefore ? row : row.nextSibling);
    });

    // --- Reihenfolge beim Absenden festschreiben ----------------------------
    // Siehe invoices/form.php für die identische Begründung/Funktionsweise.
    if (ocForm) {
        ocForm.addEventListener('submit', function () {
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
})();
</script>

<script>
  // WYSIWYG-Editor für den dokumenteigenen Schlusstext (siehe
  // renderDocumentClosingTextField() oben in dieser Datei). Identisches
  // Muster wie das Skript in resources/views/invoices/form.php (bewusst
  // dupliziert, siehe dortiger Kommentar). Der Submit-Sync hängt sich
  // hier ebenfalls über `closest('form')` ein statt an eine feste
  // `form[action="..."]`-Auswahl, da dieses Formular je nach
  // Neuanlage/Bearbeitung unterschiedliche `action`-URLs hat.
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

<?php endif; ?>
