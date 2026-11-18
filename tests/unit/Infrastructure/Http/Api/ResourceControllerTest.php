<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Api;

use App\Application\Command\CommandBus;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Manufacturer\Command\UpdateManufacturer;
use App\Application\Query\QueryBus;
use App\Domain\Api\ApiToken;
use App\Domain\Manufacturer\Manufacturer;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Controllers\Api\SimpleResourceApiController;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * Controller-level behaviour shared by all /api/v1 resources (via
 * SimpleResourceApiController): list filtering/sorting/paging and PATCH
 * semantics (omitted fields keep their value).
 */
final class ResourceControllerTest extends Unit
{
    /** @var list<CommandInterface> */
    private array $dispatched = [];

    public function testListFiltersSortsAndPages(): void
    {
        $controller = $this->controller();

        $body = $controller->index($this->request(query: ['q' => 'S', 'sort' => '-name']))->body();
        $this->assertSame(['Shure', 'Sennheiser', 'Bose'], array_column($body['data'], 'name'), 'case-insensitive over name and prefix');

        $body = $controller->index($this->request(query: ['q' => 'enn']))->body();
        $this->assertSame(['Sennheiser'], array_column($body['data'], 'name'));
        $this->assertSame(1, $body['meta']['total']);

        $body = $controller->index($this->request(query: ['page' => '2', 'page_size' => '1', 'sort' => 'name']))->body();
        $this->assertSame(['Sennheiser'], array_column($body['data'], 'name'));
        $this->assertSame(3, $body['meta']['total_pages']);

        $body = $controller->index($this->request(query: ['updated_since' => '2026-06-01T00:00:00+00:00']))->body();
        $this->assertSame(['Shure'], array_column($body['data'], 'name'));
    }

    public function testUnknownSortFieldIsABadRequest(): void
    {
        $this->expectException(ApiException::class);
        $this->controller()->index($this->request(query: ['sort' => 'nope']));
    }

    public function testShowUnknownIdIs404(): void
    {
        try {
            $this->controller()->show($this->request(params: ['id' => 'missing']));
            $this->fail('ApiException expected');
        } catch (ApiException $e) {
            $this->assertSame(404, $e->status);
        }
    }

    public function testPatchKeepsOmittedFields(): void
    {
        $this->controller()->update($this->request(params: ['id' => 'm2'], body: ['name' => 'Sennheiser GmbH']));

        $command = $this->dispatched[0];
        $this->assertInstanceOf(UpdateManufacturer::class, $command);
        $this->assertSame('m2', $command->id);
        $this->assertSame('Sennheiser GmbH', $command->name);
        $this->assertSame('SEN', $command->prefix, 'prefix was not sent and keeps its value');
    }

    private function controller(): SimpleResourceApiController
    {
        $manufacturers = [
            new Manufacturer('m1', 'Shure', 'SHU', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-07-01T00:00:00+00:00')),
            new Manufacturer('m2', 'Sennheiser', 'SEN', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01T00:00:00+00:00')),
            new Manufacturer('m3', 'Bose', 'BOS', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01T00:00:00+00:00')),
        ];
        $commands = new CommandBus();
        $dispatched = &$this->dispatched;
        $commands->register(UpdateManufacturer::class, new class ($dispatched) implements CommandHandlerInterface {
            /** @param list<CommandInterface> $dispatched */
            public function __construct(private array &$dispatched)
            {
            }

            public function handle(CommandInterface $command): mixed
            {
                $this->dispatched[] = $command;

                return null;
            }
        });

        return new SimpleResourceApiController(
            new QueryBus(),
            $commands,
            'Hersteller',
            static fn () => $manufacturers,
            null,
            null,
            UpdateManufacturer::class,
            null,
        );
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     */
    private function request(array $query = [], array $params = [], array $body = []): ApiRequest
    {
        return new ApiRequest('GET', '/api/v1/manufacturers', $params, $query, $body, [], new ApiToken('t', 'T', 'h', 'abcd', null, true, null, new DateTimeImmutable()));
    }
}
