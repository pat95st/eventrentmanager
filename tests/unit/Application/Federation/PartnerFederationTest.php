<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use App\Application\Federation\Command\AcceptIncomingPartnerRequest;
use App\Application\Federation\Command\AcceptPartnerDiscount;
use App\Application\Federation\Command\AcceptPartnerInvitation;
use App\Application\Federation\Command\CancelOutgoingPartnerRequest;
use App\Application\Federation\Command\ConfirmPartner;
use App\Application\Federation\Command\CreatePartnerInvitation;
use App\Application\Federation\Command\DeclineIncomingPartnerRequest;
use App\Application\Federation\Command\DeclinePartner;
use App\Application\Federation\Command\EndPartnership;
use App\Application\Federation\Command\ProposePartnerDiscount;
use App\Application\Federation\Command\RefreshOutgoingPartnerRequest;
use App\Application\Federation\Command\RejectPartnerDiscount;
use App\Application\Federation\Command\SendPartnerMaterialRequest;
use App\Application\Federation\Command\SendPartnerTechnicianRequest;
use App\Application\Federation\Command\UpdatePartnerSettings;
use App\Application\Federation\Query\BrowsePartnerPool;
use App\Application\Federation\Query\GetPartnerTechnicianOptions;
use App\Application\Federation\Query\PreviewPartnerInvitation;
use App\Domain\Exception\ValidationException;
use App\Domain\Federation\DiscountProposalSource;
use App\Domain\Federation\PartnerInstance;
use App\Domain\Federation\PartnerRequestKind;
use App\Domain\Federation\PartnerRequestStatus;
use App\Domain\Federation\PartnerStatus;
use Codeception\Test\Unit;
use Tests\Support\Fakes\FakeFederationNetwork;
use Tests\Support\Fakes\FederationTestInstance;

/**
 * Two installations befriend each other via invitation link, agree on
 * discounts and exchange material and technician requests — end to end
 * through the federation protocol, without network or database.
 */
final class PartnerFederationTest extends Unit
{
    private FakeFederationNetwork $network;
    private FederationTestInstance $alpha; // inviting installation
    private FederationTestInstance $beta;  // installation entering the link

    protected function _before(): void
    {
        $this->network = new FakeFederationNetwork();
        $this->alpha = new FederationTestInstance('Alpha Veranstaltungstechnik', 'https://alpha.example', $this->network);
        $this->beta = new FederationTestInstance('Beta Events', 'https://beta.example', $this->network);
    }

    public function testInvitationLinkCanBePreviewedAndIsSingleUse(): void
    {
        $link = $this->invite();
        self::assertStringStartsWith('https://alpha.example/api/federation/v1/invitations/', $link);

        $preview = $this->beta->ask(new PreviewPartnerInvitation($link));
        self::assertSame('Alpha Veranstaltungstechnik', $preview['name']);
        self::assertSame('Musterstadt', $preview['profile']->city);

        $this->accept($link, symmetric: true, discount: '10');

        $other = new FederationTestInstance('Gamma', 'https://gamma.example', $this->network);
        $this->expectException(ValidationException::class);
        $other->ask(new PreviewPartnerInvitation($link));
    }

    public function testOwnInvitationLinkIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->alpha->dispatch(new AcceptPartnerInvitation($this->invite(), false, true, true, '5', '', ''));
    }

    public function testSymmetricDiscountIsAgreedOnConfirmation(): void
    {
        $this->accept($this->invite(), symmetric: true, discount: '10');

        $betaPartner = $this->beta->partners->only();
        self::assertSame(PartnerStatus::AwaitingPartner, $betaPartner->status);
        self::assertSame(DiscountProposalSource::Us, $betaPartner->proposalFrom);

        $alphaPartner = $this->alpha->partners->only();
        self::assertSame(PartnerStatus::PendingConfirmation, $alphaPartner->status);
        self::assertSame('Beta Events', $alphaPartner->name);
        self::assertSame(10.0, $alphaPartner->proposedDiscountGranted);

        $this->alpha->dispatch(new ConfirmPartner($alphaPartner->id, true, true, true, '10', '', ''));

        foreach ([$this->alpha->partners->only(), $this->beta->partners->only()] as $partner) {
            self::assertSame(PartnerStatus::Active, $partner->status);
            self::assertSame(10.0, $partner->discountGranted);
            self::assertSame(10.0, $partner->discountReceived);
            self::assertFalse($partner->hasPendingProposal());
        }
        self::assertTrue($this->alpha->partners->only()->autoAcceptRequests);
    }

    public function testDifferentDiscountsNeedConsentOfBothSides(): void
    {
        // Beta proposes: Beta grants 5 %, receives 15 %.
        $this->accept($this->invite(), symmetric: false, granted: '5', received: '15');
        $alphaPartner = $this->alpha->partners->only();
        self::assertSame(15.0, $alphaPartner->proposedDiscountGranted); // Alpha's point of view
        self::assertSame(5.0, $alphaPartner->proposedDiscountReceived);

        // Alpha confirms the partnership but counters with 10/10.
        $this->alpha->dispatch(new ConfirmPartner($alphaPartner->id, false, true, true, '10', '', ''));
        $alphaPartner = $this->alpha->partners->only();
        $betaPartner = $this->beta->partners->only();
        self::assertSame(PartnerStatus::Active, $betaPartner->status);
        self::assertSame(0.0, $betaPartner->discountGranted, 'nothing agreed yet');
        self::assertSame(DiscountProposalSource::Them, $betaPartner->proposalFrom);
        self::assertSame(DiscountProposalSource::Us, $alphaPartner->proposalFrom);

        // Beta accepts the counter-proposal.
        $this->beta->dispatch(new AcceptPartnerDiscount($betaPartner->id));
        self::assertSame(10.0, $this->beta->partners->only()->discountGranted);
        self::assertSame(10.0, $this->alpha->partners->only()->discountReceived);
        self::assertFalse($this->alpha->partners->only()->hasPendingProposal());

        // Later Alpha proposes asymmetric values, Beta rejects them.
        $this->alpha->dispatch(new ProposePartnerDiscount($alphaPartner->id, false, '', '20', '12'));
        self::assertSame(20.0, $this->beta->partners->only()->proposedDiscountReceived);
        self::assertSame(12.0, $this->beta->partners->only()->proposedDiscountGranted);
        $this->beta->dispatch(new RejectPartnerDiscount($betaPartner->id));
        self::assertFalse($this->alpha->partners->only()->hasPendingProposal());
        self::assertSame(10.0, $this->alpha->partners->only()->discountGranted);
    }

    public function testDeclinedPartnershipRevokesAccess(): void
    {
        $this->accept($this->invite(), symmetric: true, discount: '0');
        $this->alpha->dispatch(new DeclinePartner($this->alpha->partners->only()->id));

        self::assertSame(PartnerStatus::Declined, $this->alpha->partners->only()->status);
        self::assertSame(PartnerStatus::Declined, $this->beta->partners->only()->status);
    }

    public function testPoolShowsDiscountedPricesAndAvailability(): void
    {
        [$alphaPartner, $betaPartner] = $this->befriend(autoAccept: false, discount: '10');
        $this->alpha->gateway->addArticle('a-1', 'Lautsprecher', 40.0, 4);
        $this->alpha->gateway->addArticle('a-2', 'Mischpult', 80.0, 1);

        $pool = $this->beta->ask(new BrowsePartnerPool($betaPartner->id, 'Laut', '2026-12-01', '2026-12-03'));
        self::assertNull($pool->error);
        self::assertCount(1, $pool->items);
        self::assertSame(36.0, $pool->items[0]['discounted_daily_price']);
        self::assertSame(4, $pool->items[0]['available']);
        self::assertSame(10.0, $pool->discountPercent);
    }

    public function testMaterialRequestIsAcceptedAutomaticallyWhenConfigured(): void
    {
        [$alphaPartner, $betaPartner] = $this->befriend(autoAccept: true, discount: '10');
        $this->alpha->gateway->addArticle('a-1', 'Lautsprecher', 40.0, 4);

        $requestId = $this->beta->dispatch(new SendPartnerMaterialRequest($betaPartner->id, null, 'Stadtfest', '2026-12-01', '2026-12-03', 'Marktplatz', '', ['a-1' => '3'], 'Erika Mustermann'));

        $outgoing = $this->beta->requests->findById($requestId);
        self::assertSame(PartnerRequestStatus::Accepted, $outgoing->status);
        self::assertSame('Lautsprecher', $outgoing->items[0]->name);
        self::assertSame(10.0, $outgoing->discountPercent);

        $incoming = $this->alpha->requests->only();
        self::assertSame(PartnerRequestStatus::Accepted, $incoming->status);
        self::assertNotNull($incoming->rentalId);
        $rental = $this->alpha->gateway->rentals[$incoming->rentalId];
        self::assertSame('Stadtfest (Beta Events)', $rental['name']);
        self::assertSame(['a-1' => 3], $rental['items']);
        self::assertSame($this->alpha->partners->only()->customerId, $rental['customer']);
        self::assertSame(1, $this->alpha->gateway->availableQuantity('a-1', $rental['from'], $rental['to']));
    }

    public function testAutomaticAcceptanceFallsBackToManualWhenStockIsShort(): void
    {
        [$alphaPartner, $betaPartner] = $this->befriend(autoAccept: true, discount: '0');
        $this->alpha->gateway->addArticle('a-1', 'Lautsprecher', 40.0, 2);

        $requestId = $this->beta->dispatch(new SendPartnerMaterialRequest($betaPartner->id, null, 'Stadtfest', '2026-12-01', '2026-12-03', '', '', ['a-1' => '3']));

        self::assertSame(PartnerRequestStatus::Pending, $this->beta->requests->findById($requestId)->status);
        $incoming = $this->alpha->requests->only();
        self::assertSame(PartnerRequestStatus::Pending, $incoming->status);
        self::assertStringContainsString('Automatische Zusage nicht möglich', (string) $incoming->syncError);
        self::assertSame([], $this->alpha->gateway->rentals);

        // Manually declining informs the requesting installation.
        $this->alpha->dispatch(new DeclineIncomingPartnerRequest($incoming->id, 'Leider ausgebucht'));
        $outgoing = $this->beta->requests->findById($requestId);
        self::assertSame(PartnerRequestStatus::Declined, $outgoing->status);
        self::assertSame('Leider ausgebucht', $outgoing->responseMessage);
    }

    public function testManualAcceptanceAndCancellation(): void
    {
        [$alphaPartner, $betaPartner] = $this->befriend(autoAccept: false, discount: '5');
        $this->alpha->gateway->addArticle('a-1', 'Lautsprecher', 40.0, 4);
        $requestId = $this->beta->dispatch(new SendPartnerMaterialRequest($betaPartner->id, null, 'Hochzeit', '2026-12-05', '2026-12-05', '', 'Bitte mit Stativen', ['a-1' => '2', 'unused' => '']));
        self::assertSame(PartnerRequestStatus::Pending, $this->alpha->requests->only()->status);

        $this->alpha->dispatch(new AcceptIncomingPartnerRequest($this->alpha->requests->only()->id, [], [], 'Gerne!'));
        $outgoing = $this->beta->requests->findById($requestId);
        self::assertSame(PartnerRequestStatus::Accepted, $outgoing->status);
        self::assertSame('Gerne!', $outgoing->responseMessage);

        $this->beta->dispatch(new CancelOutgoingPartnerRequest($requestId));
        self::assertSame(PartnerRequestStatus::Cancelled, $this->beta->requests->findById($requestId)->status);
        $incoming = $this->alpha->requests->only();
        self::assertSame(PartnerRequestStatus::Cancelled, $incoming->status);
        self::assertNotNull($incoming->syncError, 'provider is told to check the created event');
    }

    public function testTechnicianRequestRoundTrip(): void
    {
        [$alphaPartner, $betaPartner] = $this->befriend(autoAccept: true, discount: '0');
        $this->alpha->gateway->technicians = [['id' => 'u-1', 'name' => 'Max Licht'], ['id' => 'u-2', 'name' => 'Toni Ton']];
        $this->alpha->gateway->trades = [['id' => 't-1', 'name' => 'Licht'], ['id' => 't-2', 'name' => 'Ton']];

        $options = $this->beta->ask(new GetPartnerTechnicianOptions($betaPartner->id));
        self::assertTrue($options->allowed);
        self::assertSame(['Licht', 'Ton'], $options->trades);

        $requestId = $this->beta->dispatch(new SendPartnerTechnicianRequest($betaPartner->id, null, 'Gala', '2026-12-10', '2026-12-11', 'Stadthalle', '', ['Licht'], '1', 'u-1', 'Max Licht'));
        $incoming = $this->alpha->requests->only();
        self::assertSame(PartnerRequestKind::Technician, $incoming->kind);
        self::assertSame(PartnerRequestStatus::Pending, $incoming->status, 'technician requests are never auto-accepted');
        self::assertSame('Max Licht', $incoming->preferredTechnician['name']);

        $this->alpha->dispatch(new AcceptIncomingPartnerRequest($incoming->id, ['u-1'], ['t-1']));
        $outgoing = $this->beta->requests->findById($requestId);
        self::assertSame(PartnerRequestStatus::Accepted, $outgoing->status);
        self::assertSame([['name' => 'Max Licht', 'status' => 'Angefragt']], $outgoing->technicians);

        $this->beta->dispatch(new RefreshOutgoingPartnerRequest($requestId));
        self::assertSame('Max Licht', $this->beta->requests->findById($requestId)->technicians[0]['name']);
    }

    public function testTechnicianRequestsCanBeDisabled(): void
    {
        [$alphaPartner, $betaPartner] = $this->befriend(autoAccept: false, discount: '0');
        $this->alpha->dispatch(new UpdatePartnerSettings($alphaPartner->id, false, false));

        self::assertFalse($this->beta->ask(new GetPartnerTechnicianOptions($betaPartner->id))->allowed);
        $this->expectException(ValidationException::class);
        $this->beta->dispatch(new SendPartnerTechnicianRequest($betaPartner->id, null, 'Gala', '2026-12-10', '2026-12-11', '', '', [], '1', ''));
    }

    public function testEndedPartnershipBlocksFurtherRequests(): void
    {
        [$alphaPartner, $betaPartner] = $this->befriend(autoAccept: false, discount: '0');
        $this->alpha->gateway->addArticle('a-1', 'Lautsprecher', 40.0, 4);
        $this->alpha->dispatch(new EndPartnership($alphaPartner->id));

        self::assertSame(PartnerStatus::Ended, $this->beta->partners->only()->status);
        self::assertSame(PartnerStatus::Ended, $this->alpha->partners->only()->status);
        $this->expectException(ValidationException::class);
        $this->beta->dispatch(new SendPartnerMaterialRequest($betaPartner->id, null, 'X', '2026-12-01', '2026-12-01', '', '', ['a-1' => '1']));
    }

    public function testUnreachablePartnerLeavesStateUnchanged(): void
    {
        [$alphaPartner, $betaPartner] = $this->befriend(autoAccept: false, discount: '10');
        $this->network->offline[] = 'https://alpha.example';

        try {
            $this->beta->dispatch(new ProposePartnerDiscount($betaPartner->id, true, '20', '', ''));
            self::fail('Expected a validation error.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('nicht erreichbar', implode(' ', $e->errors()));
        }
        $partner = $this->beta->partners->only();
        self::assertFalse($partner->hasPendingProposal());
        self::assertNotNull($partner->lastError);
    }

    private function invite(): string
    {
        return $this->alpha->dispatch(new CreatePartnerInvitation('für Beta'))['link'];
    }

    private function accept(string $link, bool $symmetric, string $discount = '', string $granted = '', string $received = ''): void
    {
        $this->beta->dispatch(new AcceptPartnerInvitation($link, false, true, $symmetric, $discount, $granted, $received));
    }

    /** @return array{0: PartnerInstance, 1: PartnerInstance} [alpha's partner record, beta's partner record] */
    private function befriend(bool $autoAccept, string $discount): array
    {
        $this->accept($this->invite(), symmetric: true, discount: $discount);
        $this->alpha->dispatch(new ConfirmPartner($this->alpha->partners->only()->id, $autoAccept, true, true, $discount, '', ''));

        return [$this->alpha->partners->only(), $this->beta->partners->only()];
    }
}
