<?php

declare(strict_types=1);

namespace App\Application\Federation;

use App\Domain\Exception\ValidationException;
use App\Domain\Federation\Discount;
use App\Domain\Federation\DiscountProposalSource;
use App\Domain\Federation\PartnerInstance;
use App\Domain\Federation\PartnerInstanceRepositoryInterface;
use App\Domain\Federation\PartnerInvitation;
use App\Domain\Federation\PartnerInvitationLink;
use App\Domain\Federation\PartnerInvitationRepositoryInterface;
use App\Domain\Federation\PartnerProfile;
use App\Domain\Federation\PartnerRequest;
use App\Domain\Federation\PartnerRequestDirection;
use App\Domain\Federation\PartnerRequestItem;
use App\Domain\Federation\PartnerRequestKind;
use App\Domain\Federation\PartnerRequestRepositoryInterface;
use App\Domain\Federation\PartnerRequestStatus;
use App\Domain\Federation\PartnerStatus;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Server side of the federation protocol (/api/federation/v1/*): everything a
 * partner installation can ask us. Requests are authenticated with the
 * bearer token we handed out when the partnership was created; only the
 * invitation endpoints are authenticated by the secret invitation code.
 *
 * All values exchanged are from the point of view of the SENDER (e.g.
 * "discount_granted" = what the sender grants the receiver).
 */
final class InboundFederationService
{
    public const PROTOCOL_VERSION = 1;

    public function __construct(
        private readonly PartnerInstanceRepositoryInterface $partners,
        private readonly PartnerInvitationRepositoryInterface $invitations,
        private readonly PartnerRequestRepositoryInterface $requests,
        private readonly PartnerRentalGatewayInterface $gateway,
        private readonly IncomingRequestAcceptor $acceptor,
        private readonly LocalInstanceInterface $local,
    ) {
    }

    /** @return array<string, mixed> */
    public function invitationInfo(string $code): array
    {
        $this->usableInvitation($code);

        return $this->self();
    }

    /**
     * The partner entered our invitation link and confirmed on its side.
     *
     * @param array<string, mixed> $payload base_url, name, profile, token, discount_granted, discount_received
     * @return array<string, mixed>
     */
    public function acceptInvitation(string $code, array $payload): array
    {
        $invitation = $this->usableInvitation($code);
        $now = new DateTimeImmutable();

        $name = FederationInput::text($payload['name'] ?? null, 255);
        $token = is_string($payload['token'] ?? null) ? $payload['token'] : '';
        if ($name === null || preg_match('/^[A-Za-z0-9]{32,128}$/', $token) !== 1) {
            throw new FederationApiException('Name oder Zugangstoken der Partner-Instanz fehlen.', 422);
        }
        try {
            $baseUrl = PartnerInvitationLink::normalizeBaseUrl(is_string($payload['base_url'] ?? null) ? $payload['base_url'] : '');
            $granted = Discount::parse($this->scalar($payload['discount_granted'] ?? null), 'discount');
            $received = Discount::parse($this->scalar($payload['discount_received'] ?? null), 'discount');
        } catch (ValidationException $e) {
            throw new FederationApiException(implode(' ', $e->errors()), 422);
        }
        if ($baseUrl === PartnerInvitationLink::normalizeBaseUrl($this->local->baseUrl())) {
            throw new FederationApiException('Eine Instanz kann sich nicht mit sich selbst befreunden.', 422);
        }
        foreach ($this->partners->findAll() as $existing) {
            if ($existing->baseUrl === $baseUrl && in_array($existing->status, [PartnerStatus::Active, PartnerStatus::AwaitingPartner, PartnerStatus::PendingConfirmation], true)) {
                throw new FederationApiException('Mit dieser Instanz besteht bereits eine Partnerschaft.', 409);
            }
        }

        $ourToken = FederationInput::token();
        $partner = new PartnerInstance(
            id: Uuid::v4(),
            name: $name,
            baseUrl: $baseUrl,
            status: PartnerStatus::PendingConfirmation,
            outboundToken: $token,
            inboundTokenHash: hash('sha256', $ourToken),
            profile: PartnerProfile::fromArray(is_array($payload['profile'] ?? null) ? $payload['profile'] : []),
            autoAcceptRequests: false,
            allowTechnicianRequests: true,
            discountGranted: 0.0,
            discountReceived: 0.0,
            proposedDiscountGranted: null,
            proposedDiscountReceived: null,
            proposalFrom: null,
            customerId: null,
            lastError: null,
            lastContactAt: $now,
            confirmedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
        $partner->receiveProposal($granted, $received, $now);
        $this->partners->save($partner);

        $invitation->usedAt = $now;
        $invitation->partnerId = $partner->id;
        $this->invitations->save($invitation);

        return $this->self() + ['token' => $ourToken];
    }

    public function authenticate(?string $token): PartnerInstance
    {
        $partner = $token !== null && $token !== '' ? $this->partners->findByInboundTokenHash(hash('sha256', $token)) : null;
        if ($partner === null) {
            throw new FederationApiException('Ungültiges Zugangstoken.', 401);
        }
        if (in_array($partner->status, [PartnerStatus::Ended, PartnerStatus::Declined], true)) {
            throw new FederationApiException('Die Partnerschaft besteht nicht mehr.', 403);
        }
        $partner->recordContact(new DateTimeImmutable());
        $this->partners->save($partner);

        return $partner;
    }

    /** @return array<string, mixed> */
    public function partnership(PartnerInstance $partner): array
    {
        return $this->self() + [
            'status' => $partner->status->value,
            // Our point of view = sender's point of view.
            'discount_granted' => $partner->discountGranted,
            'discount_received' => $partner->discountReceived,
            'allows_technician_requests' => $partner->allowTechnicianRequests,
        ];
    }

    /**
     * The inviting side confirmed (or declined) the partnership.
     *
     * @param array<string, mixed> $payload decision, discount{action, granted, received}
     * @return array<string, mixed>
     */
    public function confirm(PartnerInstance $partner, array $payload): array
    {
        if ($partner->status !== PartnerStatus::AwaitingPartner) {
            throw new FederationApiException('Die Partnerschaft wartet nicht auf eine Bestätigung.', 409);
        }
        $now = new DateTimeImmutable();
        if (($payload['decision'] ?? '') === 'decline') {
            $partner->status = PartnerStatus::Declined;
            $partner->clearProposal($now);
            $this->partners->save($partner);

            return ['status' => $partner->status->value];
        }

        $partner->activate($now);
        if (is_array($payload['discount'] ?? null)) {
            $this->applyDiscountAction($partner, $payload['discount'], $now);
        }
        $this->partners->save($partner);

        return $this->partnership($partner);
    }

    /**
     * @param array<string, mixed> $payload action (propose|accept|reject), granted, received
     * @return array<string, mixed>
     */
    public function discount(PartnerInstance $partner, array $payload): array
    {
        $this->requireActive($partner);
        $now = new DateTimeImmutable();
        $this->applyDiscountAction($partner, $payload, $now);
        $this->partners->save($partner);

        return $this->partnership($partner);
    }

    /** @return array<string, mixed> */
    public function end(PartnerInstance $partner): array
    {
        $partner->status = PartnerStatus::Ended;
        $partner->clearProposal(new DateTimeImmutable());
        $this->partners->save($partner);

        return ['status' => $partner->status->value];
    }

    /**
     * @param array<string, mixed> $query q, from, to, page
     * @return array<string, mixed>
     */
    public function articles(PartnerInstance $partner, array $query): array
    {
        $this->requireActive($partner);
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        try {
            [$from, $to] = FederationInput::period($query['from'] ?? $today, $query['to'] ?? ($query['from'] ?? $today));
        } catch (ValidationException $e) {
            throw new FederationApiException(implode(' ', $e->errors()), 422);
        }
        $page = max(1, (int) ($query['page'] ?? 1));
        $pageSize = 25;
        $result = $this->gateway->listArticles(FederationInput::text($query['q'] ?? null, 100), $from, $to, $page, $pageSize);

        $items = [];
        foreach ($result['items'] as $article) {
            $price = $article['daily_price'];
            $items[] = $article + [
                'discount_percent' => $partner->discountGranted,
                'discounted_daily_price' => $price !== null ? Discount::apply($price, $partner->discountGranted) : null,
            ];
        }

        return [
            'data' => $items,
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $result['total'],
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'discount_percent' => $partner->discountGranted,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function technicians(PartnerInstance $partner): array
    {
        $this->requireActive($partner);
        if (!$partner->allowTechnicianRequests) {
            return ['allowed' => false, 'technicians' => [], 'trades' => []];
        }

        return [
            'allowed' => true,
            'technicians' => $this->gateway->listTechnicians(),
            'trades' => array_column($this->gateway->listTrades(), 'name'),
        ];
    }

    /**
     * New material or technician request from the partner.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createRequest(PartnerInstance $partner, array $payload): array
    {
        $this->requireActive($partner);
        $reference = is_string($payload['reference'] ?? null) ? strtolower($payload['reference']) : '';
        if (preg_match(FederationInput::UUID_PATTERN, $reference) !== 1) {
            throw new FederationApiException('Die Anfrage hat keine gültige Referenz.', 422);
        }
        $existing = $this->requests->findByReference($partner->id, PartnerRequestDirection::Incoming, $reference);
        if ($existing !== null) {
            return PartnerRequestPayload::status($existing); // repeated delivery
        }
        $kind = PartnerRequestKind::tryFrom(is_string($payload['kind'] ?? null) ? $payload['kind'] : '');
        if ($kind === null) {
            throw new FederationApiException('Unbekannte Art der Anfrage.', 422);
        }
        if ($kind === PartnerRequestKind::Technician && !$partner->allowTechnicianRequests) {
            throw new FederationApiException('Techniker-Anfragen sind für diese Partnerschaft nicht freigegeben.', 403);
        }

        try {
            [$from, $to] = FederationInput::period($payload['start_date'] ?? null, $payload['end_date'] ?? null);
        } catch (ValidationException $e) {
            throw new FederationApiException(implode(' ', $e->errors()), 422);
        }
        $eventName = FederationInput::text($payload['event_name'] ?? null, 200);
        if ($eventName === null) {
            throw new FederationApiException('Bitte einen Namen für die Veranstaltung angeben.', 422);
        }

        $items = [];
        $trades = [];
        $technicianCount = 0;
        $preferred = null;
        if ($kind === PartnerRequestKind::Material) {
            $items = $this->requestedItems($payload['items'] ?? null);
        } else {
            $trades = PartnerRequestPayload::stringList($payload['trades'] ?? null);
            $technicianCount = max(1, min(50, (int) ($payload['technician_count'] ?? 1)));
            $preferredId = is_scalar($payload['preferred_technician_id'] ?? null) ? (string) $payload['preferred_technician_id'] : '';
            foreach ($this->gateway->listTechnicians() as $technician) {
                if ($technician['id'] === $preferredId) {
                    $preferred = $technician;
                }
            }
        }

        $now = new DateTimeImmutable();
        $request = new PartnerRequest(
            id: Uuid::v4(),
            partnerId: $partner->id,
            direction: PartnerRequestDirection::Incoming,
            kind: $kind,
            reference: $reference,
            status: PartnerRequestStatus::Pending,
            eventName: $eventName,
            startDate: $from,
            endDate: $to,
            eventLocation: FederationInput::text($payload['event_location'] ?? null, 255),
            note: FederationInput::text($payload['note'] ?? null, 2000),
            contactName: FederationInput::text($payload['contact_name'] ?? null, 255),
            contactEmail: FederationInput::text($payload['contact_email'] ?? null, 255),
            items: $items,
            trades: $trades,
            technicianCount: $technicianCount,
            preferredTechnician: $preferred,
            technicians: [],
            discountPercent: $kind === PartnerRequestKind::Material ? $partner->discountGranted : null,
            rentalId: null,
            responseMessage: null,
            syncError: null,
            createdAt: $now,
            updatedAt: $now,
            respondedAt: null,
        );
        $this->requests->save($request);

        if ($kind === PartnerRequestKind::Material && $partner->autoAcceptRequests) {
            try {
                $this->acceptor->acceptMaterial($request, $partner, null, $now);
            } catch (ValidationException $e) {
                $request->syncError = mb_substr('Automatische Zusage nicht möglich: ' . implode(' ', $e->errors()), 0, 1000);
                $this->requests->save($request);
            }
        }

        return PartnerRequestPayload::status($request);
    }

    /** @return array<string, mixed> */
    public function requestStatus(PartnerInstance $partner, string $reference): array
    {
        $request = $this->incomingRequest($partner, $reference);
        if ($request->kind === PartnerRequestKind::Technician && $request->rentalId !== null) {
            $request->technicians = $this->gateway->technicianStatuses($request->rentalId);
            $this->requests->save($request);
        }

        return PartnerRequestPayload::status($request);
    }

    /** @return array<string, mixed> */
    public function cancelRequest(PartnerInstance $partner, string $reference): array
    {
        $request = $this->incomingRequest($partner, $reference);
        if (in_array($request->status, [PartnerRequestStatus::Pending, PartnerRequestStatus::Accepted], true)) {
            $now = new DateTimeImmutable();
            if ($request->status === PartnerRequestStatus::Accepted && $request->rentalId !== null) {
                $request->syncError = 'Der Partner hat die bereits zugesagte Anfrage storniert. Bitte die angelegte Veranstaltung prüfen.';
            }
            $request->status = PartnerRequestStatus::Cancelled;
            $request->updatedAt = $now;
            $this->requests->save($request);
        }

        return PartnerRequestPayload::status($request);
    }

    /**
     * The partner reports a new state of a request we sent it.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function receiveStatus(PartnerInstance $partner, string $reference, array $payload): array
    {
        $request = $this->requests->findByReference($partner->id, PartnerRequestDirection::Outgoing, strtolower($reference));
        if ($request === null) {
            throw new FederationApiException('Die Anfrage ist unbekannt.', 404);
        }
        PartnerRequestPayload::apply($request, $payload, new DateTimeImmutable());
        $this->requests->save($request);

        return ['ok' => true];
    }

    /** @param array<string, mixed> $action */
    private function applyDiscountAction(PartnerInstance $partner, array $action, DateTimeImmutable $now): void
    {
        $type = is_string($action['action'] ?? null) ? $action['action'] : '';
        try {
            $granted = Discount::parse($this->scalar($action['granted'] ?? null), 'discount');
            $received = Discount::parse($this->scalar($action['received'] ?? null), 'discount');
        } catch (ValidationException $e) {
            throw new FederationApiException(implode(' ', $e->errors()), 422);
        }

        switch ($type) {
            case 'propose':
                $partner->receiveProposal($granted, $received, $now);
                break;
            case 'accept':
                // The partner accepted OUR proposal; its values are swapped from our point of view.
                if ($partner->proposalFrom !== DiscountProposalSource::Us) {
                    throw new FederationApiException('Es liegt kein eigener Rabattvorschlag vor.', 409);
                }
                try {
                    $partner->acceptProposal($now, $received, $granted);
                } catch (ValidationException $e) {
                    throw new FederationApiException(implode(' ', $e->errors()), 409);
                }
                break;
            case 'reject':
                $partner->clearProposal($now);
                break;
            default:
                throw new FederationApiException('Unbekannte Rabatt-Aktion.', 422);
        }
    }

    /** @return PartnerRequestItem[] */
    private function requestedItems(mixed $rows): array
    {
        if (!is_array($rows) || $rows === []) {
            throw new FederationApiException('Bitte mindestens einen Artikel anfragen.', 422);
        }
        $quantities = [];
        foreach (array_slice($rows, 0, 200) as $row) {
            $item = is_array($row) ? PartnerRequestItem::fromArray($row) : null;
            if ($item === null) {
                throw new FederationApiException('Ungültige Artikelposition in der Anfrage.', 422);
            }
            $quantities[$item->articleId] = ($quantities[$item->articleId] ?? 0) + $item->quantity;
        }

        $items = [];
        foreach ($quantities as $articleId => $quantity) {
            $article = $this->gateway->findRentableArticle((string) $articleId);
            if ($article === null) {
                throw new FederationApiException('Ein angefragter Artikel ist nicht (mehr) vermietbar.', 422);
            }
            $items[] = new PartnerRequestItem($article['id'], $article['name'], $quantity, $article['daily_price']);
        }

        return $items;
    }

    private function incomingRequest(PartnerInstance $partner, string $reference): PartnerRequest
    {
        $request = $this->requests->findByReference($partner->id, PartnerRequestDirection::Incoming, strtolower($reference));
        if ($request === null) {
            throw new FederationApiException('Die Anfrage ist unbekannt.', 404);
        }

        return $request;
    }

    private function usableInvitation(string $code): PartnerInvitation
    {
        $invitation = $this->invitations->findByCodeHash(PartnerInvitation::hashCode($code));
        if ($invitation === null || !$invitation->isUsable(new DateTimeImmutable())) {
            throw new FederationApiException('Der Einladungslink ist ungültig, abgelaufen oder wurde bereits verwendet.', 404);
        }

        return $invitation;
    }

    private function requireActive(PartnerInstance $partner): void
    {
        if (!$partner->isActive()) {
            throw new FederationApiException('Die Partnerschaft ist noch nicht bestätigt.', 403);
        }
    }

    /** @return array<string, mixed> */
    private function self(): array
    {
        return [
            'protocol' => self::PROTOCOL_VERSION,
            'name' => $this->local->name(),
            'base_url' => $this->local->baseUrl(),
            'profile' => $this->local->profile()->toArray(),
        ];
    }

    private function scalar(mixed $value): string|float|int|null
    {
        return is_string($value) || is_int($value) || is_float($value) ? $value : null;
    }
}
