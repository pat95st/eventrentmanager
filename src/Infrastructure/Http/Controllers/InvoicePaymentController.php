<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\OnlinePayment\Command\CancelInvoiceOnlinePayment;
use App\Application\OnlinePayment\Command\ConfirmInvoiceOnlinePayment;
use App\Application\OnlinePayment\Command\StartInvoiceOnlinePayment;
use App\Application\OnlinePayment\Query\GetInvoicePaymentPage;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\OnlinePayment\OnlinePayment;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Domain\OnlinePayment\OnlinePaymentStatus;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Origin;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Public (not logged in!) payment page of an invoice, reachable via the
 * secret link /zahlung/{token} from the invoice mail (migration 163), plus
 * the return/cancel targets of PayPal and SumUp and the SumUp webhook.
 * Slim standalone page without the regular navigation, like
 * CustomerChecklistController.
 */
final class InvoicePaymentController
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
            $page = $this->queries->ask(new GetInvoicePaymentPage($token));
        } catch (NotFoundException) {
            $this->unavailable();

            return;
        }

        $notice = $request->query('hinweis');

        echo View::render('public/invoice-payment', [
            'page' => $page,
            'token' => $token,
            'notice' => is_string($notice) ? $notice : null,
        ]);
    }

    /** POST /zahlung/{token}/{provider}: starts the checkout and redirects to PayPal/SumUp. */
    public function start(Request $request): void
    {
        $token = $request->param('token');
        $provider = OnlinePaymentProvider::tryFrom($request->param('provider'));
        if ($provider === null) {
            $this->unavailable();

            return;
        }

        try {
            $redirectUrl = $this->commands->dispatch(new StartInvoiceOnlinePayment($token, $provider, Origin::current()));
        } catch (NotFoundException) {
            $this->unavailable();

            return;
        } catch (ConflictException | ValidationException $e) {
            Flash::error($e instanceof ValidationException ? implode(' ', $e->errors()) : $e->getMessage());
            Response::redirect($this->pagePath($token));

            return;
        }

        Response::redirect((string) $redirectUrl);
    }

    /** GET /zahlung/{token}/{provider}/zurueck?zahlung=…: customer comes back from the provider. */
    public function returnFromProvider(Request $request): void
    {
        $token = $request->param('token');
        $provider = OnlinePaymentProvider::tryFrom($request->param('provider'));
        $paymentId = (string) $request->query('zahlung', '');
        if ($provider === null || $paymentId === '') {
            Response::redirect($this->pagePath($token));

            return;
        }

        // PayPal appends ?token=<order id>; it has to match the stored order.
        $reference = $provider === OnlinePaymentProvider::PayPal ? $request->query('token') : null;

        try {
            /** @var OnlinePayment $payment */
            $payment = $this->commands->dispatch(ConfirmInvoiceOnlinePayment::byPaymentId($paymentId, $reference !== null ? $provider : null, $reference));
        } catch (NotFoundException) {
            Response::redirect($this->pagePath($token));

            return;
        }

        $notice = match ($payment->status) {
            OnlinePaymentStatus::Completed => 'bezahlt',
            OnlinePaymentStatus::Pending => 'in-bearbeitung',
            OnlinePaymentStatus::Cancelled => 'abgebrochen',
            OnlinePaymentStatus::Failed => 'fehlgeschlagen',
        };

        Response::redirect($this->pagePath($token) . '?hinweis=' . $notice);
    }

    /** GET /zahlung/{token}/abbrechen?zahlung=…: customer cancelled at PayPal. */
    public function cancel(Request $request): void
    {
        $token = $request->param('token');
        $paymentId = (string) $request->query('zahlung', '');
        if ($paymentId !== '') {
            $this->commands->dispatch(new CancelInvoiceOnlinePayment($token, $paymentId));
        }

        Response::redirect($this->pagePath($token) . '?hinweis=abgebrochen');
    }

    /**
     * POST /api/payments/sumup/webhook — SumUp notifies status changes of a
     * checkout ({"event_type": "CHECKOUT_STATUS_CHANGED", "id": "…"}). The
     * body is not authenticated, so it only triggers a status lookup at
     * SumUp; an unknown id is ignored. Always answers 200 so SumUp does not
     * retry endlessly.
     */
    public function sumUpWebhook(Request $request): void
    {
        $raw = (string) file_get_contents('php://input');
        $payload = json_decode($raw, true);
        $checkoutId = is_array($payload) && is_string($payload['id'] ?? null) ? $payload['id'] : null;

        if ($checkoutId !== null && $checkoutId !== '' && strlen($checkoutId) <= 100) {
            try {
                $this->commands->dispatch(ConfirmInvoiceOnlinePayment::byProviderReference(OnlinePaymentProvider::SumUp, $checkoutId));
            } catch (NotFoundException) {
                // Not one of our checkouts (or created by another installation) — ignore.
            }
        }

        Response::json(['ok' => true]);
    }

    private function pagePath(string $token): string
    {
        return '/zahlung/' . rawurlencode($token);
    }

    private function unavailable(): void
    {
        http_response_code(404);
        echo View::render('public/invoice-payment-unavailable');
    }
}
