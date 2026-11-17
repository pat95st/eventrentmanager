<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Application\Search\GlobalSearchReadModelInterface;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchTarget;
use App\Domain\Purchase\PurchaseOrderStatus;
use App\Domain\Technician\RentalTechnicianStatus;
use PDO;

/**
 * Global search (Ctrl+K) straight on the tables: every word of the search
 * term has to occur in at least one of the searched columns of a record
 * (so "müller berlin" finds the customer Müller in Berlin). Hits whose main
 * column starts with the term are listed first.
 */
final class MariadbGlobalSearchReadModel implements GlobalSearchReadModelInterface
{
    private const CUSTOMER_NAME = "COALESCE(NULLIF(TRIM(c.company_name), ''), TRIM(CONCAT(c.first_name, ' ', c.last_name)))";
    private const USER_NAME = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email)";

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function searchRentals(string $term, int $limit, ?string $onlyForTechnicianUserId = null): array
    {
        if ($onlyForTechnicianUserId !== null) {
            // Technicians only see their own events, and no customer data.
            [$where, $params] = $this->where($term, ['r.event_name', 'r.event_location', 'v.name']);
            $rows = $this->fetch(
                'SELECT r.id, r.event_name, r.start_date, r.end_date, COALESCE(v.name, r.event_location) AS place
                 FROM rentals r
                 INNER JOIN rental_technicians rt
                    ON rt.rental_id = r.id AND rt.user_id = ? AND rt.status <> ?
                 LEFT JOIN venues v ON v.id = r.venue_id
                 WHERE ' . $where . '
                 ORDER BY (r.event_name LIKE ?) DESC, r.start_date DESC',
                [$onlyForTechnicianUserId, RentalTechnicianStatus::Declined->value, ...$params, $this->prefix($term)],
                $limit
            );
        } else {
            [$where, $params] = $this->where($term, [
                'r.event_name', 'r.event_location', 'v.name', 'c.company_name', 'c.first_name', 'c.last_name',
            ]);
            $rows = $this->fetch(
                'SELECT r.id, r.event_name, r.start_date, r.end_date, ' . self::CUSTOMER_NAME . ' AS place
                 FROM rentals r
                 INNER JOIN customers c ON c.id = r.customer_id
                 LEFT JOIN venues v ON v.id = r.venue_id
                 WHERE ' . $where . '
                 ORDER BY (r.event_name LIKE ?) DESC, r.start_date DESC',
                [...$params, $this->prefix($term)],
                $limit
            );
        }

        return array_map(
            fn (array $row): SearchResult => new SearchResult(
                SearchTarget::Rental,
                (string) $row['id'],
                (string) $row['event_name'],
                $this->join([$this->dateRange($row['start_date'], $row['end_date']), $row['place']]),
            ),
            $rows
        );
    }

    public function searchArticles(string $term, int $limit): array
    {
        $results = [];

        // A single item by its inventory number (e.g. scanned or typed from a label).
        $rows = $this->fetch(
            'SELECT i.id, i.number, a.name
             FROM inventory_numbers i
             INNER JOIN articles a ON a.id = i.article_id
             WHERE i.number LIKE ?
             ORDER BY (i.number = ?) DESC, (i.number LIKE ?) DESC, CHAR_LENGTH(i.number), i.number',
            [$this->contains($term), $term, $this->prefix($term)],
            $limit
        );
        foreach ($rows as $row) {
            $results[] = new SearchResult(SearchTarget::InventoryNumber, (string) $row['id'], (string) $row['number'], (string) $row['name']);
        }

        if (count($results) < $limit) {
            [$where, $params] = $this->where($term, ['a.name', 'm.name', 'a.prefix']);
            $rows = $this->fetch(
                'SELECT a.id, a.name, m.name AS manufacturer, cat.name AS category
                 FROM articles a
                 LEFT JOIN manufacturers m ON m.id = a.manufacturer_id
                 LEFT JOIN categories cat ON cat.id = a.category_id
                 WHERE ' . $where . '
                 ORDER BY (a.name LIKE ?) DESC, a.is_active DESC, a.name',
                [...$params, $this->prefix($term)],
                $limit - count($results)
            );
            foreach ($rows as $row) {
                $results[] = new SearchResult(
                    SearchTarget::Article,
                    (string) $row['id'],
                    (string) $row['name'],
                    $this->join([$row['manufacturer'], $row['category']]),
                );
            }
        }

        return $results;
    }

    public function searchCustomers(string $term, int $limit): array
    {
        [$where, $params] = $this->where($term, [
            'c.company_name', 'c.first_name', 'c.last_name', 'c.customer_number', 'c.email', 'c.city',
        ]);
        $rows = $this->fetch(
            'SELECT c.id, ' . self::CUSTOMER_NAME . ' AS name, c.customer_number, c.city
             FROM customers c
             WHERE ' . $where . '
             ORDER BY (c.customer_number = ?) DESC,
                      (c.company_name LIKE ? OR c.last_name LIKE ? OR c.first_name LIKE ?) DESC, name',
            [...$params, $term, $this->prefix($term), $this->prefix($term), $this->prefix($term)],
            $limit
        );

        return array_map(
            fn (array $row): SearchResult => new SearchResult(
                SearchTarget::Customer,
                (string) $row['id'],
                (string) $row['name'],
                $this->join([$row['customer_number'], $row['city']]),
            ),
            $rows
        );
    }

    public function searchSuppliers(string $term, int $limit): array
    {
        [$where, $params] = $this->where($term, ['s.name', 's.city', 's.email', 's.customer_number']);
        $rows = $this->fetch(
            'SELECT s.id, s.name, s.postal_code, s.city
             FROM suppliers s
             WHERE ' . $where . '
             ORDER BY (s.name LIKE ?) DESC, s.name',
            [...$params, $this->prefix($term)],
            $limit
        );

        return array_map(
            fn (array $row): SearchResult => new SearchResult(
                SearchTarget::Supplier,
                (string) $row['id'],
                (string) $row['name'],
                $this->join([trim($row['postal_code'] . ' ' . $row['city'])]),
            ),
            $rows
        );
    }

    public function searchTechnicians(string $term, int $limit): array
    {
        [$where, $params] = $this->where($term, ['u.first_name', 'u.last_name', 'u.email', 'tp.phone']);
        $rows = $this->fetch(
            'SELECT u.id, ' . self::USER_NAME . ' AS name, u.email, tp.phone
             FROM users u
             LEFT JOIN technician_profiles tp ON tp.user_id = u.id
             WHERE FIND_IN_SET(\'techniker\', u.permissions) > 0 AND ' . $where . '
             ORDER BY u.is_active DESC, (u.last_name LIKE ? OR u.first_name LIKE ?) DESC, name',
            [...$params, $this->prefix($term), $this->prefix($term)],
            $limit
        );

        return array_map(
            fn (array $row): SearchResult => new SearchResult(
                SearchTarget::Technician,
                (string) $row['id'],
                (string) $row['name'],
                $this->join([$row['name'] !== $row['email'] ? $row['email'] : null, $row['phone']]),
            ),
            $rows
        );
    }

    public function searchVenues(string $term, int $limit): array
    {
        [$where, $params] = $this->where($term, ['v.name', 'v.city', 'v.street', 'v.postal_code']);
        $rows = $this->fetch(
            'SELECT v.id, v.name, v.street, v.postal_code, v.city
             FROM venues v
             WHERE ' . $where . '
             ORDER BY (v.name LIKE ?) DESC, v.name',
            [...$params, $this->prefix($term)],
            $limit
        );

        return array_map(
            fn (array $row): SearchResult => new SearchResult(
                SearchTarget::Venue,
                (string) $row['id'],
                (string) $row['name'],
                $this->join([$row['street'], trim($row['postal_code'] . ' ' . $row['city'])], ', '),
            ),
            $rows
        );
    }

    public function searchPurchaseOrders(string $term, int $limit): array
    {
        // Purchase orders have no number of their own: they are found by
        // supplier, note or one of the ordered articles.
        [$where, $params] = $this->where($term, [
            's.name',
            'po.note',
            '(SELECT GROUP_CONCAT(a.name SEPARATOR \' \') FROM purchase_order_items poi
               INNER JOIN articles a ON a.id = poi.article_id WHERE poi.purchase_order_id = po.id)',
        ]);
        $rows = $this->fetch(
            'SELECT po.id, s.name AS supplier, po.status, COALESCE(po.ordered_at, po.created_at) AS ordered_on,
                    (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS item_count,
                    (SELECT a.name FROM purchase_order_items poi INNER JOIN articles a ON a.id = poi.article_id
                      WHERE poi.purchase_order_id = po.id ORDER BY a.name LIMIT 1) AS first_article
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE ' . $where . '
             ORDER BY COALESCE(po.ordered_at, po.created_at) DESC',
            $params,
            $limit
        );

        return array_map(
            function (array $row): SearchResult {
                $articles = $row['first_article'];
                if ($articles !== null && (int) $row['item_count'] > 1) {
                    $articles .= ' +' . ((int) $row['item_count'] - 1);
                }

                return new SearchResult(
                    SearchTarget::PurchaseOrder,
                    (string) $row['id'],
                    $this->join([$row['supplier'], $this->date($row['ordered_on'])]) ?? '',
                    $this->join([PurchaseOrderStatus::tryFrom((string) $row['status'])?->label(), $articles]),
                );
            },
            $rows
        );
    }

    public function searchReceipts(string $term, int $limit): array
    {
        [$where, $params] = $this->where($term, ['rc.receipt_number', 's.name', 'rc.note', 'rc.original_filename']);
        $rows = $this->fetch(
            'SELECT rc.id, rc.receipt_number, rc.original_filename, rc.receipt_date, rc.amount, s.name AS supplier
             FROM receipts rc
             INNER JOIN suppliers s ON s.id = rc.supplier_id
             WHERE ' . $where . '
             ORDER BY (rc.receipt_number LIKE ?) DESC, rc.receipt_date DESC',
            [...$params, $this->prefix($term)],
            $limit
        );

        return array_map(
            fn (array $row): SearchResult => new SearchResult(
                SearchTarget::Receipt,
                (string) $row['id'],
                trim((string) $row['receipt_number']) !== '' ? (string) $row['receipt_number'] : (string) $row['original_filename'],
                $this->join([
                    $row['supplier'],
                    $this->date($row['receipt_date']),
                    number_format((float) $row['amount'], 2, ',', '.') . ' €',
                ]),
            ),
            $rows
        );
    }

    public function searchDocuments(string $term, int $limit): array
    {
        // Documents are found by their number only; all four kinds share
        // the limit, the best match (exact, then prefix) wins.
        $sources = [
            [SearchTarget::Invoice, 'invoices', 'invoice_number', 'd.customer_id'],
            [SearchTarget::Offer, 'offers', 'offer_number', 'd.customer_id'],
            [SearchTarget::OrderConfirmation, 'order_confirmations', 'order_confirmation_number', 'd.customer_id'],
            [SearchTarget::InvoiceCorrection, 'invoice_corrections', 'correction_number', '(SELECT i.customer_id FROM invoices i WHERE i.id = d.invoice_id)'],
        ];
        $parts = [];
        $params = [];
        foreach ($sources as [$target, $table, $column, $customerId]) {
            $parts[] = "SELECT '" . $target->value . "' AS target, d.id, d." . $column . ' AS number, ' . self::CUSTOMER_NAME . ' AS customer,
                               (d.' . $column . ' = ?) AS is_exact, (d.' . $column . ' LIKE ?) AS is_prefix, d.created_at
                        FROM ' . $table . ' d
                        LEFT JOIN customers c ON c.id = ' . $customerId . '
                        WHERE d.' . $column . ' LIKE ?';
            array_push($params, $term, $this->prefix($term), $this->contains($term));
        }
        $rows = $this->fetch(
            implode(' UNION ALL ', $parts) . ' ORDER BY is_exact DESC, is_prefix DESC, created_at DESC',
            $params,
            $limit
        );

        return array_map(
            static fn (array $row): SearchResult => new SearchResult(
                SearchTarget::from((string) $row['target']),
                (string) $row['id'],
                (string) $row['number'],
                $row['customer'] !== null && $row['customer'] !== '' ? (string) $row['customer'] : null,
            ),
            $rows
        );
    }

    /**
     * WHERE fragment: every word of $term must occur in at least one column.
     *
     * @param string[] $columns
     * @return array{0: string, 1: string[]}
     */
    private function where(string $term, array $columns): array
    {
        $clauses = [];
        $params = [];
        foreach (preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $clauses[] = '(' . implode(' OR ', array_map(static fn (string $column): string => $column . ' LIKE ?', $columns)) . ')';
            foreach ($columns as $_) {
                $params[] = $this->contains($word);
            }
        }

        return [$clauses === [] ? '1 = 0' : implode(' AND ', $clauses), $params];
    }

    /**
     * @param array<int, string> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetch(string $sql, array $params, int $limit): array
    {
        $stmt = $this->pdo->prepare($sql . ' LIMIT ' . max(1, $limit));
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function contains(string $text): string
    {
        return '%' . $this->escapeLike($text) . '%';
    }

    private function prefix(string $text): string
    {
        return $this->escapeLike($text) . '%';
    }

    private function escapeLike(string $text): string
    {
        return addcslashes($text, '\\%_');
    }

    /** @param array<int, string|null> $parts */
    private function join(array $parts, string $separator = ' · '): ?string
    {
        $parts = array_filter(array_map(static fn (?string $part): string => trim((string) $part), $parts), static fn (string $part): bool => $part !== '');

        return $parts === [] ? null : implode($separator, $parts);
    }

    private function date(?string $value): ?string
    {
        $timestamp = $value !== null ? strtotime($value) : false;

        return $timestamp !== false ? date('d.m.Y', $timestamp) : null;
    }

    private function dateRange(?string $start, ?string $end): ?string
    {
        $from = $this->date($start);
        $to = $this->date($end);

        return $from !== null && $to !== null && $from !== $to ? $from . ' – ' . $to : ($from ?? $to);
    }
}
