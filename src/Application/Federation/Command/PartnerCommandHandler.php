<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Federation\FederationClientInterface;
use App\Application\Federation\FederationException;
use App\Application\Federation\FederationInput;
use App\Application\Federation\IncomingRequestAcceptor;
use App\Application\Federation\LocalInstanceInterface;
use App\Application\Federation\PartnerRentalGatewayInterface;
use App\Application\Federation\PartnerRequestPayload;
use App\Domain\Exception\NotFoundException;
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
 * All user actions around partner installations (migration 160): invitation
 * links, confirming partnerships, discount agreements and sending/answering
 * material and technician requests. Every action that affects both sides
 * calls the partner first and only changes our data when the partner
 * accepted the call, so both installations stay consistent.
 */
final class PartnerCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly PartnerInstanceRepositoryInterface $partners,
        private readonly PartnerInvitationRepositoryInterface $invitations,
        private readonly PartnerRequestRepositoryInterface $requests,
        private readonly FederationClientInterface $client,
        private readonly LocalInstanceInterface $local,
        private readonly PartnerRentalGatewayInterface $gateway,
        private readonly IncomingRequestAcceptor $acceptor,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof CreatePartnerInvitation => $this->createInvitation($command),
            $command instanceof DeletePartnerInvitation => $this->invitations->delete($command->invitationId),
            $command instanceof AcceptPartnerInvitation => $this->acceptInvitation($command),
            $command instanceof ConfirmPartner => $this->confirm($command),
            $command instanceof DeclinePartner => $this->decline($command),
            $command instanceof UpdatePartnerSettings => $this->updateSettings($command),
            $command instanceof ProposePartnerDiscount => $this->proposeDiscount($command),
            $command instanceof AcceptPartnerDiscount => $this->acceptDiscount($command),
            $command instanceof RejectPartnerDiscount => $this->rejectDiscount($command),
            $command instanceof EndPartnership => $this->end($command),
            $command instanceof DeletePartner => $this->deletePartner($command),
            $command instanceof TestPartnerConnection => $this->test($command),
            $command instanceof SendPartnerMaterialRequest => $this->sendMaterialRequest($command),
            $command instanceof SendPartnerTechnicianRequest => $this->sendTechnicianRequest($command),
            $command instanceof AcceptIncomingPartnerRequest => $this->acceptIncoming($command),
            $command instanceof DeclineIncomingPartnerRequest => $this->declineIncoming($command),
            $command instanceof CancelOutgoingPartnerRequest => $this->cancelOutgoing($command),
            $command instanceof RefreshOutgoingPartnerRequest => $this->refreshOutgoing($command),
            $command instanceof ResendPartnerRequestStatus => $this->resendStatus($command),
            default => throw new \InvalidArgumentException('Unsupported command ' . $command::class),
        };
    }

    /** @return array{link: string, expiresAt: DateTimeImmutable} */
    private function createInvitation(CreatePartnerInvitation $command): array
    {
        $code = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = new DateTimeImmutable();
        $note = FederationInput::text($command->note, 255);
        $invitation = new PartnerInvitation(
            Uuid::v4(),
            PartnerInvitation::hashCode($code),
            $note,
            $now->modify('+' . PartnerInvitation::VALIDITY_DAYS . ' days'),
            null,
            null,
            $now,
        );
        $this->invitations->save($invitation);

        return ['link' => PartnerInvitationLink::build($this->local->baseUrl(), $code), 'expiresAt' => $invitation->expiresAt];
    }

    private function acceptInvitation(AcceptPartnerInvitation $command): string
    {
        ['baseUrl' => $baseUrl, 'code' => $code] = PartnerInvitationLink::parse($command->link);
        [$granted, $received] = $this->discounts($command->symmetricDiscount, $command->discount, $command->discountGranted, $command->discountReceived);
        if ($baseUrl === PartnerInvitationLink::normalizeBaseUrl($this->local->baseUrl())) {
            throw new ValidationException(['link' => 'Das ist ein Einladungslink dieser Instanz. Er muss bei der anderen Instanz eingegeben werden.']);
        }
        foreach ($this->partners->findAll() as $existing) {
            if ($existing->baseUrl === $baseUrl && in_array($existing->status, [PartnerStatus::Active, PartnerStatus::AwaitingPartner, PartnerStatus::PendingConfirmation], true)) {
                throw new ValidationException(['link' => "Mit „{$existing->name}“ besteht bereits eine Partnerschaft."]);
            }
        }

        $ourToken = FederationInput::token();
        try {
            $response = $this->client->request('POST', $baseUrl, PartnerInvitationLink::PATH . $code . '/accept', null, [
                'protocol' => \App\Application\Federation\InboundFederationService::PROTOCOL_VERSION,
                'name' => $this->local->name(),
                'base_url' => $this->local->baseUrl(),
                'profile' => $this->local->profile()->toArray(),
                'token' => $ourToken,
                'discount_granted' => $granted,
                'discount_received' => $received,
            ]);
        } catch (FederationException $e) {
            throw new ValidationException(['link' => 'Die Partner-Instanz hat die Einladung nicht angenommen: ' . $e->getMessage()]);
        }
        $token = is_string($response['token'] ?? null) ? $response['token'] : '';
        if (preg_match('/^[A-Za-z0-9]{32,128}$/', $token) !== 1) {
            throw new ValidationException(['link' => 'Die Partner-Instanz hat keine gültige Antwort geschickt.']);
        }

        $now = new DateTimeImmutable();
        $partner = new PartnerInstance(
            id: Uuid::v4(),
            name: FederationInput::text($response['name'] ?? null, 255) ?? parse_url($baseUrl, PHP_URL_HOST) ?? $baseUrl,
            baseUrl: $baseUrl,
            status: PartnerStatus::AwaitingPartner,
            outboundToken: $token,
            inboundTokenHash: hash('sha256', $ourToken),
            profile: PartnerProfile::fromArray(is_array($response['profile'] ?? null) ? $response['profile'] : []),
            autoAcceptRequests: $command->autoAcceptRequests,
            allowTechnicianRequests: $command->allowTechnicianRequests,
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
        $partner->proposeDiscount($granted, $received, $now);
        $this->partners->save($partner);

        return $partner->id;
    }

    private function confirm(ConfirmPartner $command): void
    {
        $partner = $this->partner($command->partnerId);
        if ($partner->status !== PartnerStatus::PendingConfirmation) {
            throw new ValidationException(['partner' => 'Diese Partnerschaft wartet nicht auf deine Bestätigung.']);
        }
        [$granted, $received] = $this->discounts($command->symmetricDiscount, $command->discount, $command->discountGranted, $command->discountReceived);

        // Unchanged values accept the partner's proposal, changed values become our counter-proposal.
        $acceptsProposal = $partner->proposalFrom === DiscountProposalSource::Them
            && Discount::equals($granted, (float) $partner->proposedDiscountGranted)
            && Discount::equals($received, (float) $partner->proposedDiscountReceived);

        $this->call($partner, 'POST', '/partnership/confirm', [
            'decision' => 'accept',
            'discount' => ['action' => $acceptsProposal ? 'accept' : 'propose', 'granted' => $granted, 'received' => $received],
        ]);

        $now = new DateTimeImmutable();
        $partner->activate($now);
        if ($acceptsProposal) {
            $partner->acceptProposal($now);
        } else {
            $partner->proposeDiscount($granted, $received, $now);
        }
        $partner->autoAcceptRequests = $command->autoAcceptRequests;
        $partner->allowTechnicianRequests = $command->allowTechnicianRequests;
        $this->partners->save($partner);
    }

    private function decline(DeclinePartner $command): void
    {
        $partner = $this->partner($command->partnerId);
        if ($partner->status !== PartnerStatus::PendingConfirmation) {
            throw new ValidationException(['partner' => 'Diese Partnerschaft wartet nicht auf deine Bestätigung.']);
        }
        try {
            $this->call($partner, 'POST', '/partnership/confirm', ['decision' => 'decline']);
        } catch (ValidationException) {
            // The partner is informed on a best-effort basis; declining works anyway.
        }
        $partner->status = PartnerStatus::Declined;
        $partner->clearProposal(new DateTimeImmutable());
        $this->partners->save($partner);
    }

    private function updateSettings(UpdatePartnerSettings $command): void
    {
        $partner = $this->partner($command->partnerId);
        $partner->autoAcceptRequests = $command->autoAcceptRequests;
        $partner->allowTechnicianRequests = $command->allowTechnicianRequests;
        if ($command->customerId !== null) {
            $partner->customerId = trim($command->customerId) === '' ? null : trim($command->customerId);
        }
        $partner->updatedAt = new DateTimeImmutable();
        $this->partners->save($partner);
    }

    private function proposeDiscount(ProposePartnerDiscount $command): void
    {
        $partner = $this->activePartner($command->partnerId);
        [$granted, $received] = $this->discounts($command->symmetricDiscount, $command->discount, $command->discountGranted, $command->discountReceived);
        if (Discount::equals($granted, $partner->discountGranted) && Discount::equals($received, $partner->discountReceived)) {
            throw new ValidationException(['discount' => 'Diese Rabatte sind bereits vereinbart.']);
        }
        $this->call($partner, 'POST', '/partnership/discount', ['action' => 'propose', 'granted' => $granted, 'received' => $received]);
        $partner->proposeDiscount($granted, $received, new DateTimeImmutable());
        $this->partners->save($partner);
    }

    private function acceptDiscount(AcceptPartnerDiscount $command): void
    {
        $partner = $this->activePartner($command->partnerId);
        if ($partner->proposalFrom !== DiscountProposalSource::Them || !$partner->hasPendingProposal()) {
            throw new ValidationException(['discount' => 'Es liegt kein Rabattvorschlag des Partners vor.']);
        }
        $this->call($partner, 'POST', '/partnership/discount', [
            'action' => 'accept',
            'granted' => $partner->proposedDiscountGranted,
            'received' => $partner->proposedDiscountReceived,
        ]);
        $partner->acceptProposal(new DateTimeImmutable());
        $this->partners->save($partner);
    }

    private function rejectDiscount(RejectPartnerDiscount $command): void
    {
        $partner = $this->activePartner($command->partnerId);
        if (!$partner->hasPendingProposal()) {
            throw new ValidationException(['discount' => 'Es liegt kein offener Rabattvorschlag vor.']);
        }
        $this->call($partner, 'POST', '/partnership/discount', ['action' => 'reject', 'granted' => 0, 'received' => 0]);
        $partner->clearProposal(new DateTimeImmutable());
        $this->partners->save($partner);
    }

    /** @return bool whether the partner could be informed */
    private function end(EndPartnership $command): bool
    {
        $partner = $this->partner($command->partnerId);
        $informed = true;
        try {
            $this->call($partner, 'POST', '/partnership/end', []);
        } catch (ValidationException) {
            $informed = false; // ending works without the partner; its token is no longer accepted
        }
        $partner->status = PartnerStatus::Ended;
        $partner->clearProposal(new DateTimeImmutable());
        $this->partners->save($partner);

        return $informed;
    }

    private function deletePartner(DeletePartner $command): void
    {
        $partner = $this->partner($command->partnerId);
        if ($partner->isActive()) {
            throw new ValidationException(['partner' => 'Eine aktive Partnerschaft muss zuerst beendet werden.']);
        }
        $this->partners->delete($partner->id);
    }

    private function test(TestPartnerConnection $command): string
    {
        $partner = $this->partner($command->partnerId);
        $response = $this->call($partner, 'GET', '/partnership');
        $name = FederationInput::text($response['name'] ?? null, 255);
        if ($name !== null) {
            $partner->name = $name;
        }
        if (is_array($response['profile'] ?? null)) {
            $partner->profile = PartnerProfile::fromArray($response['profile']);
        }
        $this->partners->save($partner);

        return $partner->name;
    }

    private function sendMaterialRequest(SendPartnerMaterialRequest $command): string
    {
        $partner = $this->activePartner($command->partnerId);
        $items = [];
        foreach ($command->quantities as $articleId => $quantity) {
            $quantity = trim((string) $quantity);
            if ($quantity === '' || $quantity === '0') {
                continue;
            }
            $value = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1) {
                throw new ValidationException(['items' => 'Bitte nur ganze Mengen ab 1 angeben.']);
            }
            $items[] = ['article_id' => (string) $articleId, 'quantity' => $value];
        }
        if ($items === []) {
            throw new ValidationException(['items' => 'Bitte bei mindestens einem Artikel eine Menge angeben.']);
        }

        $request = $this->newOutgoing($partner, PartnerRequestKind::Material, $command->rentalId, $command->eventName, $command->startDate, $command->endDate, $command->eventLocation, $command->note, $command->contactName, $command->contactEmail);
        $request->items = array_map(static fn (array $row) => new PartnerRequestItem($row['article_id'], '', $row['quantity']), $items);

        $response = $this->call($partner, 'POST', '/requests', $this->requestBody($request) + ['items' => $items]);
        PartnerRequestPayload::apply($request, $response, new DateTimeImmutable());
        $this->requests->save($request);

        return $request->id;
    }

    private function sendTechnicianRequest(SendPartnerTechnicianRequest $command): string
    {
        $partner = $this->activePartner($command->partnerId);
        $count = filter_var(trim($command->technicianCount), FILTER_VALIDATE_INT);
        if ($count === false || $count < 1 || $count > 50) {
            throw new ValidationException(['technicianCount' => 'Bitte eine Anzahl zwischen 1 und 50 angeben.']);
        }
        $request = $this->newOutgoing($partner, PartnerRequestKind::Technician, $command->rentalId, $command->eventName, $command->startDate, $command->endDate, $command->eventLocation, $command->note, $command->contactName, $command->contactEmail);
        $request->trades = PartnerRequestPayload::stringList($command->trades);
        $request->technicianCount = $count;
        $preferredId = trim($command->preferredTechnicianId);

        $response = $this->call($partner, 'POST', '/requests', $this->requestBody($request) + [
            'trades' => $request->trades,
            'technician_count' => $count,
            'preferred_technician_id' => $preferredId,
        ]);
        if ($preferredId !== '') {
            $request->preferredTechnician = ['id' => $preferredId, 'name' => FederationInput::text($command->preferredTechnicianName, 255) ?? $preferredId];
        }
        PartnerRequestPayload::apply($request, $response, new DateTimeImmutable());
        $this->requests->save($request);

        return $request->id;
    }

    private function acceptIncoming(AcceptIncomingPartnerRequest $command): void
    {
        $request = $this->incoming($command->requestId);
        $partner = $this->partner($request->partnerId);
        $message = FederationInput::text($command->message, 1000);
        $now = new DateTimeImmutable();
        if ($request->kind === PartnerRequestKind::Material) {
            $this->acceptor->acceptMaterial($request, $partner, $message, $now);
        } else {
            $this->acceptor->acceptTechnicians($request, $partner, $command->technicianIds, $command->tradeIds, $message, $now);
        }
        $this->pushStatus($request, $partner);
    }

    private function declineIncoming(DeclineIncomingPartnerRequest $command): void
    {
        $request = $this->incoming($command->requestId);
        if ($request->status !== PartnerRequestStatus::Pending) {
            throw new ValidationException(['request' => 'Die Anfrage ist nicht mehr offen.']);
        }
        $now = new DateTimeImmutable();
        $request->status = PartnerRequestStatus::Declined;
        $request->responseMessage = FederationInput::text($command->message, 1000);
        $request->respondedAt = $now;
        $request->syncError = null;
        $request->updatedAt = $now;
        $this->requests->save($request);
        $this->pushStatus($request, $this->partner($request->partnerId));
    }

    private function cancelOutgoing(CancelOutgoingPartnerRequest $command): void
    {
        $request = $this->outgoing($command->requestId);
        if (!in_array($request->status, [PartnerRequestStatus::Pending, PartnerRequestStatus::Accepted], true)) {
            throw new ValidationException(['request' => 'Die Anfrage kann nicht mehr storniert werden.']);
        }
        $partner = $this->partner($request->partnerId);
        $response = $this->call($partner, 'POST', '/requests/' . $request->reference . '/cancel', []);
        PartnerRequestPayload::apply($request, $response, new DateTimeImmutable());
        $request->status = PartnerRequestStatus::Cancelled;
        $this->requests->save($request);
    }

    private function refreshOutgoing(RefreshOutgoingPartnerRequest $command): void
    {
        $request = $this->outgoing($command->requestId);
        $partner = $this->partner($request->partnerId);
        try {
            $response = $this->call($partner, 'GET', '/requests/' . $request->reference);
        } catch (ValidationException $e) {
            $request->syncError = mb_substr(implode(' ', $e->errors()), 0, 1000);
            $this->requests->save($request);
            throw $e;
        }
        PartnerRequestPayload::apply($request, $response, new DateTimeImmutable());
        $this->requests->save($request);
    }

    private function resendStatus(ResendPartnerRequestStatus $command): bool
    {
        $request = $this->incoming($command->requestId);

        return $this->pushStatus($request, $this->partner($request->partnerId));
    }

    /** Informs the requesting partner about a decision; failures are kept on the request. */
    private function pushStatus(PartnerRequest $request, PartnerInstance $partner): bool
    {
        try {
            $this->call($partner, 'POST', '/requests/' . $request->reference . '/status', PartnerRequestPayload::status($request));
            $request->syncError = null;
            $this->requests->save($request);

            return true;
        } catch (ValidationException $e) {
            $request->syncError = mb_substr('Der Partner konnte nicht benachrichtigt werden: ' . implode(' ', $e->errors()), 0, 1000);
            $this->requests->save($request);

            return false;
        }
    }

    private function newOutgoing(
        PartnerInstance $partner,
        PartnerRequestKind $kind,
        ?string $rentalId,
        string $eventName,
        string $startDate,
        string $endDate,
        string $eventLocation,
        string $note,
        ?string $contactName,
        ?string $contactEmail,
    ): PartnerRequest {
        $rentalId = $rentalId !== null && trim($rentalId) !== '' ? trim($rentalId) : null;
        $summary = $rentalId !== null ? $this->gateway->rentalSummary($rentalId) : null;
        if ($rentalId !== null && $summary === null) {
            $rentalId = null;
        }
        $eventName = FederationInput::text($eventName, 200) ?? ($summary['event_name'] ?? null);
        if ($eventName === null) {
            throw new ValidationException(['eventName' => 'Bitte einen Namen für die Veranstaltung angeben.']);
        }
        [$from, $to] = FederationInput::period(
            trim($startDate) !== '' ? $startDate : ($summary['start_date'] ?? ''),
            trim($endDate) !== '' ? $endDate : ($summary['end_date'] ?? ''),
        );
        $now = new DateTimeImmutable();
        $reference = Uuid::v4();

        return new PartnerRequest(
            id: $reference,
            partnerId: $partner->id,
            direction: PartnerRequestDirection::Outgoing,
            kind: $kind,
            reference: $reference,
            status: PartnerRequestStatus::Pending,
            eventName: $eventName,
            startDate: $from,
            endDate: $to,
            eventLocation: FederationInput::text($eventLocation, 255) ?? ($summary['location'] ?? null),
            note: FederationInput::text($note, 2000),
            contactName: FederationInput::text($contactName, 255),
            contactEmail: FederationInput::text($contactEmail, 255),
            items: [],
            trades: [],
            technicianCount: 0,
            preferredTechnician: null,
            technicians: [],
            discountPercent: null,
            rentalId: $rentalId,
            responseMessage: null,
            syncError: null,
            createdAt: $now,
            updatedAt: $now,
            respondedAt: null,
        );
    }

    /** @return array<string, mixed> */
    private function requestBody(PartnerRequest $request): array
    {
        return [
            'reference' => $request->reference,
            'kind' => $request->kind->value,
            'event_name' => $request->eventName,
            'start_date' => $request->startDate->format('Y-m-d'),
            'end_date' => $request->endDate->format('Y-m-d'),
            'event_location' => $request->eventLocation,
            'note' => $request->note,
            'contact_name' => $request->contactName,
            'contact_email' => $request->contactEmail,
        ];
    }

    /**
     * Authenticated call to the partner (path relative to /api/federation/v1).
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function call(PartnerInstance $partner, string $method, string $path, ?array $body = null): array
    {
        if ($partner->outboundToken === null) {
            throw new ValidationException(['partner' => 'Für diese Partner-Instanz ist kein Zugangstoken hinterlegt.']);
        }
        try {
            $response = $this->client->request($method, $partner->baseUrl, '/api/federation/v1' . $path, $partner->outboundToken, $body);
        } catch (FederationException $e) {
            $partner->recordError($e->getMessage());
            $this->partners->save($partner);
            throw new ValidationException(['partner' => "„{$partner->name}“: " . $e->getMessage()]);
        }
        $partner->recordContact(new DateTimeImmutable());
        $this->partners->save($partner);

        return $response;
    }

    /** @return array{0: float, 1: float} [granted, received] */
    private function discounts(bool $symmetric, string $discount, string $granted, string $received): array
    {
        if ($symmetric) {
            $value = Discount::parse($discount, 'discount');

            return [$value, $value];
        }

        return [Discount::parse($granted, 'discountGranted'), Discount::parse($received, 'discountReceived')];
    }

    private function partner(string $id): PartnerInstance
    {
        return $this->partners->findById($id) ?? throw new NotFoundException('Die Partner-Instanz wurde nicht gefunden.');
    }

    private function activePartner(string $id): PartnerInstance
    {
        $partner = $this->partner($id);
        if (!$partner->isActive()) {
            throw new ValidationException(['partner' => 'Die Partnerschaft ist nicht aktiv.']);
        }

        return $partner;
    }

    private function incoming(string $id): PartnerRequest
    {
        $request = $this->requests->findById($id);
        if ($request === null || !$request->isIncoming()) {
            throw new NotFoundException('Die Anfrage wurde nicht gefunden.');
        }

        return $request;
    }

    private function outgoing(string $id): PartnerRequest
    {
        $request = $this->requests->findById($id);
        if ($request === null || $request->isIncoming()) {
            throw new NotFoundException('Die Anfrage wurde nicht gefunden.');
        }

        return $request;
    }
}
