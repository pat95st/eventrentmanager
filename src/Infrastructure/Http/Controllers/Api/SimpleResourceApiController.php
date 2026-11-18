<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Command\CommandBus;
use App\Application\Command\CommandInterface;
use App\Application\Query\QueryBus;
use App\Application\Common\PaginatedResult;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\CommandFactory;
use App\Infrastructure\Http\Api\Normalizer;
use Closure;

/**
 * Generic CRUD controller for flat master data whose entity properties map
 * 1:1 onto the create/update commands (categories, manufacturers, suppliers,
 * warehouses, vehicles, venues, labor time articles). One instance per
 * resource, configured in bootstrap.php.
 */
final class SimpleResourceApiController extends ApiController
{
    /**
     * @param Closure(): iterable<object> $list all entities
     * @param Closure(string): ?object|null $find single entity (null result = 404); defaults to searching $list
     * @param class-string<CommandInterface>|null $createCommand
     * @param class-string<CommandInterface>|null $updateCommand
     * @param class-string<CommandInterface>|null $deleteCommand constructor takes the id
     * @param list<string> $exclude camelCase properties not exposed
     */
    public function __construct(
        QueryBus $queries,
        CommandBus $commands,
        private readonly string $label,
        private readonly Closure $list,
        private readonly ?Closure $find = null,
        private readonly ?string $createCommand = null,
        private readonly ?string $updateCommand = null,
        private readonly ?string $deleteCommand = null,
        private readonly array $exclude = [],
    ) {
        parent::__construct($queries, $commands);
    }

    public function index(ApiRequest $request): ApiResponse
    {
        $items = [];
        foreach (($this->list)() as $entity) {
            $items[] = Normalizer::normalize($entity, $this->exclude);
        }

        return $this->listResponse($request, $items);
    }

    public function show(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data(Normalizer::normalize($this->load($request->param('id')), $this->exclude));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $id = $this->run((string) $this->createCommand, $request);

        return $this->created(Normalizer::normalize($this->load((string) $id), $this->exclude));
    }

    public function update(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $this->run((string) $this->updateCommand, $request, ['id' => $id], CommandFactory::props($this->load($id)));

        return ApiResponse::data(Normalizer::normalize($this->load($id), $this->exclude));
    }

    public function destroy(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $this->load($id);
        $class = (string) $this->deleteCommand;
        $this->dispatch(new $class($id));

        return ApiResponse::noContent();
    }

    private function load(string $id): object
    {
        if ($this->find !== null) {
            $entity = ($this->find)($id);
        } else {
            $entity = null;
            foreach (($this->list)() as $candidate) {
                if (($candidate->id ?? null) === $id) {
                    $entity = $candidate;
                    break;
                }
            }
        }
        if ($entity === null) {
            throw ApiException::notFound($this->label . ' wurde nicht gefunden.');
        }

        return $entity;
    }

    /**
     * Unwraps a PaginatedResult or array result of a list query.
     *
     * @return iterable<object>
     */
    public static function items(mixed $result): iterable
    {
        return $result instanceof PaginatedResult ? $result->items : (is_iterable($result) ? $result : []);
    }
}
