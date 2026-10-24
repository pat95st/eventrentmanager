<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Rental\Command\ConfirmRentalItemByCustomer;
use App\Application\Rental\Query\GetRentalCustomerChecklist;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Öffentlich erreichbare (nicht angemeldete!) Seite, über die ein Kunde
 * ohne eigenes Benutzerkonto die für seine Veranstaltung ausgegebenen
 * Artikel abhaken kann (Vollständigkeitsprüfung vor dem Abbau) — siehe
 * App\Domain\Rental\RentalCustomerChecklistToken für das Token-Konzept.
 * Eigene, schlanke Seite ohne die normale, angemeldete Layout-Navigation,
 * analog PublicProductController.
 */
final class CustomerChecklistController
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {
    }

    public function show(Request $request): void
    {
        $token = $request->param('token');

        try {
            $view = $this->queries->ask(new GetRentalCustomerChecklist($token));
        } catch (NotFoundException) {
            // Bewusst dieselbe Seite/HTTP-Status unabhängig davon, ob der Token
            // nie existiert hat oder inzwischen ungültig geworden ist (siehe
            // GetRentalCustomerChecklistHandler) — keine Unterscheidung nach
            // außen, die Rückschlüsse zuließe.
            http_response_code(404);
            echo View::render('public/customer-checklist-unavailable');

            return;
        }

        echo View::render('public/customer-checklist', ['checklist' => $view, 'token' => $token]);
    }

    /** Vom Kunden per fetch() ausgelöstes Abhaken/Zurücknehmen eines einzelnen Postens (siehe public/customer-checklist.php). */
    public function toggle(Request $request): void
    {
        $token = $request->param('token');
        $issuedItemId = $request->param('item');
        $confirmed = $request->input('confirmed', '0') === '1';

        try {
            $this->commands->dispatch(new ConfirmRentalItemByCustomer($token, $issuedItemId, $confirmed));
        } catch (NotFoundException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 404);

            return;
        }

        Response::json(['ok' => true, 'confirmed' => $confirmed]);
    }
}
