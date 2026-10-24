<?php
/** @var \App\Application\Article\Query\InventoryNumberContext $context */
/** @var \App\Domain\Supplier\Supplier|null $supplier */
/** @var \App\Application\Rental\Query\RentalHistoryEntry[] $rentalHistory */
/** @var int $rmaCount */
/** @var int $electricalInspectionCount */
/** @var \App\Domain\ElectricalInspection\ElectricalInspection|null $latestElectricalInspection */
/** @var \App\Domain\Printing\LabelPrinter[] $labelPrinters */
/** @var \App\Domain\Sale\Sale|null $sale */
/** @var \App\Domain\Customer\Customer|null $saleCustomer */
/** @var \App\Domain\Customer\Customer[] $customers */
/** @var array<string, string> $saleErrors */

use function App\Infrastructure\Http\e;

$inv = $context->inventoryNumber;
$article = $context->article;
$today = new DateTimeImmutable('today');
$effectivePrice = $inv->effectivePurchasePrice($article->purchasePrice);
?>

<div class="mb-6">
    <a href="/artikel/<?= e($article->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; <?= e($article->name) ?></a>
    <h1 class="mt-1 font-mono text-xl font-semibold text-slate-900 dark:text-slate-100">
        <?= e($inv->number) ?>
        <?php if ($sale !== null): ?>
            <span class="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600">
                Verkauft am <?= e($sale->soldAt->format('d.m.Y')) ?> für <?= e(number_format((float) $sale->salePrice, 2, ',', '.')) ?> €
            </span>
        <?php endif; ?>
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e($article->name) ?> · <?= e($context->manufacturer->name) ?>
    </p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Details</h2>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500">Angeschafft</dt>
                    <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $inv->acquisitionDate !== null ? e($inv->acquisitionDate->format('d.m.Y')) : '–' ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500">Lieferant</dt>
                    <dd class="text-slate-800 dark:text-slate-200">
                        <?php if ($supplier !== null): ?>
                            <a href="/lieferanten/<?= e($supplier->id) ?>/bearbeiten" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($supplier->name) ?></a>
                        <?php else: ?>
                            –
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500">Einkaufspreis</dt>
                    <dd class="font-mono text-slate-800 dark:text-slate-200">
                        <?= $effectivePrice !== null ? e($effectivePrice) . ' €' : '–' ?>
                        <?php if ($inv->purchasePriceOverride !== null): ?>
                            <span class="ml-1 text-xs font-normal text-slate-400 dark:text-slate-500">(abweichend vom Artikel)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500">Lagerort</dt>
                    <dd class="text-slate-800 dark:text-slate-200"><?= $inv->storageLocation !== null ? e($inv->storageLocation) : '–' ?></dd>
                </div>
                <?php if ($article->hasFirmware): ?>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500">Installierte Firmware-Version</dt>
                        <dd class="text-slate-800 dark:text-slate-200">
                            <?php if ($context->installedFirmwareVersion !== null): ?>
                                <span class="font-mono"><?= e($context->installedFirmwareVersion->version) ?></span>
                                <?php if ($context->firmwareOutdated): ?>
                                    <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700">Update verfügbar</span>
                                <?php endif; ?>
                            <?php else: ?>
                                –
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt class="text-xs text-slate-400 dark:text-slate-500">RMA-Vorgänge</dt>
                    <dd class="text-slate-800 dark:text-slate-200">
                        <a href="/inventurnummern/<?= e($inv->id) ?>/rma" class="text-brand-700 hover:underline dark:text-brand-400">
                            <?= $rmaCount > 0 ? $rmaCount . ' Vorgänge ansehen' : 'Keine Vorgänge — RMA anlegen' ?>
                        </a>
                    </dd>
                </div>
                <?php if ($article->requiresElectricalInspection): ?>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500">Elektroprüfung (DGUV V3)</dt>
                        <dd class="text-slate-800 dark:text-slate-200">
                            <a href="/inventurnummern/<?= e($inv->id) ?>/elektropruefungen" class="text-brand-700 hover:underline dark:text-brand-400">
                                <?= $electricalInspectionCount > 0 ? $electricalInspectionCount . ' Prüfung(en) ansehen' : 'Noch keine Prüfung — jetzt erfassen' ?>
                            </a>
                            <?php if ($latestElectricalInspection !== null): ?>
                                <?php $latestStatus = $latestElectricalInspection->status(); ?>
                                <span class="ml-1 inline-flex items-center rounded-full <?= $latestStatus->badgeClasses() ?> px-2 py-0.5 text-xs font-medium">
                                    <?= e($latestStatus->label()) ?>
                                </span>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Veranstaltungshistorie</h2>

            <?php if ($rentalHistory === []): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Diese Inventurnummer wurde noch keiner Veranstaltung zugeordnet.</p>
            <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($rentalHistory as $entry): ?>
                        <?php
                        $rental = $entry->rental;
                        if ($rental->endDate < $today) {
                            $statusLabel = 'Vergangen';
                            $statusClasses = 'bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700';
                        } elseif ($rental->startDate > $today) {
                            $statusLabel = 'Kommend';
                            $statusClasses = 'bg-brand-50 text-brand-700 ring-1 ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800';
                        } else {
                            $statusLabel = 'Aktuell';
                            $statusClasses = 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700';
                        }
                        ?>
                        <a href="/veranstaltungen/<?= e($rental->id) ?>" class="flex flex-wrap items-center justify-between gap-2 py-3 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <div>
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($rental->eventName) ?></p>
                                <p class="text-xs text-slate-400 dark:text-slate-500">
                                    <?= e($entry->customer->displayName()) ?> ·
                                    <?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?>
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $statusClasses ?>"><?= e($statusLabel) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Etikett drucken</h2>
            <?php if ($labelPrinters === []): ?>
                <p class="text-slate-500 dark:text-slate-400">
                    Kein Etikettendrucker konfiguriert. Bitte in den
                    <a href="/einstellungen/etikettendrucker" class="text-brand-700 underline dark:text-brand-400">Admin-Einstellungen</a>
                    einen Drucker hinzufügen.
                </p>
            <?php else: ?>
                <form method="post" action="/inventurnummern/<?= e($inv->id) ?>/etikett-drucken" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <div>
                        <label for="printer_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Drucker</label>
                        <select id="printer_id" name="printer_id" required
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                            <?php foreach ($labelPrinters as $printer): ?>
                                <option value="<?= e($printer->id) ?>"><?= e($printer->name) ?> (<?= e((string) $printer->tapeWidthMm) ?>&nbsp;mm)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                        Etikett drucken
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Verkauf</h2>
            <?php if ($sale !== null): ?>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500">Verkauft am</dt>
                        <dd class="text-slate-800 dark:text-slate-200"><?= e($sale->soldAt->format('d.m.Y')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500">Verkaufspreis</dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-200"><?= e(number_format((float) $sale->salePrice, 2, ',', '.')) ?> €</dd>
                    </div>
                    <?php if ($sale->shippingAmount !== null): ?>
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Versandkosten</dt>
                            <dd class="font-mono text-slate-800 dark:text-slate-200"><?= e(number_format((float) $sale->shippingAmount, 2, ',', '.')) ?> €</dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500">Kunde</dt>
                        <dd class="text-slate-800 dark:text-slate-200">
                            <?= $saleCustomer !== null ? e($saleCustomer->displayName()) : 'Barverkauf (kein Kunde hinterlegt)' ?>
                        </dd>
                    </div>
                </dl>
                <a href="/rechnungen/<?= e($sale->invoiceId) ?>" class="mt-3 inline-block text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Zugehörige Rechnung ansehen</a>
                <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
                    Diese Inventarnummer wurde verkauft und steht daher nicht mehr für die Vermietung zur Verfügung. Sie bleibt dauerhaft erhalten und wird nie wieder neu vergeben.
                </p>
            <?php else: ?>
                <form method="post" action="/inventurnummern/<?= e($inv->id) ?>/verkaufen" class="space-y-3" data-confirm="Dieses Exemplar unwiderruflich aus dem Bestand verkaufen?" data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <div>
                        <label for="sale_customer_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Kunde (optional)</label>
                        <select id="sale_customer_id" name="customer_id"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                            <option value="">— Barverkauf, kein Kunde —</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= e($customer->id) ?>"><?= e($customer->displayName()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($saleErrors['customerId'])): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($saleErrors['customerId']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="sale_price" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Verkaufspreis (€, netto)</label>
                        <input type="text" id="sale_price" name="sale_price" required placeholder="z. B. 120,00"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                        <?php if (isset($saleErrors['salePrice'])): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($saleErrors['salePrice']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="shipping_amount" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Versandkosten (€, optional)</label>
                        <input type="text" id="shipping_amount" name="shipping_amount" placeholder="leer lassen, falls kein Versand"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                        <?php if (isset($saleErrors['shippingAmount'])): ?>
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($saleErrors['shippingAmount']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="sale_note" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notiz (optional)</label>
                        <input type="text" id="sale_note" name="note" maxlength="500"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                    </div>
                    <button type="submit" class="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                        Verkaufen
                    </button>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        Legt automatisch eine Rechnung (Entwurf) mit diesem Verkaufspreis an. Die Inventarnummer steht danach nicht mehr für die Vermietung zur Verfügung und wird nie wieder neu vergeben.
                    </p>
                </form>
            <?php endif; ?>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Hinweis</h2>
            <p class="text-slate-500 dark:text-slate-400">
                Anschaffungsdatum, Lieferant, abweichender Einkaufspreis und Lagerort lassen
                sich auf der <a href="/artikel/<?= e($article->id) ?>?edit_inventory=<?= e($inv->id) ?>" class="underline">Artikel-Detailseite</a> bearbeiten.
            </p>
        </div>
    </div>
</div>
