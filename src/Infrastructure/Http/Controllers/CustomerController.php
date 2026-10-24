<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Customer\Command\CreateCustomer;
use App\Application\Customer\Command\DeleteCustomer;
use App\Application\Customer\Command\UpdateCustomer;
use App\Application\Customer\Query\GetCustomer;
use App\Application\Customer\Query\ListCustomers;
use App\Application\Messaging\Command\SendAdHocMessageToCustomer;
use App\Application\Messaging\Query\GetMessagingSettings;
use App\Application\Query\QueryBus;
use App\Domain\Customer\Customer;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettings;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class CustomerController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListCustomers($search, $page, $pageSize));

        $viewData = [
            'customers' => $result->items,
            'search' => $search ?? '',
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/kunden',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('customers/_table', $viewData);

            return;
        }

        echo View::page('customers/index', $viewData, activeNav: 'customers', title: t('customers.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateCustomer(
            type: $request->input('type', '') ?? '',
            companyName: $this->nullIfEmpty($request->input('company_name')),
            firstName: $request->input('first_name', '') ?? '',
            lastName: $request->input('last_name', '') ?? '',
            street: $request->input('street', '') ?? '',
            addressLine2: $this->nullIfEmpty($request->input('address_line2')),
            postalCode: $request->input('postal_code', '') ?? '',
            city: $request->input('city', '') ?? '',
            phone: $this->nullIfEmpty($request->input('phone')),
            email: $this->nullIfEmpty($request->input('email')),
            telegramChatId: $this->nullIfEmpty($request->input('telegram_chat_id')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('customers.flash.created'));
            Response::redirect('/kunden');
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $customer = $this->queries->ask(new GetCustomer($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($customer, [], [], $customer->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateCustomer(
            id: $id,
            type: $request->input('type', '') ?? '',
            companyName: $this->nullIfEmpty($request->input('company_name')),
            firstName: $request->input('first_name', '') ?? '',
            lastName: $request->input('last_name', '') ?? '',
            street: $request->input('street', '') ?? '',
            addressLine2: $this->nullIfEmpty($request->input('address_line2')),
            postalCode: $request->input('postal_code', '') ?? '',
            city: $request->input('city', '') ?? '',
            phone: $this->nullIfEmpty($request->input('phone')),
            email: $this->nullIfEmpty($request->input('email')),
            telegramChatId: $this->nullIfEmpty($request->input('telegram_chat_id')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('customers.flash.updated'));
            Response::redirect('/kunden');
        } catch (ValidationException $e) {
            $customer = $this->queries->ask(new GetCustomer($id));
            $this->renderForm($customer, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteCustomer($id));
            Flash::success(t('customers.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/kunden');
    }

    /** Verschickt eine freie Einzel-Nachricht an den Kunden über einen der drei Messenger (siehe SendAdHocMessageToCustomerHandler). */
    public function sendMessage(Request $request): void
    {
        $id = $request->param('id');
        $channel = MessagingChannel::tryFrom($request->input('channel', '') ?? '');

        if ($channel === null) {
            Flash::error(t('customers.send_message.flash.invalid_channel'));
            Response::redirect('/kunden/' . $id . '/bearbeiten');

            return;
        }

        try {
            $this->commands->dispatch(new SendAdHocMessageToCustomer(
                customerId: $id,
                channel: $channel,
                text: $request->input('message_text', '') ?? '',
            ));
            Flash::success(t('customers.send_message.flash.sent'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }

        Response::redirect('/kunden/' . $id . '/bearbeiten');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?Customer $customer, array $errors, array $old, ?string $editingId = null): void
    {
        $messagingSettings = $customer !== null ? $this->queries->ask(new GetMessagingSettings()) : null;
        assert($messagingSettings === null || $messagingSettings instanceof MessagingSettings);

        echo View::page('customers/form', [
            'customer' => $customer,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
            'messagingSettings' => $messagingSettings,
        ], activeNav: 'customers', title: $customer !== null ? t('customers.edit') : t('customers.new'));
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateCustomer|UpdateCustomer $command): array
    {
        return [
            'type' => $command->type,
            'company_name' => $command->companyName,
            'first_name' => $command->firstName,
            'last_name' => $command->lastName,
            'street' => $command->street,
            'address_line2' => $command->addressLine2,
            'postal_code' => $command->postalCode,
            'city' => $command->city,
            'phone' => $command->phone,
            'email' => $command->email,
            'telegram_chat_id' => $command->telegramChatId,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
