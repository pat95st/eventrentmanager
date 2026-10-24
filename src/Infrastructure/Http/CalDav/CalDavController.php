<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\CalDav;

use App\Application\Calendar\Query\GetCalendar;
use App\Application\Calendar\Query\ListCalendarsForUser;
use App\Application\Calendar\Query\ListRentalsForCalendar;
use App\Application\Query\QueryBus;
use App\Domain\Calendar\Calendar;
use App\Domain\Calendar\CalendarAccessRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\Rental;
use App\Domain\User\User;
use App\Infrastructure\Http\Auth\CalDavAuthGuard;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use DateTimeImmutable;
use DOMDocument;

/**
 * Minimaler, aber echter CalDAV-Server (RFC 4791) — rein lesend (Sync in
 * Kalender-Apps, siehe Projektentscheidung: kein Zurückschreiben). Deckt
 * genau den Ablauf ab, den reale Clients (Apple Kalender, Thunderbird,
 * iOS/iPadOS) für einen Nur-Lese-Kalender tatsächlich brauchen:
 * Discovery (.well-known → current-user-principal → calendar-home-set),
 * PROPFIND (Depth 0/1) auf jeder Ebene, REPORT (calendar-query mit
 * optionalem Zeitraum sowie calendar-multiget) und GET auf einzelne
 * Ressourcen. Anfragen-Properties in PROPFIND werden bewusst NICHT geparst
 * (siehe wellKnown()-Docblock) — es wird immer der volle, feste
 * Eigenschaftensatz zurückgegeben; reale Clients tolerieren das.
 *
 * Autorisierung: CalDavAuthGuard prüft die Basic-Auth-Zugangsdaten,
 * dieser Controller prüft zusätzlich pro Anfrage, dass der {userId}-Teil
 * des Pfads zum authentifizierten User passt (sonst 403 — verhindert, dass
 * ein User fremde Kalenderpfade erraten/lesen kann) sowie bei
 * kalenderspezifischen Anfragen, dass der Kalender aktiviert ist und der
 * User Zugriff hat (siehe requireCalendarAccess()).
 */
final class CalDavController
{
    private const DAV_HEADER = '1, 2, 3, calendar-access';

    public function __construct(
        private readonly CalDavAuthGuard $authGuard,
        private readonly QueryBus $queries,
        private readonly CalendarAccessRepositoryInterface $calendarAccess,
    ) {
    }

    /** .well-known/caldav (RFC 6764) — leitet auf die Discovery-Wurzel um. */
    public function wellKnown(Request $request): void
    {
        header('Location: /caldav/', true, 302);
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

    /** PROPFIND /caldav/ — current-user-principal. */
    public function root(Request $request): void
    {
        $user = $this->authGuard->authenticate();

        $body = $this->multistatus([
            $this->response('/caldav/', [
                $this->prop('D:current-user-principal', $this->href($this->principalHref($user))),
                $this->prop('D:resourcetype', '<D:collection/>'),
            ]),
        ]);

        $this->respond207($body);
    }

    /** PROPFIND /caldav/principals/{userId}/ — verweist auf das Kalender-Home. */
    public function principal(Request $request): void
    {
        $user = $this->requireOwnPath($request);

        $body = $this->multistatus([
            $this->response($this->principalHref($user), [
                $this->prop('D:resourcetype', '<D:collection/><D:principal/>'),
                $this->prop('D:displayname', $this->esc($user->email)),
                $this->prop('D:current-user-principal', $this->href($this->principalHref($user))),
                $this->prop('C:calendar-home-set', $this->href($this->calendarHomeHref($user))),
                $this->prop('C:calendar-user-address-set', $this->href('mailto:' . $user->email)),
            ]),
        ]);

        $this->respond207($body);
    }

    /** PROPFIND /caldav/calendars/{userId}/ — listet die sichtbaren Kalender des Users (Depth 1). */
    public function calendarHome(Request $request): void
    {
        $user = $this->requireOwnPath($request);

        $responses = [
            $this->response($this->calendarHomeHref($user), [
                $this->prop('D:resourcetype', '<D:collection/>'),
                $this->prop('D:displayname', 'Kalender'),
            ]),
        ];

        if ($this->depth($request) !== '0') {
            /** @var Calendar[] $calendars */
            $calendars = $this->queries->ask(new ListCalendarsForUser($user->id));
            foreach ($calendars as $calendar) {
                $responses[] = $this->response($this->calendarHref($user, $calendar), $this->calendarProps($calendar));
            }
        }

        $this->respond207($this->multistatus($responses));
    }

    /** PROPFIND /caldav/calendars/{userId}/{calendarId}/ — Kalender-Eigenschaften, bei Depth 1 zusätzlich alle Termine. */
    public function calendarCollection(Request $request): void
    {
        $user = $this->requireOwnPath($request);
        $calendar = $this->requireCalendarAccess($request, $user);

        $responses = [
            $this->response($this->calendarHref($user, $calendar), $this->calendarProps($calendar)),
        ];

        if ($this->depth($request) !== '0') {
            foreach ($this->rentalsFor($calendar) as $rental) {
                $responses[] = $this->response(
                    $this->eventHref($user, $calendar, $rental),
                    [
                        $this->prop('D:resourcetype', ''),
                        $this->prop('D:getcontenttype', 'text/calendar; charset=utf-8; component=VEVENT'),
                        $this->prop('D:getetag', $this->esc(IcsEventWriter::etagFor($rental))),
                    ]
                );
            }
        }

        $this->respond207($this->multistatus($responses));
    }

    /**
     * REPORT /caldav/calendars/{userId}/{calendarId}/ — calendar-query
     * (mit optionalem time-range) ODER calendar-multiget (explizite
     * href-Liste, üblicherweise im Anschluss an eine PROPFIND-Depth-1-Liste
     * verwendet, um die Termininhalte gebündelt statt per Einzel-GET zu
     * holen). Beide Report-Typen werden anhand des XML-Bodys unterschieden.
     */
    public function report(Request $request): void
    {
        $user = $this->requireOwnPath($request);
        $calendar = $this->requireCalendarAccess($request, $user);

        $body = file_get_contents('php://input') ?: '';
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadXML($body);
        libxml_use_internal_errors($previous);

        $requestedHrefs = [];
        foreach ($doc->getElementsByTagNameNS('DAV:', 'href') as $node) {
            $requestedHrefs[] = trim($node->textContent);
        }

        $rangeStart = null;
        $rangeEnd = null;
        $timeRangeNodes = $doc->getElementsByTagNameNS('urn:ietf:params:xml:ns:caldav', 'time-range');
        if ($timeRangeNodes->length > 0) {
            $node = $timeRangeNodes->item(0);
            $rangeStart = $this->parseIcsDateTime($node->getAttribute('start'));
            $rangeEnd = $this->parseIcsDateTime($node->getAttribute('end'));
        }

        $rentals = $this->rentalsFor($calendar, $rangeStart, $rangeEnd);

        if ($requestedHrefs !== []) {
            // calendar-multiget: nur die explizit angefragten Ressourcen liefern.
            $requestedIds = array_map(fn ($href) => $this->eventIdFromHref($href), $requestedHrefs);
            $rentals = array_values(array_filter($rentals, fn ($rental) => in_array($rental->id, $requestedIds, true)));
        }

        $responses = array_map(
            fn (Rental $rental) => $this->response(
                $this->eventHref($user, $calendar, $rental),
                [
                    $this->prop('D:getetag', $this->esc(IcsEventWriter::etagFor($rental))),
                    $this->propCdata('C:calendar-data', IcsEventWriter::write($rental)),
                ]
            ),
            $rentals
        );

        $this->respond207($this->multistatus($responses));
    }

    /** GET /caldav/calendars/{userId}/{calendarId}/{eventId}.ics — einzelne Ressource. */
    public function getEvent(Request $request): void
    {
        $user = $this->requireOwnPath($request);
        $calendar = $this->requireCalendarAccess($request, $user);

        $eventId = $this->eventIdFromHref($request->param('eventId'));
        $rental = null;
        foreach ($this->rentalsFor($calendar) as $candidate) {
            if ($candidate->id === $eventId) {
                $rental = $candidate;

                break;
            }
        }

        if ($rental === null) {
            Response::raw('Nicht gefunden.', 404, 'text/plain; charset=utf-8');
        }

        Response::raw(IcsEventWriter::write($rental), 200, 'text/calendar; charset=utf-8', [
            'ETag' => IcsEventWriter::etagFor($rental),
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

    private function requireCalendarAccess(Request $request, User $user): Calendar
    {
        try {
            /** @var Calendar $calendar */
            $calendar = $this->queries->ask(new GetCalendar($request->param('calendarId')));
        } catch (NotFoundException) {
            Response::raw('Nicht gefunden.', 404, 'text/plain; charset=utf-8');
        }

        $hasAccess = $user->isAdministrator() || $this->calendarAccess->hasAccess($calendar->id, $user->id);
        if (!$calendar->enabled || !$hasAccess) {
            // 404 statt 403: verrät nicht, ob der Kalender überhaupt existiert
            // (dieselbe Begründung wie beim globalen API-Schalter, siehe
            // ApiTokenGuard).
            Response::raw('Nicht gefunden.', 404, 'text/plain; charset=utf-8');
        }

        return $calendar;
    }

    /** @return Rental[] */
    private function rentalsFor(Calendar $calendar, ?DateTimeImmutable $rangeStart = null, ?DateTimeImmutable $rangeEnd = null): array
    {
        return $this->queries->ask(new ListRentalsForCalendar($calendar->id, $rangeStart, $rangeEnd));
    }

    // --- Pfade -----------------------------------------------------------

    private function principalHref(User $user): string
    {
        return '/caldav/principals/' . $user->id . '/';
    }

    private function calendarHomeHref(User $user): string
    {
        return '/caldav/calendars/' . $user->id . '/';
    }

    private function calendarHref(User $user, Calendar $calendar): string
    {
        return '/caldav/calendars/' . $user->id . '/' . $calendar->id . '/';
    }

    private function eventHref(User $user, Calendar $calendar, Rental $rental): string
    {
        return $this->calendarHref($user, $calendar) . $rental->id . '.ics';
    }

    private function eventIdFromHref(string $href): string
    {
        $basename = basename(parse_url($href, PHP_URL_PATH) ?: $href);

        return str_ends_with($basename, '.ics') ? substr($basename, 0, -4) : $basename;
    }

    private function depth(Request $request): string
    {
        $depth = $_SERVER['HTTP_DEPTH'] ?? '0';

        return is_string($depth) ? $depth : '0';
    }

    private function parseIcsDateTime(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Ymd\THis\Z', $value) ?: DateTimeImmutable::createFromFormat('Ymd', $value);

        return $parsed !== false ? $parsed : null;
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

    /** calendar-data-Inhalte dürfen kein weiteres XML-Escaping der ICS-Zeilenumbrüche erhalten, aber die ICS selbst wird als Text eingebettet (kein CDATA nötig, da keine "<"/">" im Inhalt vorkommen — SUMMARY/LOCATION werden dennoch defensiv escaped, siehe IcsEventWriter). */
    private function propCdata(string $name, string $value): string
    {
        return "<{$name}>" . $this->esc($value) . "</{$name}>";
    }

    private function href(string $path): string
    {
        return '<D:href>' . $this->esc($path) . '</D:href>';
    }

    /** @return string[] */
    private function calendarProps(Calendar $calendar): array
    {
        return [
            $this->prop('D:resourcetype', '<D:collection/><C:calendar/>'),
            $this->prop('D:displayname', $this->esc($calendar->name)),
            $this->prop('C:supported-calendar-component-set', '<C:comp name="VEVENT"/>'),
            $this->prop('CS:getctag', $this->esc($this->ctagFor($calendar))),
            $this->prop('D:sync-token', $this->esc($this->ctagFor($calendar))),
        ];
    }

    /**
     * "Change Tag": ändert sich jedes Mal, wenn sich der Kalender selbst
     * (Name/Filter/Aktivierung) ändert — reicht für unseren rein lesenden
     * Server als grobes "hat sich hier irgendwas verändert?"-Signal; echte
     * inkrementelle Synchronisation pro Termin läuft ohnehin über
     * getetag/calendar-multiget, nicht über den CTag.
     */
    private function ctagFor(Calendar $calendar): string
    {
        return sha1($calendar->id . '-' . $calendar->updatedAt->format(DATE_ATOM));
    }

    /** @param array{href: string, props: string[]}[] $responses */
    private function multistatus(array $responses): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\r\n";
        $xml .= '<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav" xmlns:CS="http://calendarserver.org/ns/">' . "\r\n";

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
