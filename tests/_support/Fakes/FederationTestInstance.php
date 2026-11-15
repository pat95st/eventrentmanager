<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Command\CommandInterface;
use App\Application\Federation\Command\PartnerCommandHandler;
use App\Application\Federation\IncomingRequestAcceptor;
use App\Application\Federation\InboundFederationService;
use App\Application\Federation\LocalInstanceInterface;
use App\Application\Federation\Query\PartnerQueryHandler;
use App\Application\Query\QueryInterface;
use App\Domain\Federation\PartnerProfile;

/** One in-memory installation taking part in a FakeFederationNetwork. */
final class FederationTestInstance implements LocalInstanceInterface
{
    public readonly InMemoryPartnerInstanceRepository $partners;
    public readonly InMemoryPartnerInvitationRepository $invitations;
    public readonly InMemoryPartnerRequestRepository $requests;
    public readonly FakePartnerRentalGateway $gateway;
    public readonly InboundFederationService $inbound;
    private readonly PartnerCommandHandler $commands;
    private readonly PartnerQueryHandler $queries;

    public function __construct(
        private readonly string $name,
        private readonly string $baseUrl,
        FakeFederationNetwork $network,
    ) {
        $this->partners = new InMemoryPartnerInstanceRepository();
        $this->invitations = new InMemoryPartnerInvitationRepository();
        $this->requests = new InMemoryPartnerRequestRepository();
        $this->gateway = new FakePartnerRentalGateway();
        $acceptor = new IncomingRequestAcceptor($this->requests, $this->partners, $this->gateway);
        $this->inbound = new InboundFederationService($this->partners, $this->invitations, $this->requests, $this->gateway, $acceptor, $this);
        $this->commands = new PartnerCommandHandler($this->partners, $this->invitations, $this->requests, $network, $this, $this->gateway, $acceptor);
        $this->queries = new PartnerQueryHandler($this->partners, $this->invitations, $this->requests, $network, $this, $this->gateway, $acceptor);
        $network->register($baseUrl, $this->inbound);
    }

    public function dispatch(CommandInterface $command): mixed
    {
        return $this->commands->handle($command);
    }

    public function ask(QueryInterface $query): mixed
    {
        return $this->queries->handle($query);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function hasConfiguredBaseUrl(): bool
    {
        return true;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function profile(): PartnerProfile
    {
        return new PartnerProfile('Hauptstraße 1', '12345', 'Musterstadt', 'DE', 'info@' . parse_url($this->baseUrl, PHP_URL_HOST));
    }
}
