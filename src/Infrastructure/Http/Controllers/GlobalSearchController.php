<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Query\QueryBus;
use App\Application\Search\Query\GlobalSearch;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchTarget;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

use function App\Infrastructure\Http\t;

/**
 * JSON endpoint behind the global search dialog (Ctrl+K), see
 * resources/views/partials/global_search.php and
 * public/assets/js/global-search.js.
 */
final class GlobalSearchController
{
    public function __construct(private readonly QueryBus $queries)
    {
    }

    public function search(Request $request): void
    {
        $user = CurrentUser::get();
        if ($user === null) {
            Response::json(['items' => []], 401);

            return;
        }

        /** @var SearchResult[] $results */
        $results = $this->queries->ask(new GlobalSearch((string) $request->query('q'), $user));

        Response::json([
            'items' => array_map(
                fn (SearchResult $result): array => [
                    'type' => $result->target->type()->value,
                    'title' => $result->title,
                    'subtitle' => $result->subtitle,
                    'badge' => $this->badge($result->target),
                    'url' => $this->url($result),
                ],
                $results
            ),
        ]);
    }

    private function url(SearchResult $result): string
    {
        $id = rawurlencode($result->id);

        return match ($result->target) {
            SearchTarget::Rental => '/veranstaltungen/' . $id,
            SearchTarget::Article => '/artikel/' . $id,
            SearchTarget::InventoryNumber => '/inventurnummern/' . $id,
            SearchTarget::Customer => '/kunden/' . $id . '/bearbeiten',
            SearchTarget::Supplier => '/lieferanten/' . $id . '/bearbeiten',
            SearchTarget::Technician => '/techniker/' . $id,
            SearchTarget::Venue => '/veranstaltungsorte/' . $id . '/bearbeiten',
            SearchTarget::PurchaseOrder => '/bestellungen/' . $id,
            SearchTarget::Receipt => '/belege/' . $id,
            SearchTarget::Offer => '/angebote/' . $id,
            SearchTarget::OrderConfirmation => '/auftragsbestaetigungen/' . $id,
            SearchTarget::Invoice => '/rechnungen/' . $id,
            SearchTarget::InvoiceCorrection => '/rechnungskorrekturen/' . $id,
        };
    }

    /** Short label telling apart the kinds of hit that share one group. */
    private function badge(SearchTarget $target): ?string
    {
        return match ($target) {
            SearchTarget::InventoryNumber => t('search.badge.inventory_number'),
            SearchTarget::Offer => t('search.badge.offer'),
            SearchTarget::OrderConfirmation => t('search.badge.order_confirmation'),
            SearchTarget::Invoice => t('search.badge.invoice'),
            SearchTarget::InvoiceCorrection => t('search.badge.invoice_correction'),
            default => null,
        };
    }
}
