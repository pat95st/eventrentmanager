<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Query\QueryBus;
use App\Application\Sale\Query\ListSales;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Cross-cutting "Verkaufsübersicht" — listet ALLE Verkäufe (sowohl aus dem
 * Vermietpool via Inventurnummer als auch aus dem reinen Verkaufsbestand,
 * siehe App\Domain\Sale\Sale::isFromStock()) in einer gemeinsamen,
 * durchsuchbaren Liste. Rein lesend — Verkäufe selbst entstehen über die
 * Inventurnummer-Detailseite (InventoryNumberController::sell()), die
 * Artikel-Detailseite (ArticleController::sellFromStock()) oder beim
 * Einlösen eines Angebots (CreateInvoiceFromOfferHandler).
 */
final class SalesController
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListSales($search, $page, $pageSize));

        $viewData = [
            'sales' => $result->items,
            'search' => $search ?? '',
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/verkaeufe',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('sales/_table', $viewData);

            return;
        }

        echo View::page('sales/index', $viewData, activeNav: 'sales', title: t('sales.title'));
    }
}
