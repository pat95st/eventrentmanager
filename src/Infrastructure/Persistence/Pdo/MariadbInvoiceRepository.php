<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceFreeTextItem;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\InvoiceLaborItem;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceSectionText;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\LaborTime\LaborTimeBillingUnit;
use DateTimeImmutable;
use PDO;

final class MariadbInvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Invoice $invoice): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO invoices (
                id, customer_id, event_location, venue_id, start_date, end_date,
                invoice_number, invoice_date, due_date, buyer_reference,
                status, is_small_business, vat_rate_percent,
                accessory_flat_rate_enabled, accessory_flat_rate_percent, discount_type, discount_value,
                flat_rate_enabled, flat_rate_amount,
                shipping_amount,
                paid_at, note, closing_text,
                source_offer_id, source_rental_id, contact_id, bank_account_id, payment_method_id,
                logistics_method, logistics_date1, logistics_date2,
                created_at, updated_at
             ) VALUES (
                :id, :customer_id, :event_location, :venue_id, :start_date, :end_date,
                :invoice_number, :invoice_date, :due_date, :buyer_reference,
                :status, :is_small_business, :vat_rate_percent,
                :accessory_flat_rate_enabled, :accessory_flat_rate_percent, :discount_type, :discount_value,
                :flat_rate_enabled, :flat_rate_amount,
                :shipping_amount,
                :paid_at, :note, :closing_text,
                :source_offer_id, :source_rental_id, :contact_id, :bank_account_id, :payment_method_id,
                :logistics_method, :logistics_date1, :logistics_date2,
                :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                customer_id = :customer_id2, event_location = :event_location2, venue_id = :venue_id2,
                start_date = :start_date2, end_date = :end_date2,
                invoice_number = :invoice_number2, invoice_date = :invoice_date2, due_date = :due_date2,
                buyer_reference = :buyer_reference2, status = :status2,
                is_small_business = :is_small_business2, vat_rate_percent = :vat_rate_percent2,
                accessory_flat_rate_enabled = :accessory_flat_rate_enabled2,
                accessory_flat_rate_percent = :accessory_flat_rate_percent2,
                discount_type = :discount_type2, discount_value = :discount_value2,
                flat_rate_enabled = :flat_rate_enabled2, flat_rate_amount = :flat_rate_amount2,
                shipping_amount = :shipping_amount2,
                paid_at = :paid_at2, note = :note2, closing_text = :closing_text2,
                source_offer_id = :source_offer_id2, source_rental_id = :source_rental_id2, contact_id = :contact_id2, bank_account_id = :bank_account_id2, payment_method_id = :payment_method_id2,
                logistics_method = :logistics_method2, logistics_date1 = :logistics_date12, logistics_date2 = :logistics_date22,
                updated_at = :updated_at2'
        );

        $invoiceDate = $invoice->invoiceDate?->format('Y-m-d');
        $dueDate = $invoice->dueDate?->format('Y-m-d');
        $paidAt = $invoice->paidAt?->format('Y-m-d H:i:s');
        $isSmallBusiness = $invoice->isSmallBusiness === null ? null : ($invoice->isSmallBusiness ? 1 : 0);
        $accessoryFlatRateEnabled = $invoice->accessoryFlatRateEnabled ? 1 : 0;
        $flatRateEnabled = $invoice->flatRateEnabled ? 1 : 0;
        $logisticsDate1 = $invoice->logisticsDate1?->format('Y-m-d H:i:s');
        $logisticsDate2 = $invoice->logisticsDate2?->format('Y-m-d H:i:s');

        $stmt->execute([
            'id' => $invoice->id,
            'customer_id' => $invoice->customerId,
            'event_location' => $invoice->eventLocation,
            'venue_id' => $invoice->venueId,
            'start_date' => $invoice->startDate?->format('Y-m-d'),
            'end_date' => $invoice->endDate?->format('Y-m-d'),
            'invoice_number' => $invoice->invoiceNumber,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'buyer_reference' => $invoice->buyerReference,
            'status' => $invoice->status->value,
            'is_small_business' => $isSmallBusiness,
            'vat_rate_percent' => $invoice->vatRatePercent,
            'accessory_flat_rate_enabled' => $accessoryFlatRateEnabled,
            'accessory_flat_rate_percent' => $invoice->accessoryFlatRatePercent,
            'discount_type' => $invoice->discountType,
            'discount_value' => $invoice->discountValue,
            'flat_rate_enabled' => $flatRateEnabled,
            'flat_rate_amount' => $invoice->flatRateAmount,
            'shipping_amount' => $invoice->shippingAmount,
            'paid_at' => $paidAt,
            'note' => $invoice->note,
            'closing_text' => $invoice->closingText,
            'source_offer_id' => $invoice->sourceOfferId,
            'source_rental_id' => $invoice->sourceRentalId,
            'contact_id' => $invoice->contactId,
            'bank_account_id' => $invoice->bankAccountId,
            'payment_method_id' => $invoice->paymentMethodId,
            'logistics_method' => $invoice->logisticsMethod,
            'logistics_date1' => $logisticsDate1,
            'logistics_date2' => $logisticsDate2,
            'created_at' => $invoice->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $invoice->updatedAt->format('Y-m-d H:i:s'),
            'customer_id2' => $invoice->customerId,
            'event_location2' => $invoice->eventLocation,
            'venue_id2' => $invoice->venueId,
            'start_date2' => $invoice->startDate?->format('Y-m-d'),
            'end_date2' => $invoice->endDate?->format('Y-m-d'),
            'invoice_number2' => $invoice->invoiceNumber,
            'invoice_date2' => $invoiceDate,
            'due_date2' => $dueDate,
            'buyer_reference2' => $invoice->buyerReference,
            'status2' => $invoice->status->value,
            'is_small_business2' => $isSmallBusiness,
            'vat_rate_percent2' => $invoice->vatRatePercent,
            'accessory_flat_rate_enabled2' => $accessoryFlatRateEnabled,
            'accessory_flat_rate_percent2' => $invoice->accessoryFlatRatePercent,
            'discount_type2' => $invoice->discountType,
            'discount_value2' => $invoice->discountValue,
            'flat_rate_enabled2' => $flatRateEnabled,
            'flat_rate_amount2' => $invoice->flatRateAmount,
            'shipping_amount2' => $invoice->shippingAmount,
            'paid_at2' => $paidAt,
            'note2' => $invoice->note,
            'closing_text2' => $invoice->closingText,
            'source_offer_id2' => $invoice->sourceOfferId,
            'source_rental_id2' => $invoice->sourceRentalId,
            'contact_id2' => $invoice->contactId,
            'bank_account_id2' => $invoice->bankAccountId,
            'payment_method_id2' => $invoice->paymentMethodId,
            'logistics_method2' => $invoice->logisticsMethod,
            'logistics_date12' => $logisticsDate1,
            'logistics_date22' => $logisticsDate2,
            'updated_at2' => $invoice->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Invoice
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE invoice_number = :invoice_number');
        $stmt->execute(['invoice_number' => $invoiceNumber]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findSentUnpaid(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE status = :status ORDER BY created_at ASC');
        $stmt->execute(['status' => InvoiceStatus::Sent->value]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function search(?string $searchTerm, ?InvoiceStatus $status, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $status);

        $sql = 'SELECT i.* FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id'
            . $where . ' ORDER BY i.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countSearch(?string $searchTerm, ?InvoiceStatus $status): int
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $status);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $searchTerm, ?InvoiceStatus $status): array
    {
        $sql = ' WHERE 1 = 1';
        $params = [];

        if ($searchTerm !== null && trim($searchTerm) !== '') {
            $sql .= ' AND (i.event_location LIKE :search OR i.invoice_number LIKE :search5
                OR c.first_name LIKE :search2 OR c.last_name LIKE :search3 OR c.company_name LIKE :search4)';
            $needle = '%' . $searchTerm . '%';
            $params['search'] = $needle;
            $params['search2'] = $needle;
            $params['search3'] = $needle;
            $params['search4'] = $needle;
            $params['search5'] = $needle;
        }

        if ($status !== null) {
            $sql .= ' AND i.status = :status';
            $params['status'] = $status->value;
        }

        return [$sql, $params];
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM invoices WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findItemsByInvoice(string $invoiceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ii.* FROM invoice_items ii
             INNER JOIN articles a ON a.id = ii.article_id
             WHERE ii.invoice_id = :invoice_id
             ORDER BY ii.sort_order ASC'
        );
        $stmt->execute(['invoice_id' => $invoiceId]);

        return array_map($this->hydrateItem(...), $stmt->fetchAll());
    }

    public function replaceItems(string $invoiceId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM invoice_items WHERE invoice_id = :invoice_id');
            $delete->execute(['invoice_id' => $invoiceId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO invoice_items (id, invoice_id, article_id, quantity, unit_price_per_day, line_note, sort_order, is_rental_item)
                 VALUES (:id, :invoice_id, :article_id, :quantity, :unit_price_per_day, :line_note, :sort_order, :is_rental_item)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'invoice_id' => $item->invoiceId,
                    'article_id' => $item->articleId,
                    'quantity' => $item->quantity,
                    'unit_price_per_day' => $item->unitPricePerDay,
                    'line_note' => $item->lineNote,
                    'sort_order' => $item->sortOrder,
                    'is_rental_item' => $item->isRentalItem ? 1 : 0,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findFreeTextItemsByInvoice(string $invoiceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM invoice_free_text_items WHERE invoice_id = :invoice_id ORDER BY sort_order ASC'
        );
        $stmt->execute(['invoice_id' => $invoiceId]);

        return array_map($this->hydrateFreeTextItem(...), $stmt->fetchAll());
    }

    public function replaceFreeTextItems(string $invoiceId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM invoice_free_text_items WHERE invoice_id = :invoice_id');
            $delete->execute(['invoice_id' => $invoiceId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO invoice_free_text_items (id, invoice_id, description, quantity, unit_price, sort_order)
                 VALUES (:id, :invoice_id, :description, :quantity, :unit_price, :sort_order)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'invoice_id' => $item->invoiceId,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unitPrice,
                    'sort_order' => $item->sortOrder,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findSectionTextsByInvoice(string $invoiceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM invoice_section_texts WHERE invoice_id = :invoice_id ORDER BY sort_order ASC'
        );
        $stmt->execute(['invoice_id' => $invoiceId]);

        return array_map($this->hydrateSectionText(...), $stmt->fetchAll());
    }

    public function replaceSectionTexts(string $invoiceId, array $sectionTexts): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM invoice_section_texts WHERE invoice_id = :invoice_id');
            $delete->execute(['invoice_id' => $invoiceId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO invoice_section_texts (id, invoice_id, heading, sort_order)
                 VALUES (:id, :invoice_id, :heading, :sort_order)'
            );

            foreach ($sectionTexts as $sectionText) {
                $insert->execute([
                    'id' => $sectionText->id,
                    'invoice_id' => $sectionText->invoiceId,
                    'heading' => $sectionText->heading,
                    'sort_order' => $sectionText->sortOrder,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findLaborItemsByInvoice(string $invoiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoice_labor_items WHERE invoice_id = :invoice_id');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return array_map($this->hydrateLaborItem(...), $stmt->fetchAll());
    }

    public function replaceLaborItems(string $invoiceId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM invoice_labor_items WHERE invoice_id = :invoice_id');
            $delete->execute(['invoice_id' => $invoiceId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO invoice_labor_items (id, invoice_id, labor_time_article_id, user_id, hours, unit_rate_per_hour, billing_unit)
                 VALUES (:id, :invoice_id, :labor_time_article_id, :user_id, :hours, :unit_rate_per_hour, :billing_unit)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'invoice_id' => $item->invoiceId,
                    'labor_time_article_id' => $item->laborTimeArticleId,
                    'user_id' => $item->userId,
                    'hours' => $item->hours,
                    'unit_rate_per_hour' => $item->unitRatePerHour,
                    'billing_unit' => $item->billingUnit->value,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findOverdue(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM invoices WHERE status = 'sent' AND due_date IS NOT NULL AND due_date < CURDATE() ORDER BY due_date ASC"
        );
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findDueSoon(int $leadDays): array
    {
        // Deadline bewusst in PHP berechnet und als Datum gebunden (statt
        // DATE_ADD mit gebundenem Parameter) — identisches Muster wie
        // MariadbRentalRepository::findDueForReminder().
        $today = new DateTimeImmutable('today');
        $deadline = $today->modify("+{$leadDays} days");

        $stmt = $this->pdo->prepare(
            "SELECT * FROM invoices WHERE status = 'sent' AND due_date IS NOT NULL
             AND due_date >= :today AND due_date <= :deadline
             ORDER BY due_date ASC"
        );
        $stmt->execute([
            'today' => $today->format('Y-m-d'),
            'deadline' => $deadline->format('Y-m-d'),
        ]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findPaidBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM invoices WHERE status = 'paid' AND paid_at IS NOT NULL
             AND paid_at >= :from AND paid_at <= :to ORDER BY paid_at ASC"
        );
        $stmt->execute([
            'from' => $from->format('Y-m-d 00:00:00'),
            'to' => $to->format('Y-m-d 23:59:59'),
        ]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findNotCancelledForRevenueForecast(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM invoices WHERE status != 'cancelled' ORDER BY start_date ASC"
        );
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findSourceOfferIds(): array
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT source_offer_id FROM invoices WHERE source_offer_id IS NOT NULL');
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function hydrate(array $row): Invoice
    {
        return new Invoice(
            id: $row['id'],
            customerId: $row['customer_id'],
            eventLocation: $row['event_location'],
            venueId: $row['venue_id'],
            startDate: $row['start_date'] !== null ? new DateTimeImmutable($row['start_date']) : null,
            endDate: $row['end_date'] !== null ? new DateTimeImmutable($row['end_date']) : null,
            invoiceNumber: $row['invoice_number'],
            invoiceDate: $row['invoice_date'] !== null ? new DateTimeImmutable($row['invoice_date']) : null,
            dueDate: $row['due_date'] !== null ? new DateTimeImmutable($row['due_date']) : null,
            buyerReference: $row['buyer_reference'],
            status: InvoiceStatus::from($row['status']),
            isSmallBusiness: $row['is_small_business'] !== null ? (bool) $row['is_small_business'] : null,
            vatRatePercent: $row['vat_rate_percent'],
            paidAt: $row['paid_at'] !== null ? new DateTimeImmutable($row['paid_at']) : null,
            note: $row['note'],
            sourceOfferId: $row['source_offer_id'],
            closingText: $row['closing_text'] ?? null,
            sourceRentalId: $row['source_rental_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            accessoryFlatRateEnabled: (bool) $row['accessory_flat_rate_enabled'],
            accessoryFlatRatePercent: $row['accessory_flat_rate_percent'],
            discountType: $row['discount_type'],
            discountValue: $row['discount_value'],
            shippingAmount: $row['shipping_amount'],
            flatRateEnabled: (bool) ($row['flat_rate_enabled'] ?? false),
            flatRateAmount: $row['flat_rate_amount'] ?? null,
            logisticsMethod: $row['logistics_method'] ?? null,
            logisticsDate1: ($row['logistics_date1'] ?? null) !== null ? new DateTimeImmutable($row['logistics_date1']) : null,
            logisticsDate2: ($row['logistics_date2'] ?? null) !== null ? new DateTimeImmutable($row['logistics_date2']) : null,
            contactId: $row['contact_id'] ?? null,
            bankAccountId: $row['bank_account_id'] ?? null,
            paymentMethodId: $row['payment_method_id'] ?? null,
        );
    }

    private function hydrateItem(array $row): InvoiceItem
    {
        return new InvoiceItem(
            id: $row['id'],
            invoiceId: $row['invoice_id'],
            articleId: $row['article_id'],
            quantity: (int) $row['quantity'],
            unitPricePerDay: $row['unit_price_per_day'],
            lineNote: $row['line_note'],
            sortOrder: (int) $row['sort_order'],
            isRentalItem: (bool) ($row['is_rental_item'] ?? true),
        );
    }

    private function hydrateFreeTextItem(array $row): InvoiceFreeTextItem
    {
        return new InvoiceFreeTextItem(
            id: $row['id'],
            invoiceId: $row['invoice_id'],
            description: $row['description'],
            quantity: $row['quantity'],
            unitPrice: $row['unit_price'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function hydrateSectionText(array $row): InvoiceSectionText
    {
        return new InvoiceSectionText(
            id: $row['id'],
            invoiceId: $row['invoice_id'],
            heading: $row['heading'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function hydrateLaborItem(array $row): InvoiceLaborItem
    {
        return new InvoiceLaborItem(
            id: $row['id'],
            invoiceId: $row['invoice_id'],
            laborTimeArticleId: $row['labor_time_article_id'],
            userId: $row['user_id'],
            hours: $row['hours'],
            unitRatePerHour: $row['unit_rate_per_hour'],
            billingUnit: LaborTimeBillingUnit::tryFrom($row['billing_unit'] ?? '') ?? LaborTimeBillingUnit::Hour,
        );
    }
}
