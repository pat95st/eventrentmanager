<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Network;

use App\Application\Network\Command\DeleteNetwork;
use App\Application\Network\Command\NetworkSettingsHandler;
use App\Application\Network\Command\SaveNetwork;
use App\Application\Network\Command\SaveNetworkCategoryRange;
use App\Domain\Category\Category;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\ValidationException;
use App\Domain\Network\IpMode;
use App\Domain\Network\NetworkInterfaceAssignment;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryNetworkRepository;

final class NetworkSettingsHandlerTest extends Unit
{
    private InMemoryNetworkRepository $repository;
    private NetworkSettingsHandler $handler;

    protected function _before(): void
    {
        $this->repository = new InMemoryNetworkRepository();
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->method('findById')->willReturnCallback(static fn (string $id) => in_array($id, ['cat-light', 'cat-audio'], true)
            ? new Category($id, $id === 'cat-light' ? 'Licht' : 'Audio', null, new DateTimeImmutable(), new DateTimeImmutable())
            : null);
        $this->handler = new NetworkSettingsHandler($this->repository, $categories);
    }

    private function errorsOf(callable $action): array
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('ValidationException expected.');
    }

    public function testCreatesNetworkFromCidrNotation(): void
    {
        $id = $this->handler->handle(new SaveNetwork(null, ' Dante Primary ', 'dual', '10', '192.168.10.0/24', null, 'FD00:10::/64'));

        $network = $this->repository->findById($id);
        $this->assertSame('Dante Primary', $network?->name);
        $this->assertSame(IpMode::Dual, $network->mode);
        $this->assertSame(10, $network->vlanId);
        $this->assertSame(['192.168.10.0', 24], [$network->ipv4Network, $network->ipv4Prefix]);
        $this->assertSame(['fd00:10::', 64, 64], [$network->ipv6Network, $network->ipv6Prefix, $network->ipv6DefaultPrefix]);
    }

    public function testAcceptsDottedSubnetMaskAndDropsUnusedFamily(): void
    {
        $id = $this->handler->handle(new SaveNetwork(null, 'Licht', 'ipv4', '', '10.20.0.0', '255.255.0.0', 'fd00::', '/64'));

        $network = $this->repository->findById($id);
        $this->assertSame(16, $network?->ipv4Prefix);
        $this->assertNull($network->ipv6Network, 'IPv6 is not stored for an IPv4-only network.');
        $this->assertNull($network->vlanId);
    }

    public function testRejectsPublicRangesHostAddressesAndInvalidVlan(): void
    {
        $errors = $this->errorsOf(fn () => $this->handler->handle(new SaveNetwork(null, 'A', 'ipv4', null, '8.8.8.0/24')));
        $this->assertStringContainsString('private', $errors['ipv4Network']);

        $errors = $this->errorsOf(fn () => $this->handler->handle(new SaveNetwork(null, 'B', 'ipv4', null, '192.168.10.5', '24')));
        $this->assertStringContainsString('192.168.10.0/24', $errors['ipv4Network']);

        $errors = $this->errorsOf(fn () => $this->handler->handle(new SaveNetwork(null, 'C', 'ipv6', '4095', null, null, '2001:db8::', '64')));
        $this->assertArrayHasKey('vlanId', $errors);
        $this->assertArrayHasKey('ipv6Network', $errors);
    }

    public function testCategoryRangesMustLieInsideTheNetworkAndNotOverlap(): void
    {
        $id = $this->handler->handle(new SaveNetwork(null, 'LAN', 'ipv4', null, '192.168.10.0/24'));

        $this->handler->handle(new SaveNetworkCategoryRange($id, 'cat-light', '192.168.10.100', '192.168.10.149'));
        $this->assertSame('192.168.10.100 – 192.168.10.149', $this->repository->findCategoryRange('cat-light')?->label(4));

        $errors = $this->errorsOf(fn () => $this->handler->handle(new SaveNetworkCategoryRange($id, 'cat-audio', '192.168.10.140', '192.168.10.160')));
        $this->assertStringContainsString('Licht', $errors['ipv4']);

        $errors = $this->errorsOf(fn () => $this->handler->handle(new SaveNetworkCategoryRange($id, 'cat-audio', '192.168.10.200', '192.168.10.255')));
        $this->assertArrayHasKey('ipv4', $errors, 'The broadcast address is not part of a range.');

        $errors = $this->errorsOf(fn () => $this->handler->handle(new SaveNetworkCategoryRange($id, 'cat-audio', '192.168.10.210', '192.168.10.200')));
        $this->assertArrayHasKey('ipv4', $errors);
    }

    public function testNetworkWithAssignedAddressesCannotBeDeletedOrShrunk(): void
    {
        $id = $this->handler->handle(new SaveNetwork(null, 'LAN', 'ipv4', null, '192.168.10.0/24'));
        $this->repository->replaceAssignments('inv-1', [new NetworkInterfaceAssignment('inv-1', 'main', 'Netzwerkschnittstelle', $id, '192.168.10.200')]);

        $errors = $this->errorsOf(fn () => $this->handler->handle(new SaveNetwork($id, 'LAN', 'ipv4', null, '192.168.10.0/25')));
        $this->assertStringContainsString('192.168.10.200', $errors['network']);

        $this->expectException(ConflictException::class);
        $this->handler->handle(new DeleteNetwork($id));
    }
}
