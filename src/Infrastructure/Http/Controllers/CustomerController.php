<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Customer\Command\CreateCustomer;
use App\Application\Customer\Command\DeleteCustomer;
use App\Application\Customer\Command\ImportCustomersFromCsv;
use App\Application\Customer\Command\UpdateCustomer;
use App\Application\Customer\Query\GetCustomer;
use App\Application\Customer\Query\ListCustomers;
use App\Application\Messaging\Command\SendAdHocMessageToCustomer;
use App\Application\Messaging\Query\GetMessagingSettings;
use App\Application\Query\QueryBus;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerType;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettings;
use App\Infrastructure\Csv\CustomerCsvTemplate;
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

    /**
     * JSON-Live-Suche für das Kunden-Auswahlfeld in Angebots-/Rechnungs-/
     * Auftragsbestätigungs-/Vermietungsformularen (siehe
     * App\Infrastructure\Http\renderLiveSearchField()). Liefert bewusst nur
     * eine kleine, feste Trefferzahl (keine Paginierung/Gesamtanzahl nötig,
     * da das Frontend bei jeder Eingabe neu anfragt) — `null` als Permission
     * im zugehörigen `$auth->guard()`-Aufruf (siehe bootstrap.php), da jeder
     * angemeldete Benutzer, der ein Dokument mit Kundenauswahl anlegen darf,
     * unabhängig von `Permission::Kundenverwaltung` suchen können muss
     * (exakt dieselbe Begründung wie beim bisherigen, ungefilterten
     * Kunden-Dropdown in diesen Formularen).
     */
    public function search(Request $request): void
    {
        $search = $request->query('q');
        $customers = $this->queries->ask(new ListCustomers($search, 1, 20))->items;

        Response::json([
            'items' => array_map(
                static fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'label' => $customer->displayName(),
                    // Kundennummer als Vorschlags-Zusatz (siehe live-search.js,
                    // item.sublabel) — hilft insbesondere bei mehreren
                    // gleichnamigen Kunden, den richtigen zu erkennen.
                    'sublabel' => $customer->customerNumber,
                ],
                $customers
            ),
        ]);
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
            $id = $this->commands->dispatch($command);

            // AJAX-Fall: das "+ Neuer Kunde"-Modal auf den Rechnungs-/
            // Angebots-/Auftragsbestätigungs-Formularen legt den Kunden per
            // fetch() an, statt die Seite zu verlassen (siehe
            // invoices/form.php & Geschwister-Views) — dafür wird hier
            // JSON mit der neu angelegten ID + dem Anzeigenamen
            // zurückgegeben, anstatt wie beim klassischen Formular-Post auf
            // /kunden umzuleiten.
            if (Pagination::isAjax($request)) {
                Response::json([
                    'id' => $id,
                    'displayName' => $this->displayNameFromCommand($command),
                ], 201);

                return;
            }

            Flash::success(t('customers.flash.created'));
            Response::redirect('/kunden');
        } catch (ValidationException $e) {
            if (Pagination::isAjax($request)) {
                Response::json(['errors' => $e->errors()], 422);

                return;
            }

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

    /** Zeigt das Formular für den CSV-Massenimport (siehe ImportCustomersFromCsvHandler). */
    public function importForm(Request $request): void
    {
        echo View::page('customers/import', [
            'errors' => [],
        ], activeNav: 'customers', title: t('customers.import.title'));
    }

    /** Liefert die feste CSV-Spaltenvorlage zum Herunterladen (siehe CustomerCsvTemplate). */
    public function importTemplate(Request $request): void
    {
        Response::download(CustomerCsvTemplate::buildTemplate(), 'Kunden-Vorlage.csv', 'text/csv; charset=UTF-8');
    }

    /** Verarbeitet die hochgeladene CSV-Datei (siehe ImportCustomersFromCsvHandler, "Alles oder nichts"). */
    public function import(Request $request): void
    {
        try {
            $count = $this->commands->dispatch(new ImportCustomersFromCsv(
                uploadedFile: $request->file('file') ?? ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
            ));

            Flash::success($count === 1 ? t('customers.import.flash.saved_one') : t('customers.import.flash.saved_many', ['count' => $count]));
            Response::redirect('/kunden');
        } catch (ValidationException $e) {
            echo View::page('customers/import', [
                'errors' => $e->errors(),
            ], activeNav: 'customers', title: t('customers.import.title'));
        }
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

    /**
     * Spiegelt Customer::displayName() wider, aber auf Basis der (noch nicht
     * in eine Customer-Entität validierten) Command-Rohdaten — für die
     * JSON-Antwort des AJAX-Kundenanlage-Modals braucht es keinen erneuten
     * GetCustomer-Roundtrip, da alle benötigten Felder bereits im Command
     * vorliegen.
     */
    private function displayNameFromCommand(CreateCustomer $command): string
    {
        if ($command->type === CustomerType::Geschaeftlich->value && $command->companyName !== null && trim($command->companyName) !== '') {
            return $command->companyName;
        }

        return trim($command->firstName . ' ' . $command->lastName);
    }
}
