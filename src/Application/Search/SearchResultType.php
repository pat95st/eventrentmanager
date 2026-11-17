<?php

declare(strict_types=1);

namespace App\Application\Search;

/**
 * Result groups of the global search (Ctrl+K). The value doubles as the key
 * the frontend uses to pick the group icon (public/assets/js/global-search.js)
 * and the group heading (search.type.* in resources/lang/).
 */
enum SearchResultType: string
{
    case Rental = 'rental';
    case Article = 'article';
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Technician = 'technician';
    case Venue = 'venue';
    case PurchaseOrder = 'purchase_order';
    case Receipt = 'receipt';
    case Document = 'document';
}
