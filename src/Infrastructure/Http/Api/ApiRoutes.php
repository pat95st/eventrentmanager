<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Domain\Api\ApiScope as S;
use App\Infrastructure\Http\Router;
use RuntimeException;

/**
 * Route table of the REST API /api/v1. Kept as plain data so the router
 * registration (bootstrap.php) and the documentation check
 * (ApiRoutesDocumentedTest: every route must be described in
 * docs/openapi/) share one source of truth.
 *
 * Each entry: [HTTP method, path, required scopes, controller key, controller method].
 */
final class ApiRoutes
{
    public const PREFIX = '/api/v1';

    /** @return list<array{0: string, 1: string, 2: list<S>, 3: string, 4: string}> */
    public static function definitions(): array
    {
        $crud = static function (string $path, string $controller, S $read, S $write, bool $withWrite = true): array {
            $routes = [
                ['GET', $path, [$read], $controller, 'index'],
                ['GET', $path . '/{id}', [$read], $controller, 'show'],
            ];
            if ($withWrite) {
                $routes[] = ['POST', $path, [$write], $controller, 'store'];
                $routes[] = ['PATCH', $path . '/{id}', [$write], $controller, 'update'];
                $routes[] = ['DELETE', $path . '/{id}', [$write], $controller, 'destroy'];
            }

            return $routes;
        };

        return array_merge(
            // Customers
            $crud('/customers', 'customers', S::CustomersRead, S::CustomersWrite),
            // Articles, inventory numbers, availability, sets, categories, manufacturers, labor time articles
            $crud('/articles', 'articles', S::ArticlesRead, S::ArticlesWrite),
            [
                ['POST', '/articles/{id}/activate', [S::ArticlesWrite], 'articles', 'activate'],
                ['POST', '/articles/{id}/deactivate', [S::ArticlesWrite], 'articles', 'deactivate'],
                ['GET', '/articles/{id}/images/{imageId}', [S::ArticlesRead], 'articles', 'image'],
                ['POST', '/articles/{id}/images', [S::ArticlesWrite], 'articles', 'uploadImage'],
                ['DELETE', '/articles/{id}/images/{imageId}', [S::ArticlesWrite], 'articles', 'deleteImage'],
                ['GET', '/articles/{id}/availability', [S::ArticlesRead], 'articles', 'availability'],
                ['GET', '/availability', [S::ArticlesRead], 'articles', 'availabilityBulk'],
                ['GET', '/articles/{id}/inventory-numbers', [S::ArticlesRead], 'articles', 'inventoryNumbers'],
                ['POST', '/articles/{id}/inventory-numbers', [S::ArticlesWrite], 'articles', 'storeInventoryNumber'],
                ['GET', '/inventory-numbers/{id}', [S::ArticlesRead], 'articles', 'showInventoryNumber'],
                ['PATCH', '/inventory-numbers/{id}', [S::ArticlesWrite], 'articles', 'updateInventoryNumber'],
                ['DELETE', '/inventory-numbers/{id}', [S::ArticlesWrite], 'articles', 'destroyInventoryNumber'],
            ],
            $crud('/article-sets', 'article-sets', S::ArticlesRead, S::ArticlesWrite),
            $crud('/categories', 'categories', S::ArticlesRead, S::ArticlesWrite),
            $crud('/manufacturers', 'manufacturers', S::ArticlesRead, S::ArticlesWrite),
            $crud('/labor-time-articles', 'labor-time-articles', S::ArticlesRead, S::ArticlesWrite),
            // Master data
            $crud('/suppliers', 'suppliers', S::MasterDataRead, S::MasterDataWrite),
            $crud('/venues', 'venues', S::MasterDataRead, S::MasterDataWrite),
            $crud('/warehouses', 'warehouses', S::MasterDataRead, S::MasterDataWrite),
            $crud('/vehicles', 'vehicles', S::MasterDataRead, S::MasterDataWrite),
            // Rentals (Veranstaltungen)
            $crud('/rentals', 'rentals', S::RentalsRead, S::RentalsWrite),
            [
                ['PATCH', '/rentals/{id}/logistics', [S::RentalsWrite], 'rentals', 'updateLogistics'],
                ['POST', '/rentals/{id}/items', [S::RentalsWrite], 'rentals', 'addItem'],
                ['PATCH', '/rental-items/{id}', [S::RentalsWrite], 'rentals', 'updateItem'],
                ['DELETE', '/rental-items/{id}', [S::RentalsWrite], 'rentals', 'deleteItem'],
                ['GET', '/rentals/{id}/technicians', [S::RentalsRead], 'rentals', 'technicians'],
                ['GET', '/rentals/{id}/packing-list.pdf', [S::RentalsRead], 'rentals', 'packingListPdf'],
                ['POST', '/rentals/{id}/packing-list/email', [S::RentalsRead, S::EmailSend], 'rentals', 'emailPackingList'],
                ['POST', '/rentals/{id}/invoice', [S::RentalsRead, S::InvoicesWrite], 'rentals', 'createInvoice'],
                ['GET', '/calendar', [S::RentalsRead], 'rentals', 'calendar'],
                ['GET', '/rentals/{id}/minute-programs', [S::MinuteProgramsRead], 'rentals', 'minutePrograms'],
                ['GET', '/minute-programs/{id}', [S::MinuteProgramsRead], 'rentals', 'minuteProgram'],
                ['GET', '/minute-program-templates', [S::MinuteProgramsRead], 'reports', 'minuteProgramTemplates'],
                ['GET', '/minute-program-templates/{id}', [S::MinuteProgramsRead], 'reports', 'minuteProgramTemplate'],
            ],
            // Offers & order confirmations
            $crud('/offers', 'offers', S::SalesRead, S::SalesWrite),
            [
                ['POST', '/offers/{id}/mark-sent', [S::SalesWrite], 'offers', 'markSent'],
                ['POST', '/offers/{id}/accept', [S::SalesWrite], 'offers', 'accept'],
                ['POST', '/offers/{id}/decline', [S::SalesWrite], 'offers', 'decline'],
                ['POST', '/offers/{id}/rental', [S::SalesWrite, S::RentalsWrite], 'offers', 'convertToRental'],
                ['POST', '/offers/{id}/order-confirmation', [S::SalesWrite], 'offers', 'createOrderConfirmation'],
                ['POST', '/offers/{id}/invoice', [S::SalesRead, S::InvoicesWrite], 'offers', 'createInvoice'],
                ['GET', '/offers/{id}/pdf', [S::SalesRead], 'offers', 'pdf'],
                ['POST', '/offers/{id}/email', [S::SalesRead, S::EmailSend], 'offers', 'email'],
            ],
            $crud('/order-confirmations', 'order-confirmations', S::SalesRead, S::SalesWrite),
            [
                ['POST', '/order-confirmations/{id}/mark-sent', [S::SalesWrite], 'order-confirmations', 'markSent'],
                ['POST', '/order-confirmations/{id}/confirm', [S::SalesWrite], 'order-confirmations', 'confirm'],
                ['POST', '/order-confirmations/{id}/cancel', [S::SalesWrite], 'order-confirmations', 'cancel'],
                ['GET', '/order-confirmations/{id}/pdf', [S::SalesRead], 'order-confirmations', 'pdf'],
            ],
            // Invoices, corrections, dunning notices
            $crud('/invoices', 'invoices', S::InvoicesRead, S::InvoicesWrite),
            [
                ['POST', '/invoices/{id}/finalize', [S::InvoicesFinalize], 'invoices', 'finalize'],
                ['POST', '/invoices/{id}/cancel', [S::InvoicesFinalize], 'invoices', 'cancel'],
                ['POST', '/invoices/{id}/mark-paid', [S::InvoicesWrite], 'invoices', 'markPaid'],
                ['GET', '/invoices/{id}/pdf', [S::InvoicesRead], 'invoices', 'pdf'],
                ['GET', '/invoices/{id}/xrechnung', [S::InvoicesRead], 'invoices', 'xrechnung'],
                ['POST', '/invoices/{id}/email', [S::InvoicesRead, S::EmailSend], 'invoices', 'email'],
                ['GET', '/invoices/{id}/dunning-notices', [S::InvoicesRead], 'invoices', 'dunningNotices'],
                ['GET', '/dunning-notices/{id}', [S::InvoicesRead], 'invoices', 'dunningNotice'],
                ['GET', '/dunning-notices/{id}/pdf', [S::InvoicesRead], 'invoices', 'dunningNoticePdf'],
                ['POST', '/dunning-notices/{id}/email', [S::InvoicesRead, S::EmailSend], 'invoices', 'emailDunningNotice'],
                ['GET', '/invoice-corrections', [S::InvoicesRead], 'invoices', 'corrections'],
                ['GET', '/invoice-corrections/{id}', [S::InvoicesRead], 'invoices', 'correction'],
                ['GET', '/invoice-corrections/{id}/pdf', [S::InvoicesRead], 'invoices', 'correctionPdf'],
                ['GET', '/invoice-corrections/{id}/xrechnung', [S::InvoicesRead], 'invoices', 'correctionXRechnung'],
                ['POST', '/invoice-corrections/{id}/email', [S::InvoicesRead, S::EmailSend], 'invoices', 'emailCorrection'],
            ],
            // Finance
            [
                ['GET', '/receipts', [S::FinanceRead], 'finance', 'receipts'],
                ['GET', '/receipts/{id}', [S::FinanceRead], 'finance', 'receipt'],
                ['GET', '/receipts/{id}/file', [S::FinanceRead], 'finance', 'receiptFile'],
                ['POST', '/receipts', [S::FinanceWrite], 'finance', 'storeReceipt'],
                ['PATCH', '/receipts/{id}', [S::FinanceWrite], 'finance', 'updateReceipt'],
                ['POST', '/receipts/{id}', [S::FinanceWrite], 'finance', 'updateReceipt'],
                ['DELETE', '/receipts/{id}', [S::FinanceWrite], 'finance', 'destroyReceipt'],
                ['POST', '/receipts/{id}/mark-paid', [S::FinanceWrite], 'finance', 'markReceiptPaid'],
                ['POST', '/receipts/{id}/mark-open', [S::FinanceWrite], 'finance', 'markReceiptOpen'],
                ['GET', '/bank-accounts', [S::FinanceRead], 'finance', 'bankAccounts'],
                ['GET', '/bank-accounts/{id}/ledger', [S::FinanceRead], 'finance', 'ledger'],
                ['GET', '/purchase-orders', [S::FinanceRead], 'finance', 'purchaseOrders'],
                ['GET', '/purchase-orders/{id}', [S::FinanceRead], 'finance', 'purchaseOrder'],
                ['POST', '/purchase-orders', [S::FinanceWrite], 'finance', 'storePurchaseOrder'],
                ['PATCH', '/purchase-orders/{id}', [S::FinanceWrite], 'finance', 'updatePurchaseOrder'],
                ['DELETE', '/purchase-orders/{id}', [S::FinanceWrite], 'finance', 'destroyPurchaseOrder'],
                ['POST', '/purchase-orders/{id}/mark-ordered', [S::FinanceWrite], 'finance', 'markPurchaseOrderOrdered'],
                ['POST', '/purchase-orders/{id}/cancel', [S::FinanceWrite], 'finance', 'cancelPurchaseOrder'],
                ['POST', '/purchase-order-items/{id}/receive', [S::FinanceWrite], 'finance', 'receivePurchaseOrderItem'],
            ],
            // Inventory & technology
            [
                ['GET', '/cases', [S::InventoryRead], 'inventory', 'cases'],
                ['GET', '/cases/{id}', [S::InventoryRead], 'inventory', 'caseItem'],
                ['GET', '/racks', [S::InventoryRead], 'inventory', 'racks'],
                ['GET', '/racks/{id}', [S::InventoryRead], 'inventory', 'rack'],
                ['GET', '/rmas', [S::InventoryRead], 'inventory', 'rmas'],
                ['GET', '/rmas/{id}', [S::InventoryRead], 'inventory', 'rma'],
                ['GET', '/electrical-inspections', [S::InventoryRead], 'inventory', 'electricalInspections'],
                ['GET', '/electrical-inspections/{id}', [S::InventoryRead], 'inventory', 'electricalInspection'],
                ['GET', '/inventory-numbers/{id}/electrical-inspections', [S::InventoryRead], 'inventory', 'inventoryNumberInspections'],
                ['POST', '/inventory-numbers/{id}/electrical-inspections', [S::InventoryWrite], 'inventory', 'storeInspection'],
                ['GET', '/sales', [S::InventoryRead], 'inventory', 'sales'],
            ],
            // Reports
            [
                ['GET', '/reports/income-expense', [S::ReportsRead], 'reports', 'incomeExpense'],
                ['GET', '/reports/revenue-forecast', [S::ReportsRead], 'reports', 'revenueForecast'],
                ['GET', '/reports/depreciation', [S::ReportsRead], 'reports', 'depreciation'],
            ],
        );
    }

    /**
     * Registers all routes. Static paths are registered before
     * parameterised ones of the same depth (the router matches in order).
     *
     * @param array<string, object> $controllers controller key => instance
     */
    public static function register(Router $router, ApiKernel $kernel, array $controllers): void
    {
        $routes = self::definitions();
        usort($routes, static fn (array $a, array $b) => substr_count($a[1], '{') <=> substr_count($b[1], '{'));
        foreach ($routes as [$method, $path, $scopes, $key, $action]) {
            $controller = $controllers[$key] ?? throw new RuntimeException('API controller "' . $key . '" is not configured.');
            $handler = $kernel->route($scopes, [$controller, $action]);
            match ($method) {
                'GET' => $router->get(self::PREFIX . $path, $handler),
                'POST' => $router->post(self::PREFIX . $path, $handler),
                'PATCH' => $router->patch(self::PREFIX . $path, $handler),
                'PUT' => $router->put(self::PREFIX . $path, $handler),
                'DELETE' => $router->delete(self::PREFIX . $path, $handler),
            };
        }
    }
}
