<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Application\Federation\FederationClientInterface;
use App\Application\Federation\FederationException;
use App\Application\Federation\FederationInput;
use App\Application\Federation\IncomingRequestAcceptor;
use App\Application\Federation\LocalInstanceInterface;
use App\Application\Federation\PartnerRentalGatewayInterface;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Federation\PartnerInstance;
use App\Domain\Federation\PartnerInstanceRepositoryInterface;
use App\Domain\Federation\PartnerInvitationLink;
use App\Domain\Federation\PartnerInvitationRepositoryInterface;
use App\Domain\Federation\PartnerProfile;
use App\Domain\Federation\PartnerRequest;
use App\Domain\Federation\PartnerRequestKind;
use App\Domain\Federation\PartnerRequestRepositoryInterface;
use App\Domain\Federation\PartnerRequestStatus;

/** Read side of the partner installations (migration 160). */
final class PartnerQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly PartnerInstanceRepositoryInterface $partners,
        private readonly PartnerInvitationRepositoryInterface $invitations,
        private readonly PartnerRequestRepositoryInterface $requests,
        private readonly FederationClientInterface $client,
        private readonly LocalInstanceInterface $local,
        private readonly PartnerRentalGatewayInterface $gateway,
        private readonly IncomingRequestAcceptor $acceptor,
        private readonly ?CustomerRepositoryInterface $customers = null,
    ) {
    }

    public function handle(QueryInterface $query): mixed
    {
        return match (true) {
            $query instanceof GetPartnerOverview => new PartnerOverview(
                $this->partners->findAll(),
                $this->invitations->findOpen(),
                $this->local->baseUrl(),
                $this->local->hasConfiguredBaseUrl(),
                $this->requests->countPendingIncoming(),
            ),
            $query instanceof GetPartner => $this->partnerDetail($query->partnerId),
            $query instanceof PreviewPartnerInvitation => $this->preview($query->link),
            $query instanceof ListPartnerRequests => $this->views($this->requests->findRecent($query->limit)),
            $query instanceof GetPartnerRequest => $this->requestDetail($query->requestId),
            $query instanceof BrowsePartnerPool => $this->pool($query),
            $query instanceof GetPartnerTechnicianOptions => $this->technicianOptions($query->partnerId),
            $query instanceof ListPartnerRequestsForRental => new RentalPartnerRequests(
                $this->views($this->requests->findByRental($query->rentalId)),
                array_values(array_filter($this->partners->findAll(), static fn (PartnerInstance $p) => $p->isActive())),
            ),
            default => throw new \InvalidArgumentException('Unsupported query ' . $query::class),
        };
    }

    private function partnerDetail(string $id): PartnerDetail
    {
        $partner = $this->partner($id);
        $requests = array_values(array_filter($this->requests->findRecent(500), static fn (PartnerRequest $r) => $r->partnerId === $partner->id));
        $customerLabel = null;
        if ($partner->customerId !== null && $this->customers !== null) {
            $customer = $this->customers->findById($partner->customerId);
            $customerLabel = $customer !== null ? $customer->customerNumber . ' · ' . $customer->displayName() : null;
        }

        return new PartnerDetail($partner, $requests, $customerLabel);
    }

    /** @return array{name: string, baseUrl: string, link: string, profile: PartnerProfile} */
    private function preview(string $link): array
    {
        ['baseUrl' => $baseUrl, 'code' => $code] = PartnerInvitationLink::parse($link);
        if ($baseUrl === PartnerInvitationLink::normalizeBaseUrl($this->local->baseUrl())) {
            throw new ValidationException(['link' => 'Das ist ein Einladungslink dieser Instanz. Er muss bei der anderen Instanz eingegeben werden.']);
        }
        try {
            $response = $this->client->request('GET', $baseUrl, PartnerInvitationLink::PATH . $code);
        } catch (FederationException $e) {
            throw new ValidationException(['link' => 'Die Einladung konnte nicht abgerufen werden: ' . $e->getMessage()]);
        }

        return [
            'name' => FederationInput::text($response['name'] ?? null, 255) ?? $baseUrl,
            'baseUrl' => $baseUrl,
            'link' => PartnerInvitationLink::build($baseUrl, $code),
            'profile' => PartnerProfile::fromArray(is_array($response['profile'] ?? null) ? $response['profile'] : []),
        ];
    }

    private function requestDetail(string $id): PartnerRequestDetail
    {
        $request = $this->requests->findById($id) ?? throw new NotFoundException('Die Anfrage wurde nicht gefunden.');
        $partner = $this->partner($request->partnerId);
        $pending = $request->isIncoming() && $request->status === PartnerRequestStatus::Pending;

        return new PartnerRequestDetail(
            $request,
            $partner,
            $pending && $request->kind === PartnerRequestKind::Material ? $this->acceptor->shortages($request) : [],
            $pending && $request->kind === PartnerRequestKind::Technician ? $this->gateway->listTechnicians() : [],
            $pending && $request->kind === PartnerRequestKind::Technician ? $this->gateway->listTrades() : [],
        );
    }

    private function pool(BrowsePartnerPool $query): PartnerPool
    {
        $partner = $this->activePartner($query->partnerId);
        [$from, $to] = FederationInput::period($query->from, $query->to);
        $params = http_build_query(array_filter([
            'q' => $query->search,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'page' => max(1, $query->page),
        ], static fn ($v) => $v !== null && $v !== ''));

        try {
            $response = $this->call($partner, '/articles?' . $params);
        } catch (FederationException $e) {
            return new PartnerPool($partner, [], 0, 1, 25, $from->format('Y-m-d'), $to->format('Y-m-d'), $partner->discountReceived, $e->getMessage());
        }

        $items = [];
        foreach (is_array($response['data'] ?? null) ? $response['data'] : [] as $row) {
            if (!is_array($row) || !is_scalar($row['id'] ?? null) || !is_scalar($row['name'] ?? null)) {
                continue;
            }
            $items[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'manufacturer' => FederationInput::text($row['manufacturer'] ?? null, 255),
                'category' => FederationInput::text($row['category'] ?? null, 255),
                'daily_price' => is_numeric($row['daily_price'] ?? null) ? (float) $row['daily_price'] : null,
                'discounted_daily_price' => is_numeric($row['discounted_daily_price'] ?? null) ? (float) $row['discounted_daily_price'] : null,
                'total' => (int) ($row['total'] ?? 0),
                'available' => (int) ($row['available'] ?? 0),
            ];
        }
        $meta = is_array($response['meta'] ?? null) ? $response['meta'] : [];

        return new PartnerPool(
            $partner,
            $items,
            (int) ($meta['total'] ?? count($items)),
            (int) ($meta['page'] ?? 1),
            max(1, (int) ($meta['page_size'] ?? 25)),
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            is_numeric($meta['discount_percent'] ?? null) ? (float) $meta['discount_percent'] : $partner->discountReceived,
            null,
        );
    }

    private function technicianOptions(string $partnerId): PartnerTechnicianOptions
    {
        $partner = $this->activePartner($partnerId);
        try {
            $response = $this->call($partner, '/technicians');
        } catch (FederationException $e) {
            return new PartnerTechnicianOptions($partner, false, [], [], $e->getMessage());
        }
        $technicians = [];
        foreach (is_array($response['technicians'] ?? null) ? $response['technicians'] : [] as $row) {
            if (is_array($row) && is_scalar($row['id'] ?? null) && is_scalar($row['name'] ?? null)) {
                $technicians[] = ['id' => (string) $row['id'], 'name' => (string) $row['name']];
            }
        }

        return new PartnerTechnicianOptions(
            $partner,
            (bool) ($response['allowed'] ?? false),
            $technicians,
            \App\Application\Federation\PartnerRequestPayload::stringList($response['trades'] ?? null, 100),
            null,
        );
    }

    /** @return array<string, mixed> */
    private function call(PartnerInstance $partner, string $path): array
    {
        if ($partner->outboundToken === null) {
            throw new FederationException('Kein Zugangstoken hinterlegt.');
        }

        return $this->client->request('GET', $partner->baseUrl, '/api/federation/v1' . $path, $partner->outboundToken);
    }

    /**
     * @param PartnerRequest[] $requests
     * @return PartnerRequestView[]
     */
    private function views(array $requests): array
    {
        $names = [];
        foreach ($this->partners->findAll() as $partner) {
            $names[$partner->id] = $partner->name;
        }

        return array_map(static fn (PartnerRequest $r) => new PartnerRequestView($r, $names[$r->partnerId] ?? '–'), $requests);
    }

    private function partner(string $id): PartnerInstance
    {
        return $this->partners->findById($id) ?? throw new NotFoundException('Die Partner-Instanz wurde nicht gefunden.');
    }

    private function activePartner(string $id): PartnerInstance
    {
        $partner = $this->partner($id);
        if (!$partner->isActive()) {
            throw new NotFoundException('Die Partnerschaft ist nicht aktiv.');
        }

        return $partner;
    }
}
