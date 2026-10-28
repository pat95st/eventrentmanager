<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\OrderConfirmation\OrderConfirmation;
use App\Domain\OrderConfirmation\OrderConfirmationFreeTextItem;
use App\Domain\OrderConfirmation\OrderConfirmationItem;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationSectionText;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;
use DateTimeImmutable;
use PDO;
use Throwable;

final class MariadbOrderConfirmationRepository implements OrderConfirmationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(OrderConfirmation $orderConfirmation): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_confirmations (
                id, order_confirmation_number, customer_id, event_location, venue_id, start_date, end_date,
                status, note, closing_text,
                accessory_flat_rate_enabled, accessory_flat_rate_percent, discount_type, discount_value,
                source_offer_id, source_rental_id, created_at, updated_at
             ) VALUES (
                :id, :order_confirmation_number, :customer_id, :event_location, :venue_id, :start_date, :end_date,
                :status, :note, :closing_text,
                :accessory_flat_rate_enabled, :accessory_flat_rate_percent, :discount_type, :discount_value,
                :source_offer_id, :source_rental_id, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                customer_id = :customer_id2, event_location = :event_location2, venue_id = :venue_id2,
                start_date = :start_date2, end_date = :end_date2,
                status = :status2, note = :note2, closing_text = :closing_text2,
                accessory_flat_rate_enabled = :accessory_flat_rate_enabled2,
                accessory_flat_rate_percent = :accessory_flat_rate_percent2,
                discount_type = :discount_type2, discount_value = :discount_value2,
                source_rental_id = :source_rental_id2,
                updated_at = :updated_at2'
        );

        $accessoryFlatRateEnabled = $orderConfirmation->accessoryFlatRateEnabled ? 1 : 0;
        $stmt->execute([
            'id' => $orderConfirmation->id,
            'order_confirmation_number' => $orderConfirmation->orderConfirmationNumber,
            'customer_id' => $orderConfirmation->customerId,
            'event_location' => $orderConfirmation->eventLocation,
            'venue_id' => $orderConfirmation->venueId,
            'start_date' => $orderConfirmation->startDate?->format('Y-m-d'),
            'end_date' => $orderConfirmation->endDate?->format('Y-m-d'),
            'status' => $orderConfirmation->status->value,
            'note' => $orderConfirmation->note,
            'closing_text' => $orderConfirmation->closingText,
            'accessory_flat_rate_enabled' => $accessoryFlatRateEnabled,
            'accessory_flat_rate_percent' => $orderConfirmation->accessoryFlatRatePercent,
            'discount_type' => $orderConfirmation->discountType,
            'discount_value' => $orderConfirmation->discountValue,
            'source_offer_id' => $orderConfirmation->sourceOfferId,
            'source_rental_id' => $orderConfirmation->sourceRentalId,
            'created_at' => $orderConfirmation->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $orderConfirmation->updatedAt->format('Y-m-d H:i:s'),
            'customer_id2' => $orderConfirmation->customerId,
            'event_location2' => $orderConfirmation->eventLocation,
            'venue_id2' => $orderConfirmation->venueId,
            'start_date2' => $orderConfirmation->startDate?->format('Y-m-d'),
            'end_date2' => $orderConfirmation->endDate?->format('Y-m-d'),
            'status2' => $orderConfirmation->status->value,
            'note2' => $orderConfirmation->note,
            'closing_text2' => $orderConfirmation->closingText,
            'accessory_flat_rate_enabled2' => $accessoryFlatRateEnabled,
            'accessory_flat_rate_percent2' => $orderConfirmation->accessoryFlatRatePercent,
            'discount_type2' => $orderConfirmation->discountType,
            'discount_value2' => $orderConfirmation->discountValue,
            'source_rental_id2' => $orderConfirmation->sourceRentalId,
            'updated_at2' => $orderConfirmation->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?OrderConfirmation
    {
        $stmt = $this->pdo->prepare('SELECT * FROM order_confirmations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function search(?string $searchTerm, ?OrderConfirmationStatus $status, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $status);

        $sql = 'SELECT oc.* FROM order_confirmations oc LEFT JOIN customers c ON c.id = oc.customer_id'
            . $where . ' ORDER BY oc.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countSearch(?string $searchTerm, ?OrderConfirmationStatus $status): int
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $status);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM order_confirmations oc LEFT JOIN customers c ON c.id = oc.customer_id' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $searchTerm, ?OrderConfirmationStatus $status): array
    {
        $sql = ' WHERE 1 = 1';
        $params = [];

        if ($searchTerm !== null && trim($searchTerm) !== '') {
            $sql .= ' AND (oc.event_location LIKE :search OR oc.order_confirmation_number LIKE :search2b
                OR c.first_name LIKE :search2 OR c.last_name LIKE :search3 OR c.company_name LIKE :search4)';
            $needle = '%' . $searchTerm . '%';
            $params['search'] = $needle;
            $params['search2b'] = $needle;
            $params['search2'] = $needle;
            $params['search3'] = $needle;
            $params['search4'] = $needle;
        }

        if ($status !== null) {
            $sql .= ' AND oc.status = :status';
            $params['status'] = $status->value;
        }

        return [$sql, $params];
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM order_confirmations WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findItemsByOrderConfirmation(string $orderConfirmationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_confirmation_items WHERE order_confirmation_id = :id ORDER BY sort_order ASC'
        );
        $stmt->execute(['id' => $orderConfirmationId]);

        return array_map($this->hydrateItem(...), $stmt->fetchAll());
    }

    public function replaceItems(string $orderConfirmationId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM order_confirmation_items WHERE order_confirmation_id = :id');
            $delete->execute(['id' => $orderConfirmationId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO order_confirmation_items (id, order_confirmation_id, article_id, quantity, unit_price_per_day, line_note, sort_order)
                 VALUES (:id, :order_confirmation_id, :article_id, :quantity, :unit_price_per_day, :line_note, :sort_order)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'order_confirmation_id' => $item->orderConfirmationId,
                    'article_id' => $item->articleId,
                    'quantity' => $item->quantity,
                    'unit_price_per_day' => $item->unitPricePerDay,
                    'line_note' => $item->lineNote,
                    'sort_order' => $item->sortOrder,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findFreeTextItemsByOrderConfirmation(string $orderConfirmationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_confirmation_free_text_items WHERE order_confirmation_id = :id ORDER BY sort_order ASC'
        );
        $stmt->execute(['id' => $orderConfirmationId]);

        return array_map($this->hydrateFreeTextItem(...), $stmt->fetchAll());
    }

    public function replaceFreeTextItems(string $orderConfirmationId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM order_confirmation_free_text_items WHERE order_confirmation_id = :id');
            $delete->execute(['id' => $orderConfirmationId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO order_confirmation_free_text_items (id, order_confirmation_id, description, quantity, unit_price, sort_order)
                 VALUES (:id, :order_confirmation_id, :description, :quantity, :unit_price, :sort_order)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'order_confirmation_id' => $item->orderConfirmationId,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unitPrice,
                    'sort_order' => $item->sortOrder,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findSectionTextsByOrderConfirmation(string $orderConfirmationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_confirmation_section_texts WHERE order_confirmation_id = :id ORDER BY sort_order ASC'
        );
        $stmt->execute(['id' => $orderConfirmationId]);

        return array_map($this->hydrateSectionText(...), $stmt->fetchAll());
    }

    public function replaceSectionTexts(string $orderConfirmationId, array $sectionTexts): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM order_confirmation_section_texts WHERE order_confirmation_id = :id');
            $delete->execute(['id' => $orderConfirmationId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO order_confirmation_section_texts (id, order_confirmation_id, heading, sort_order)
                 VALUES (:id, :order_confirmation_id, :heading, :sort_order)'
            );

            foreach ($sectionTexts as $sectionText) {
                $insert->execute([
                    'id' => $sectionText->id,
                    'order_confirmation_id' => $sectionText->orderConfirmationId,
                    'heading' => $sectionText->heading,
                    'sort_order' => $sectionText->sortOrder,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findNotCancelledForRevenueForecast(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM order_confirmations WHERE status != 'cancelled' ORDER BY start_date ASC"
        );
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findSourceOfferIds(): array
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT source_offer_id FROM order_confirmations WHERE source_offer_id IS NOT NULL');
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function hydrate(array $row): OrderConfirmation
    {
        return new OrderConfirmation(
            id: $row['id'],
            orderConfirmationNumber: $row['order_confirmation_number'],
            customerId: $row['customer_id'],
            eventLocation: $row['event_location'],
            venueId: $row['venue_id'],
            startDate: $row['start_date'] !== null ? new DateTimeImmutable($row['start_date']) : null,
            endDate: $row['end_date'] !== null ? new DateTimeImmutable($row['end_date']) : null,
            status: OrderConfirmationStatus::from($row['status']),
            note: $row['note'],
            closingText: $row['closing_text'] ?? null,
            sourceOfferId: $row['source_offer_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            accessoryFlatRateEnabled: (bool) $row['accessory_flat_rate_enabled'],
            accessoryFlatRatePercent: $row['accessory_flat_rate_percent'],
            discountType: $row['discount_type'],
            discountValue: $row['discount_value'],
            sourceRentalId: $row['source_rental_id'] ?? null,
        );
    }

    private function hydrateItem(array $row): OrderConfirmationItem
    {
        return new OrderConfirmationItem(
            id: $row['id'],
            orderConfirmationId: $row['order_confirmation_id'],
            articleId: $row['article_id'],
            quantity: (int) $row['quantity'],
            unitPricePerDay: $row['unit_price_per_day'],
            lineNote: $row['line_note'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function hydrateFreeTextItem(array $row): OrderConfirmationFreeTextItem
    {
        return new OrderConfirmationFreeTextItem(
            id: $row['id'],
            orderConfirmationId: $row['order_confirmation_id'],
            description: $row['description'],
            quantity: $row['quantity'],
            unitPrice: $row['unit_price'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function hydrateSectionText(array $row): OrderConfirmationSectionText
    {
        return new OrderConfirmationSectionText(
            id: $row['id'],
            orderConfirmationId: $row['order_confirmation_id'],
            heading: $row['heading'],
            sortOrder: (int) $row['sort_order'],
        );
    }
}
