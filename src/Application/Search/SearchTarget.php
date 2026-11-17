<?php

declare(strict_types=1);

namespace App\Application\Search;

/**
 * The kind of record a search hit points to. Finer-grained than
 * SearchResultType: the "document" group, for example, spans offers, order
 * confirmations, invoices and invoice corrections, each with its own page.
 */
enum SearchTarget: string
{
    case Rental = 'rental';
    case Article = 'article';
    case InventoryNumber = 'inventory_number';
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Technician = 'technician';
    case Venue = 'venue';
    case PurchaseOrder = 'purchase_order';
    case Receipt = 'receipt';
    case Offer = 'offer';
    case OrderConfirmation = 'order_confirmation';
    case Invoice = 'invoice';
    case InvoiceCorrection = 'invoice_correction';

    public function type(): SearchResultType
    {
        return match ($this) {
            self::Rental => SearchResultType::Rental,
            self::Article, self::InventoryNumber => SearchResultType::Article,
            self::Customer => SearchResultType::Customer,
            self::Supplier => SearchResultType::Supplier,
            self::Technician => SearchResultType::Technician,
            self::Venue => SearchResultType::Venue,
            self::PurchaseOrder => SearchResultType::PurchaseOrder,
            self::Receipt => SearchResultType::Receipt,
            self::Offer, self::OrderConfirmation, self::Invoice, self::InvoiceCorrection => SearchResultType::Document,
        };
    }
}
