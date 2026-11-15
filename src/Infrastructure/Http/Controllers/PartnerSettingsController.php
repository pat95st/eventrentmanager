<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Federation\Command\AcceptPartnerDiscount;
use App\Application\Federation\Command\AcceptPartnerInvitation;
use App\Application\Federation\Command\ConfirmPartner;
use App\Application\Federation\Command\CreatePartnerInvitation;
use App\Application\Federation\Command\DeclinePartner;
use App\Application\Federation\Command\DeletePartner;
use App\Application\Federation\Command\DeletePartnerInvitation;
use App\Application\Federation\Command\EndPartnership;
use App\Application\Federation\Command\ProposePartnerDiscount;
use App\Application\Federation\Command\RejectPartnerDiscount;
use App\Application\Federation\Command\TestPartnerConnection;
use App\Application\Federation\Command\UpdatePartnerSettings;
use App\Application\Federation\Query\GetPartner;
use App\Application\Federation\Query\GetPartnerOverview;
use App\Application\Federation\Query\PreviewPartnerInvitation;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Settings › partner instances (migration 160): invitation links, entering a
 * partner's link, confirming partnerships, discount agreements and the local
 * settings of each partnership.
 */
final class PartnerSettingsController
{
    private const URL = '/einstellungen/partner';
    private const NAV = 'settings-partner';
    private const SESSION_LINK = 'partner_invitation_link';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $this->renderIndex([], []);
    }

    public function createInvitation(Request $request): void
    {
        $result = $this->commands->dispatch(new CreatePartnerInvitation($request->input('note')));
        $this->ensureSession();
        $_SESSION[self::SESSION_LINK] = $result['link'];
        Flash::success('Der Einladungslink wurde erstellt. Er ist ' . \App\Domain\Federation\PartnerInvitation::VALIDITY_DAYS . ' Tage gültig und kann einmal verwendet werden.');
        Response::redirect(self::URL);
    }

    public function deleteInvitation(Request $request): void
    {
        $this->commands->dispatch(new DeletePartnerInvitation($request->param('id')));
        Flash::success('Der Einladungslink wurde widerrufen.');
        Response::redirect(self::URL);
    }

    /** Step 1 after entering a partner's link: show the partner and the settings to confirm. */
    public function preview(Request $request): void
    {
        $link = $request->input('link', '') ?? '';
        try {
            $preview = $this->queries->ask(new PreviewPartnerInvitation($link));
        } catch (ValidationException $e) {
            $this->renderIndex($e->errors(), $request->post);

            return;
        }
        echo View::page('settings/partner-connect', [
            'preview' => $preview,
            'errors' => [],
            'old' => [],
        ], activeNav: self::NAV, title: 'Partnerschaft bestätigen');
    }

    public function connect(Request $request): void
    {
        try {
            $id = $this->commands->dispatch(new AcceptPartnerInvitation(
                link: $request->input('link', '') ?? '',
                autoAcceptRequests: $request->input('auto_accept') === '1',
                allowTechnicianRequests: $request->input('allow_technicians') === '1',
                symmetricDiscount: $request->input('symmetric') === '1',
                discount: $request->input('discount', '') ?? '',
                discountGranted: $request->input('discount_granted', '') ?? '',
                discountReceived: $request->input('discount_received', '') ?? '',
            ));
        } catch (ValidationException $e) {
            try {
                $preview = $this->queries->ask(new PreviewPartnerInvitation($request->input('link', '') ?? ''));
            } catch (ValidationException) {
                $this->renderIndex($e->errors(), $request->post);

                return;
            }
            echo View::page('settings/partner-connect', [
                'preview' => $preview,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: self::NAV, title: 'Partnerschaft bestätigen');

            return;
        }
        Flash::success('Die Anfrage wurde an den Partner geschickt. Sobald er die Partnerschaft bestätigt, ist sie aktiv.');
        Response::redirect(self::URL . '/' . $id);
    }

    public function show(Request $request): void
    {
        $this->renderShow($request->param('id'), [], []);
    }

    public function confirm(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new ConfirmPartner(
            partnerId: $id,
            autoAcceptRequests: $request->input('auto_accept') === '1',
            allowTechnicianRequests: $request->input('allow_technicians') === '1',
            symmetricDiscount: $request->input('symmetric') === '1',
            discount: $request->input('discount', '') ?? '',
            discountGranted: $request->input('discount_granted', '') ?? '',
            discountReceived: $request->input('discount_received', '') ?? '',
        )), 'Die Partnerschaft ist jetzt aktiv.', $request);
    }

    public function decline(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new DeclinePartner($id)), 'Die Partnerschaft wurde abgelehnt.', $request);
    }

    public function saveSettings(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new UpdatePartnerSettings(
            $id,
            $request->input('auto_accept') === '1',
            $request->input('allow_technicians') === '1',
            $request->input('customer_id', '') ?? '',
        )), 'Die Einstellungen wurden gespeichert.', $request);
    }

    public function proposeDiscount(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new ProposePartnerDiscount(
            $id,
            $request->input('symmetric') === '1',
            $request->input('discount', '') ?? '',
            $request->input('discount_granted', '') ?? '',
            $request->input('discount_received', '') ?? '',
        )), 'Der Rabattvorschlag wurde an den Partner geschickt. Er gilt, sobald der Partner zustimmt.', $request);
    }

    public function acceptDiscount(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new AcceptPartnerDiscount($id)), 'Die neuen Rabatte sind vereinbart.', $request);
    }

    public function rejectDiscount(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new RejectPartnerDiscount($id)), 'Der Rabattvorschlag wurde verworfen.', $request);
    }

    public function end(Request $request): void
    {
        $id = $request->param('id');
        try {
            $informed = $this->commands->dispatch(new EndPartnership($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        if ($informed) {
            Flash::success('Die Partnerschaft wurde beendet.');
        } else {
            Flash::error('Die Partnerschaft wurde beendet, der Partner konnte aber nicht benachrichtigt werden. Sein Zugang ist trotzdem gesperrt.');
        }
        Response::redirect(self::URL . '/' . $id);
    }

    public function delete(Request $request): void
    {
        $id = $request->param('id');
        try {
            $this->commands->dispatch(new DeletePartner($id));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect(self::URL . '/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Flash::success('Die Partner-Instanz wurde gelöscht.');
        Response::redirect(self::URL);
    }

    public function test(Request $request): void
    {
        $id = $request->param('id');
        try {
            $name = $this->commands->dispatch(new TestPartnerConnection($id));
            Flash::success("Verbindung zu „{$name}“ erfolgreich.");
        } catch (ValidationException $e) {
            Flash::error('Verbindungstest fehlgeschlagen: ' . implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect(self::URL . '/' . $id);
    }

    private function run(string $id, callable $action, string $success, Request $request): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->renderShow($id, $e->errors(), $request->post);

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Flash::success($success);
        Response::redirect(self::URL . '/' . $id);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderIndex(array $errors, array $old): void
    {
        $this->ensureSession();
        $newLink = $_SESSION[self::SESSION_LINK] ?? null;
        unset($_SESSION[self::SESSION_LINK]);

        echo View::page('settings/partners', [
            'overview' => $this->queries->ask(new GetPartnerOverview()),
            'newLink' => is_string($newLink) ? $newLink : null,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: self::NAV, title: 'Partner-Instanzen');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderShow(string $id, array $errors, array $old): void
    {
        try {
            $detail = $this->queries->ask(new GetPartner($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        echo View::page('settings/partner', [
            'detail' => $detail,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: self::NAV, title: 'Partner: ' . $detail->partner->name);
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
