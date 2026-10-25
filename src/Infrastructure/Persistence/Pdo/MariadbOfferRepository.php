<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferFreeTextItem;
use App\Domain\Offer\OfferItem;
use App\Domain\Offer\OfferLaborItem;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\Offer\OfferSaleItem;
use App\Domain\Offer\OfferSectionText;
use App\Domain\Offer\OfferStatus;
use DateTimeImmutable;
use PDO;

final class MariadbOfferRepository implements OfferRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Offer $offer): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO offers (
                id, offer_number, customer_id, event_location, venue_id, start_date, end_date, valid_until,
                status, note, closing_text,
                accessory_flat_rate_enabled, accessory_flat_rate_percent, discount_type, discount_value,
                converted_rental_id, created_at, updated_at
             ) VALUES (
                :id, :offer_number, :customer_id, :event_location, :venue_id, :start_date, :end_date, :valid_until,
                :status, :note, :closing_text,
                :accessory_flat_rate_enabled, :accessory_flat_rate_percent, :discount_type, :discount_value,
                :converted_rental_id, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                customer_id = :customer_id2, event_location = :event_location2, venue_id = :venue_id2,
                start_date = :start_date2, end_date = :end_date2, valid_until = :valid_until2,
                status = :status2, note = :note2, closing_text = :closing_text2,
                accessory_flat_rate_enabled = :accessory_flat_rate_enabled2,
                accessory_flat_rate_percent = :accessory_flat_rate_percent2,
                discount_type = :discount_type2, discount_value = :discount_value2,
                converted_rental_id = :converted_rental_id2,
                updated_at = :updated_at2'
        );

        $validUntil = $offer->validUntil?->format('Y-m-d');
        $accessoryFlatRateEnabled = $offer->accessoryFlatRateEnabled ? 1 : 0;
        $stmt->execute([
            'id' => $offer->id,
            'offer_number' => $offer->offerNumber,
            'customer_id' => $offer->customerId,
            'event_location' => $offer->eventLocation,
            'venue_id' => $offer->venueId,
            'start_date' => $offer->startDate->format('Y-m-d'),
            'end_date' => $offer->endDate->format('Y-m-d'),
            'valid_until' => $validUntil,
            'status' => $offer->status->value,
            'note' => $offer->note,
            'closing_text' => $offer->closingText,
            'accessory_flat_rate_enabled' => $accessoryFlatRateEnabled,
            'accessory_flat_rate_percent' => $offer->accessoryFlatRatePercent,
            'discount_type' => $offer->discountType,
            'discount_value' => $offer->discountValue,
            'converted_rental_id' => $offer->convertedRentalId,
            'created_at' => $offer->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $offer->updatedAt->format('Y-m-d H:i:s'),
            'customer_id2' => $offer->customerId,
            'event_location2' => $offer->eventLocation,
            'venue_id2' => $offer->venueId,
            'start_date2' => $offer->startDate->format('Y-m-d'),
            'end_date2' => $offer->endDate->format('Y-m-d'),
            'valid_until2' => $validUntil,
            'status2' => $offer->status->value,
            'note2' => $offer->note,
            'closing_text2' => $offer->closingText,
            'accessory_flat_rate_enabled2' => $accessoryFlatRateEnabled,
            'accessory_flat_rate_percent2' => $offer->accessoryFlatRatePercent,
            'discount_type2' => $offer->discountType,
            'discount_value2' => $offer->discountValue,
            'converted_rental_id2' => $offer->convertedRentalId,
            'updated_at2' => $offer->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Offer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM offers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function search(?string $searchTerm, ?OfferStatus $status, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $status);

        $sql = 'SELECT o.* FROM offers o LEFT JOIN customers c ON c.id = o.customer_id'
            . $where . ' ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countSearch(?string $searchTerm, ?OfferStatus $status): int
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $status);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM offers o LEFT JOIN customers c ON c.id = o.customer_id' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $searchTerm, ?OfferStatus $status): array
    {
        $sql = ' WHERE 1 = 1';
        $params = [];

        if ($searchTerm !== null && trim($searchTerm) !== '') {
            $sql .= ' AND (o.event_location LIKE :search
                OR o.offer_number LIKE :search2b
                OR c.first_name LIKE :search2 OR c.last_name LIKE :search3 OR c.company_name LIKE :search4)';
            $needle = '%' . $searchTerm . '%';
            $params['search'] = $needle;
            $params['search2b'] = $needle;
            $params['search2'] = $needle;
            $params['search3'] = $needle;
            $params['search4'] = $needle;
        }

        if ($status !== null) {
            $sql .= ' AND o.status = :status';
            $params['status'] = $status->value;
        }

        return [$sql, $params];
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM offers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findItemsByOffer(string $offerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT oi.* FROM offer_items oi
             INNER JOIN articles a ON a.id = oi.article_id
             WHERE oi.offer_id = :offer_id
             ORDER BY oi.sort_order ASC'
        );
        $stmt->execute(['offer_id' => $offerId]);

        return array_map($this->hydrateItem(...), $stmt->fetchAll());
    }

    public function replaceItems(string $offerId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM offer_items WHERE offer_id = :offer_id');
            $delete->execute(['offer_id' => $offerId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO offer_items (id, offer_id, article_id, quantity, unit_price_per_day, line_note, sort_order)
                 VALUES (:id, :offer_id, :article_id, :quantity, :unit_price_per_day, :line_note, :sort_order)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'offer_id' => $item->offerId,
                    'article_id' => $item->articleId,
                    'quantity' => $item->quantity,
                    'unit_price_per_day' => $item->unitPricePerDay,
                    'line_note' => $item->lineNote,
                    'sort_order' => $item->sortOrder,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findFreeTextItemsByOffer(string $offerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM offer_free_text_items WHERE offer_id = :offer_id ORDER BY sort_order ASC'
        );
        $stmt->execute(['offer_id' => $offerId]);

        return array_map($this->hydrateFreeTextItem(...), $stmt->fetchAll());
    }

    public function replaceFreeTextItems(string $offerId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM offer_free_text_items WHERE offer_id = :offer_id');
            $delete->execute(['offer_id' => $offerId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO offer_free_text_items (id, offer_id, description, quantity, unit_price, sort_order)
                 VALUES (:id, :offer_id, :description, :quantity, :unit_price, :sort_order)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'offer_id' => $item->offerId,
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

    public function findSectionTextsByOffer(string $offerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM offer_section_texts WHERE offer_id = :offer_id ORDER BY sort_order ASC'
        );
        $stmt->execute(['offer_id' => $offerId]);

        return array_map($this->hydrateSectionText(...), $stmt->fetchAll());
    }

    public function replaceSectionTexts(string $offerId, array $sectionTexts): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM offer_section_texts WHERE offer_id = :offer_id');
            $delete->execute(['offer_id' => $offerId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO offer_section_texts (id, offer_id, heading, sort_order)
                 VALUES (:id, :offer_id, :heading, :sort_order)'
            );

            foreach ($sectionTexts as $sectionText) {
                $insert->execute([
                    'id' => $sectionText->id,
                    'offer_id' => $sectionText->offerId,
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

    public function findLaborItemsByOffer(string $offerId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM offer_labor_items WHERE offer_id = :offer_id');
        $stmt->execute(['offer_id' => $offerId]);

        return array_map($this->hydrateLaborItem(...), $stmt->fetchAll());
    }

    public function replaceLaborItems(string $offerId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM offer_labor_items WHERE offer_id = :offer_id');
            $delete->execute(['offer_id' => $offerId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO offer_labor_items (id, offer_id, labor_time_article_id, hours, unit_rate_per_hour)
                 VALUES (:id, :offer_id, :labor_time_article_id, :hours, :unit_rate_per_hour)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'offer_id' => $item->offerId,
                    'labor_time_article_id' => $item->laborTimeArticleId,
                    'hours' => $item->hours,
                    'unit_rate_per_hour' => $item->unitRatePerHour,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findSaleItemsByOffer(string $offerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT osi.* FROM offer_sale_items osi
             INNER JOIN articles a ON a.id = osi.article_id
             WHERE osi.offer_id = :offer_id
             ORDER BY a.name ASC'
        );
        $stmt->execute(['offer_id' => $offerId]);

        return array_map($this->hydrateSaleItem(...), $stmt->fetchAll());
    }

    public function replaceSaleItems(string $offerId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM offer_sale_items WHERE offer_id = :offer_id');
            $delete->execute(['offer_id' => $offerId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO offer_sale_items (id, offer_id, article_id, inventory_number_id, quantity, unit_price, line_note, fulfilled_sale_id)
                 VALUES (:id, :offer_id, :article_id, :inventory_number_id, :quantity, :unit_price, :line_note, :fulfilled_sale_id)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'offer_id' => $item->offerId,
                    'article_id' => $item->articleId,
                    'inventory_number_id' => $item->inventoryNumberId,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unitPrice,
                    'line_note' => $item->lineNote,
                    'fulfilled_sale_id' => $item->fulfilledSaleId,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function markSaleItemFulfilled(string $saleItemId, string $saleId): void
    {
        $stmt = $this->pdo->prepare('UPDATE offer_sale_items SET fulfilled_sale_id = :sale_id WHERE id = :id');
        $stmt->execute(['sale_id' => $saleId, 'id' => $saleItemId]);
    }

    public function findOpenForRevenueForecast(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM offers WHERE status != 'declined' ORDER BY start_date ASC"
        );
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    private function hydrate(array $row): Offer
    {
        return new Offer(
            id: $row['id'],
            offerNumber: $row['offer_number'],
            customerId: $row['customer_id'],
            eventLocation: $row['event_location'],
            venueId: $row['venue_id'],
            startDate: new DateTimeImmutable($row['start_date']),
            endDate: new DateTimeImmutable($row['end_date']),
            validUntil: $row['valid_until'] !== null ? new DateTimeImmutable($row['valid_until']) : null,
            status: OfferStatus::from($row['status']),
            note: $row['note'],
            closingText: $row['closing_text'] ?? null,
            convertedRentalId: $row['converted_rental_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            accessoryFlatRateEnabled: (bool) $row['accessory_flat_rate_enabled'],
            accessoryFlatRatePercent: $row['accessory_flat_rate_percent'],
            discountType: $row['discount_type'],
            discountValue: $row['discount_value'],
        );
    }

    private function hydrateItem(array $row): OfferItem
    {
        return new OfferItem(
            id: $row['id'],
            offerId: $row['offer_id'],
            articleId: $row['article_id'],
            quantity: (int) $row['quantity'],
            unitPricePerDay: $row['unit_price_per_day'],
            lineNote: $row['line_note'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function hydrateFreeTextItem(array $row): OfferFreeTextItem
    {
        return new OfferFreeTextItem(
            id: $row['id'],
            offerId: $row['offer_id'],
            description: $row['description'],
            quantity: $row['quantity'],
            unitPrice: $row['unit_price'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function hydrateSectionText(array $row): OfferSectionText
    {
        return new OfferSectionText(
            id: $row['id'],
            offerId: $row['offer_id'],
            heading: $row['heading'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function hydrateLaborItem(array $row): OfferLaborItem
    {
        return new OfferLaborItem(
            id: $row['id'],
            offerId: $row['offer_id'],
            laborTimeArticleId: $row['labor_time_article_id'],
            hours: $row['hours'],
            unitRatePerHour: $row['unit_rate_per_hour'],
        );
    }

    private function hydrateSaleItem(array $row): OfferSaleItem
    {
        return new OfferSaleItem(
            id: $row['id'],
            offerId: $row['offer_id'],
            articleId: $row['article_id'],
            inventoryNumberId: $row['inventory_number_id'],
            quantity: (int) $row['quantity'],
            unitPrice: $row['unit_price'],
            lineNote: $row['line_note'],
            fulfilledSaleId: $row['fulfilled_sale_id'],
        );
    }
}
