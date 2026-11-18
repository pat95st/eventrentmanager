<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Command\CommandBus;
use App\Application\Customer\Command\CreateCustomer;
use App\Application\Customer\Command\DeleteCustomer;
use App\Application\Customer\Command\UpdateCustomer;
use App\Application\Customer\Query\GetCustomer;
use App\Application\Customer\Query\ListCustomers;
use App\Application\Query\QueryBus;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerContactRepositoryInterface;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\CommandFactory;
use App\Infrastructure\Http\Api\Normalizer;

/** /api/v1/customers — scopes customers:read / customers:write. */
final class CustomerApiController extends ApiController
{
    public function __construct(
        QueryBus $queries,
        CommandBus $commands,
        private readonly CustomerContactRepositoryInterface $contacts,
    ) {
        parent::__construct($queries, $commands);
    }

    public function index(ApiRequest $request): ApiResponse
    {
        $result = $this->ask(new ListCustomers());

        return $this->listResponse($request, array_map($this->summary(...), $result->items));
    }

    public function show(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->detail($request->param('id')));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $id = $this->run(CreateCustomer::class, $request);

        return $this->created($this->detail($id));
    }

    public function update(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $customer = $this->ask(new GetCustomer($id));
        $current = CommandFactory::props($customer) + ['contacts' => $this->contactRows($id)];
        $this->run(UpdateCustomer::class, $request, ['id' => $id], $current);

        return ApiResponse::data($this->detail($id));
    }

    public function destroy(ApiRequest $request): ApiResponse
    {
        $this->dispatch(new DeleteCustomer($request->param('id')));

        return ApiResponse::noContent();
    }

    /** @return array<string, mixed> */
    private function summary(Customer $customer): array
    {
        return Normalizer::normalize($customer) + ['display_name' => $customer->displayName()];
    }

    /** @return array<string, mixed> */
    private function detail(string $id): array
    {
        $customer = $this->ask(new GetCustomer($id));

        return $this->summary($customer) + ['contacts' => $this->contactRows($id)];
    }

    /** @return list<array<string, mixed>> */
    private function contactRows(string $customerId): array
    {
        return Normalizer::list($this->contacts->findByCustomerId($customerId), ['customerId']);
    }
}
