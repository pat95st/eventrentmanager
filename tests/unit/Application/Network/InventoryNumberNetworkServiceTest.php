<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Network;

use App\Application\Network\InventoryNumberNetworkService;
use App\Application\Network\NetworkInterfaceResolver;
use App\Domain\Article\Article;
use App\Domain\Category\Category;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\DigitalStagebox\ArticleDigitalStagebox;
use App\Domain\DigitalStagebox\DigitalProtocol;
use App\Domain\Exception\ValidationException;
use App\Domain\MixingConsole\ArticleMixingConsole;
use App\Domain\MixingConsole\MixingConsoleExpansionCard;
use App\Domain\MixingConsole\MixingConsoleInterface;
use App\Domain\MixingConsole\MixingConsoleType;
use App\Domain\Network\ArticleNetworkSettings;
use App\Domain\Network\IpMode;
use App\Domain\Network\Network;
use App\Domain\Network\NetworkCategoryRange;
use App\Domain\Network\NetworkInterfaceAssignment;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryDigitalStageboxRepository;
use Tests\Support\Fakes\InMemoryMixingConsoleRepository;
use Tests\Support\Fakes\InMemoryNetworkRepository;

final class InventoryNumberNetworkServiceTest extends Unit
{
    private InMemoryNetworkRepository $networks;
    private InMemoryDigitalStageboxRepository $stageboxes;
    private InMemoryMixingConsoleRepository $consoles;
    private InventoryNumberNetworkService $service;

    protected function _before(): void
    {
        $this->networks = new InMemoryNetworkRepository();
        $this->networks->save(new Network('lan', 'LAN', IpMode::Ipv4, 10, '192.168.10.0', 24));
        $this->networks->save(new Network('dante', 'Dante', IpMode::Dual, 20, '172.16.20.0', 24, 'fd00:20::', 64, 64));
        $this->stageboxes = new InMemoryDigitalStageboxRepository();
        $this->stageboxes->saveProtocol(new DigitalProtocol('p-dante', 'Dante', 10, true));
        $this->stageboxes->saveProtocol(new DigitalProtocol('p-madi', 'MADI', 20, false));
        $this->consoles = new InMemoryMixingConsoleRepository();

        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->method('findCableCategoryId')->willReturn('cat-cable');
        $categories->method('findById')->willReturnCallback(static fn (string $id) => match ($id) {
            'cat-sub' => new Category('cat-sub', 'Moving Heads', 'cat-light', new DateTimeImmutable(), new DateTimeImmutable()),
            'cat-light' => new Category('cat-light', 'Licht', null, new DateTimeImmutable(), new DateTimeImmutable()),
            default => null,
        });

        $this->service = new InventoryNumberNetworkService(
            $this->networks,
            new NetworkInterfaceResolver($this->networks, $categories, $this->stageboxes, $this->consoles),
        );
    }

    private static function article(string $id, ?string $categoryId = null): Article
    {
        $now = new DateTimeImmutable();

        return new Article(
            id: $id,
            name: 'Gerät',
            manufacturerId: 'man-1',
            prefix: 'GER',
            categoryId: $categoryId,
            description: null,
            weightValue: null,
            weightUnit: null,
            dimensions: null,
            is19Inch: false,
            heightUnits: null,
            installDepthCm: null,
            purchasePrice: null,
            requiresElectricalInspection: false,
            protectionClass: null,
            hasFirmware: false,
            isBillable: true,
            isActive: true,
            dailyRentalPrice: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function testSlotsCoverNetworkInterfaceAndIpBasedProtocolsOnly(): void
    {
        $this->networks->saveArticleSettings(new ArticleNetworkSettings('console', true));
        $this->consoles->save(new ArticleMixingConsole(
            'console',
            MixingConsoleType::Digital,
            interfaces: [new MixingConsoleInterface('p-dante', 2), new MixingConsoleInterface('p-madi', 1)],
            cards: [new MixingConsoleExpansionCard('Dante-Karte', 'p-dante', 64, 64), new MixingConsoleExpansionCard('MADI-Karte', 'p-madi')],
        ));
        $this->stageboxes->saveArticleStagebox(new ArticleDigitalStagebox('stagebox', 'p-dante', 16));
        $this->networks->saveArticleSettings(new ArticleNetworkSettings('cable', true));

        $labels = array_map(static fn ($slot) => $slot->label, $this->service->resolver()->slots(self::article('console')));
        $this->assertSame(['Netzwerkschnittstelle', 'Dante-Schnittstelle 1', 'Dante-Schnittstelle 2', 'Erweiterungskarte „Dante-Karte“ (Dante)'], $labels);

        $this->assertSame(['stagebox'], array_map(static fn ($slot) => $slot->key, $this->service->resolver()->slots(self::article('stagebox'))));
        $this->assertSame([], $this->service->resolver()->slots(self::article('cable', 'cat-cable')), 'Cables have no network interface.');
    }

    public function testSuggestsNextAddressFromTheInheritedCategoryRange(): void
    {
        $this->networks->saveArticleSettings(new ArticleNetworkSettings('mh', true));
        $this->networks->saveCategoryRange(new NetworkCategoryRange('cat-light', 'lan', '192.168.10.100', '192.168.10.149'));
        $this->networks->replaceAssignments('inv-1', [new NetworkInterfaceAssignment('inv-1', 'main', 'Netzwerkschnittstelle', 'lan', '192.168.10.104')]);

        $form = $this->service->form(self::article('mh', 'cat-sub'));

        $this->assertCount(1, $form->rows);
        $this->assertSame('lan', $form->rows[0]->networkId);
        $this->assertSame('192.168.10.105', $form->rows[0]->ipv4);
        $this->assertTrue($form->rows[0]->suggested);
        $this->assertSame('172.16.20.1', $form->candidates['dante'][4][0], 'Other networks suggest from their whole subnet.');
    }

    public function testValidatesAndSavesAddresses(): void
    {
        $this->networks->saveArticleSettings(new ArticleNetworkSettings('dev', true));
        $this->stageboxes->saveArticleStagebox(new ArticleDigitalStagebox('dev', 'p-dante'));
        $article = self::article('dev');

        $assignments = $this->service->validate($article, null, [
            'main' => ['network_id' => 'lan', 'ipv4' => ' 192.168.10.20 ', 'mac' => '00-1d-c1-12-34-ab'],
            'stagebox' => ['network_id' => 'dante', 'ipv4' => '172.16.20.5', 'ipv6' => 'FD00:20::5'],
        ]);
        $this->service->save('inv-9', $assignments);

        $saved = $this->networks->findAssignmentsByInventoryNumber('inv-9');
        $this->assertSame(['192.168.10.20', '00:1D:C1:12:34:AB'], [$saved[0]->ipv4Address, $saved[0]->macAddress]);
        $this->assertSame(['172.16.20.5', 'fd00:20::5'], [$saved[1]->ipv4Address, $saved[1]->ipv6Address]);
        $this->assertSame('inv-9', $saved[1]->inventoryNumberId);

        // Re-saving the same inventory number keeps its own addresses valid.
        $this->assertCount(2, $this->service->validate($article, 'inv-9', [
            'main' => ['network_id' => 'lan', 'ipv4' => '192.168.10.20', 'mac' => '001DC11234AB'],
            'stagebox' => ['network_id' => 'dante', 'ipv4' => '172.16.20.5'],
        ]));
    }

    /** @return array<string, array{0: array<string, array<string, string>>, 1: string}> */
    public static function invalidInput(): array
    {
        return [
            'outside subnet' => [['main' => ['network_id' => 'lan', 'ipv4' => '192.168.11.20']], 'nicht im nutzbaren Bereich'],
            'broadcast' => [['main' => ['network_id' => 'lan', 'ipv4' => '192.168.10.255']], 'nicht im nutzbaren Bereich'],
            'used elsewhere' => [['main' => ['network_id' => 'lan', 'ipv4' => '192.168.10.50']], 'inv-1'],
            'mac used elsewhere' => [['main' => ['mac' => 'aa:bb:cc:dd:ee:ff']], 'inv-1'],
            'no ipv6 in network' => [['main' => ['network_id' => 'lan', 'ipv6' => 'fd00::1']], 'kein IPv6'],
            'address without network' => [['main' => ['ipv4' => '192.168.10.20']], 'Netzwerk'],
            'invalid mac' => [['main' => ['mac' => '12:34']], 'MAC'],
        ];
    }

    /** @dataProvider invalidInput */
    public function testRejectsInvalidAddresses(array $input, string $message): void
    {
        $this->networks->saveArticleSettings(new ArticleNetworkSettings('dev', true));
        $this->networks->replaceAssignments('inv-1', [new NetworkInterfaceAssignment('inv-1', 'main', 'Netzwerkschnittstelle', 'lan', '192.168.10.50', null, 'AA:BB:CC:DD:EE:FF')]);

        try {
            $this->service->validate(self::article('dev'), null, $input);
            $this->fail('ValidationException expected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, $e->errors()['network']);
        }
    }
}
