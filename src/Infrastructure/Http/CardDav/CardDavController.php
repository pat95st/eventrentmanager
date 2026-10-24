<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\CardDav;

use App\Application\Customer\Query\ListCustomers;
use App\Application\Query\QueryBus;
use App\Application\Supplier\Query\ListSuppliers;
use App\Domain\AddressBook\AddressBook;
use App\Domain\Customer\Customer;
use App\Domain\Supplier\Supplier;
use App\Domain\User\User;
use App\Infrastructure\Http\Auth\CardDavAuthGuard;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use DOMDocument;

/**
 * Minimaler, aber echter CardDAV-Server (RFC 6352) — rein lesend (Sync in
 * Kontakt-Apps, keine Rückschreibung, dieselbe Projektentscheidung wie beim
 * CalDAV-Server, siehe CalDavController). Deckt denselben Ablauf ab, den
 * reale Clients (Apple Kontakte, Thunderbird, iOS/iPadOS) für ein
 * Nur-Lese-Adressbuch tatsächlich brauchen: Discovery (.well-known →
 * current-user-principal → addressbook-home-set), PROPFIND (Depth 0/1) auf
 * jeder Ebene, REPORT (addressbook-query sowie addressbook-multiget) und GET
 * auf einzelne Ressourcen. Anders als beim Kalender gibt es hier nur zwei
 * feste, nicht konfigurierbare Adressbücher (siehe App\Domain\AddressBook\
 * AddressBook) — kein Pendant zu den frei anlegbaren Vermietungskalendern.
 *
 * Autorisierung: CardDavAuthGuard prüft die Basic-Auth-Zugangsdaten, dieser
 * Controller prüft zusätzlich pro Anfrage, dass der {userId}-Teil des Pfads
 * zum authentifizierten User passt (sonst 403) sowie bei adressbuch-
 * spezifischen Anfragen, dass der User die für dieses Adressbuch nötige
 * Berechtigung hat (siehe requireAddressBookAccess()) — dieselbe
 * Berechtigung, die auch die jeweilige Frontend-Seite (/kunden bzw.
 * /lieferanten) schützt.
 */
final class CardDavController
{
    private const DAV_HEADER = '1, 3, addressbook';

    public function __construct(
        private readonly CardDavAuthGuard $authGuard,
        private readonly QueryBus $queries,
    ) {
    }

    /** .well-known/carddav (RFC 6764) — leitet auf die Discovery-Wurzel um. */
    public function wellKnown(Request $request): void
    {
        header('Location: /carddav/', true, 302);
        exit;
    }

    public function options(Request $request): void
    {
        http_response_code(200);
        header('DAV: ' . self::DAV_HEADER);
        header('Allow: OPTIONS, GET, HEAD, PROPFIND, REPORT');
        header('Content-Length: 0');
        exit;
    }

    /** PROPFIND /carddav/ — current-user-principal. */
    public function root(Request $request): void
    {
        $user = $this->authGuard->authenticate();

        $body = $this->multistatus([
            $this->response('/carddav/', [
                $this->prop('D:current-user-principal', $this->href($this->principalHref($user))),
                $this->prop('D:resourcetype', '<D:collection/>'),
            ]),
        ]);

        $this->respond207($body);
    }

    /** PROPFIND /carddav/principals/{userId}/ — verweist auf das Adressbuch-Home. */
    public function principal(Request $request): void
    {
        $user = $this->requireOwnPath($request);

        $body = $this->multistatus([
            $this->response($this->principalHref($user), [
                $this->prop('D:resourcetype', '<D:collection/><D:principal/>'),
                $this->prop('D:displayname', $this->esc($user->email)),
                $this->prop('D:current-user-principal', $this->href($this->principalHref($user))),
                $this->prop('card:addressbook-home-set', $this->href($this->addressBookHomeHref($user))),
            ]),
        ]);

        $this->respond207($body);
    }

    /** PROPFIND /carddav/addressbooks/{userId}/ — listet die für den User sichtbaren Adressbücher (Depth 1). */
    public function addressBookHome(Request $request): void
    {
        $user = $this->requireOwnPath($request);

        $responses = [
            $this->response($this->addressBookHomeHref($user), [
                $this->prop('D:resourcetype', '<D:collection/>'),
                $this->prop('D:displayname', 'Adressbücher'),
            ]),
        ];

        if ($this->depth($request) !== '0') {
            foreach (AddressBook::all() as $addressBook) {
                if (!$user->has($addressBook->requiredPermission())) {
                    continue;
                }

                $responses[] = $this->response($this->addressBookHref($user, $addressBook), $this->addressBookProps($addressBook));
            }
        }

        $this->respond207($this->multistatus($responses));
    }

    /** PROPFIND /carddav/addressbooks/{userId}/{bookId}/ — Adressbuch-Eigenschaften, bei Depth 1 zusätzlich alle Kontakte. */
    public function addressBookCollection(Request $request): void
    {
        $user = $this->requireOwnPath($request);
        $addressBook = $this->requireAddressBookAccess($request, $user);

        $responses = [
            $this->response($this->addressBookHref($user, $addressBook), $this->addressBookProps($addressBook)),
        ];

        if ($this->depth($request) !== '0') {
            foreach ($this->contactsFor($addressBook) as $contact) {
                $responses[] = $this->response(
                    $this->contactHref($user, $addressBook, $contact['id']),
                    [
                        $this->prop('D:resourcetype', ''),
                        $this->prop('D:getcontenttype', 'text/vcard; charset=utf-8'),
                        $this->prop('D:getetag', $this->esc($contact['etag'])),
                    ]
                );
            }
        }

        $this->respond207($this->multistatus($responses));
    }

    /**
     * REPORT /carddav/addressbooks/{userId}/{bookId}/ — addressbook-query
     * (liefert immer alle Kontakte des Adressbuchs, da wir keine
     * Filterkriterien auswerten — reale Clients fragen i. d. R. ohnehin ohne
     * einschränkenden Filter) ODER addressbook-multiget (explizite
     * href-Liste, üblicherweise im Anschluss an eine PROPFIND-Depth-1-Liste
     * verwendet, um die vCard-Inhalte gebündelt statt per Einzel-GET zu
     * holen). Beide Report-Typen werden anhand des XML-Bodys unterschieden.
     */
    public function report(Request $request): void
    {
        $user = $this->requireOwnPath($request);
        $addressBook = $this->requireAddressBookAccess($request, $user);

        $body = file_get_contents('php://input') ?: '';
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadXML($body);
        libxml_use_internal_errors($previous);

        $requestedHrefs = [];
        foreach ($doc->getElementsByTagNameNS('DAV:', 'href') as $node) {
            $requestedHrefs[] = trim($node->textContent);
        }

        $contacts = $this->contactsFor($addressBook);

        if ($requestedHrefs !== []) {
            // addressbook-multiget: nur die explizit angefragten Ressourcen liefern.
            $requestedIds = array_map(fn ($href) => $this->contactIdFromHref($href), $requestedHrefs);
            $contacts = array_values(array_filter($contacts, fn ($contact) => in_array($contact['id'], $requestedIds, true)));
        }

        $responses = array_map(
            fn (array $contact) => $this->response(
                $this->contactHref($user, $addressBook, $contact['id']),
                [
                    $this->prop('D:getetag', $this->esc($contact['etag'])),
                    $this->propCdata('card:address-data', $contact['vcard']),
                ]
            ),
            $contacts
        );

        $this->respond207($this->multistatus($responses));
    }

    /** GET /carddav/addressbooks/{userId}/{bookId}/{contactId}.vcf — einzelne Ressource. */
    public function getContact(Request $request): void
    {
        $user = $this->requireOwnPath($request);
        $addressBook = $this->requireAddressBookAccess($request, $user);

        $contactId = $this->contactIdFromHref($request->param('contactId'));
        $contact = null;
        foreach ($this->contactsFor($addressBook) as $candidate) {
            if ($candidate['id'] === $contactId) {
                $contact = $candidate;

                break;
            }
        }

        if ($contact === null) {
            Response::raw('Nicht gefunden.', 404, 'text/plain; charset=utf-8');
        }

        Response::raw($contact['vcard'], 200, 'text/vcard; charset=utf-8', [
            'ETag' => $contact['etag'],
        ]);
    }

    // --- Autorisierung -------------------------------------------------

    private function requireOwnPath(Request $request): User
    {
        $user = $this->authGuard->authenticate();

        if ($request->param('userId') !== $user->id) {
            Response::raw('Zugriff verweigert.', 403, 'text/plain; charset=utf-8');
        }

        return $user;
    }

    private function requireAddressBookAccess(Request $request, User $user): AddressBook
    {
        $addressBook = AddressBook::fromId($request->param('bookId'));

        if ($addressBook === null || !$user->has($addressBook->requiredPermission())) {
            // 404 statt 403: verrät nicht, ob das Adressbuch überhaupt
            // existiert (dieselbe Begründung wie bei CalDavController).
            Response::raw('Nicht gefunden.', 404, 'text/plain; charset=utf-8');
        }

        return $addressBook;
    }

    /** @return array{id: string, vcard: string, etag: string}[] */
    private function contactsFor(AddressBook $addressBook): array
    {
        if ($addressBook === AddressBook::Customers) {
            /** @var Customer[] $customers */
            $customers = $this->queries->ask(new ListCustomers())->items;

            return array_map(
                fn (Customer $customer) => [
                    'id' => $customer->id,
                    'vcard' => VCardContactWriter::writeCustomer($customer),
                    'etag' => VCardContactWriter::etagForCustomer($customer),
                ],
                $customers
            );
        }

        /** @var Supplier[] $suppliers */
        $suppliers = $this->queries->ask(new ListSuppliers())->items;

        return array_map(
            fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'vcard' => VCardContactWriter::writeSupplier($supplier),
                'etag' => VCardContactWriter::etagForSupplier($supplier),
            ],
            $suppliers
        );
    }

    // --- Pfade -----------------------------------------------------------

    private function principalHref(User $user): string
    {
        return '/carddav/principals/' . $user->id . '/';
    }

    private function addressBookHomeHref(User $user): string
    {
        return '/carddav/addressbooks/' . $user->id . '/';
    }

    private function addressBookHref(User $user, AddressBook $addressBook): string
    {
        return '/carddav/addressbooks/' . $user->id . '/' . $addressBook->value . '/';
    }

    private function contactHref(User $user, AddressBook $addressBook, string $contactId): string
    {
        return $this->addressBookHref($user, $addressBook) . $contactId . '.vcf';
    }

    private function contactIdFromHref(string $href): string
    {
        $basename = basename(parse_url($href, PHP_URL_PATH) ?: $href);

        return str_ends_with($basename, '.vcf') ? substr($basename, 0, -4) : $basename;
    }

    private function depth(Request $request): string
    {
        $depth = $_SERVER['HTTP_DEPTH'] ?? '0';

        return is_string($depth) ? $depth : '0';
    }

    // --- XML-Aufbau (Multistatus) ----------------------------------------

    /** @return array{href: string, props: string[]} */
    private function response(string $href, array $props): array
    {
        return ['href' => $href, 'props' => $props];
    }

    private function prop(string $name, string $value): string
    {
        return $value === '' ? "<{$name}/>" : "<{$name}>{$value}</{$name}>";
    }

    /** address-data-Inhalte dürfen kein weiteres XML-Escaping der vCard-Zeilenumbrüche erhalten, aber die vCard selbst wird als Text eingebettet (kein CDATA nötig — SUMMARY-artige Felder werden dennoch defensiv escaped, siehe VCardContactWriter). */
    private function propCdata(string $name, string $value): string
    {
        return "<{$name}>" . $this->esc($value) . "</{$name}>";
    }

    private function href(string $path): string
    {
        return '<D:href>' . $this->esc($path) . '</D:href>';
    }

    /** @return string[] */
    private function addressBookProps(AddressBook $addressBook): array
    {
        return [
            $this->prop('D:resourcetype', '<D:collection/><card:addressbook/>'),
            $this->prop('D:displayname', $this->esc($addressBook->label())),
            $this->prop('card:supported-address-data', '<card:address name="vCard" version="3.0"/>'),
        ];
    }

    /** @param array{href: string, props: string[]}[] $responses */
    private function multistatus(array $responses): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\r\n";
        $xml .= '<D:multistatus xmlns:D="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">' . "\r\n";

        foreach ($responses as $response) {
            $xml .= '<D:response><D:href>' . $this->esc($response['href']) . '</D:href>';
            $xml .= '<D:propstat><D:prop>' . implode('', $response['props']) . '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>';
            $xml .= '</D:response>' . "\r\n";
        }

        $xml .= '</D:multistatus>';

        return $xml;
    }

    private function respond207(string $xml): void
    {
        Response::raw($xml, 207, 'application/xml; charset=utf-8', ['DAV' => self::DAV_HEADER]);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
