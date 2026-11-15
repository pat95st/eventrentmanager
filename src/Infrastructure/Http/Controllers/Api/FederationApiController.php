<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Federation\FederationApiException;
use App\Application\Federation\InboundFederationService;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Federation\PartnerInstance;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Server-to-server endpoints for partner installations (/api/federation/v1,
 * migration 157). Stateless: no session, no CSRF — authenticated by the
 * partner's bearer token (or the secret code of an invitation link).
 */
final class FederationApiController
{
    public function __construct(private readonly InboundFederationService $service)
    {
    }

    public function invitation(Request $request): void
    {
        $this->respond(fn () => $this->service->invitationInfo($request->param('code')));
    }

    public function acceptInvitation(Request $request): void
    {
        $this->respond(fn () => $this->service->acceptInvitation($request->param('code'), $this->jsonBody()));
    }

    public function partnership(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->partnership($partner));
    }

    public function confirm(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->confirm($partner, $this->jsonBody()));
    }

    public function discount(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->discount($partner, $this->jsonBody()));
    }

    public function end(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->end($partner));
    }

    public function articles(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->articles($partner, $request->query));
    }

    public function technicians(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->technicians($partner));
    }

    public function createRequest(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->createRequest($partner, $this->jsonBody()), 201);
    }

    public function requestStatus(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->requestStatus($partner, $request->param('reference')));
    }

    public function cancelRequest(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->cancelRequest($partner, $request->param('reference')));
    }

    public function receiveStatus(Request $request): void
    {
        $this->authed(fn (PartnerInstance $partner) => $this->service->receiveStatus($partner, $request->param('reference'), $this->jsonBody()));
    }

    /** @param callable(PartnerInstance): array<string, mixed> $action */
    private function authed(callable $action, int $successStatus = 200): void
    {
        $this->respond(fn () => $action($this->service->authenticate($this->bearerToken())), $successStatus);
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action, int $successStatus = 200): void
    {
        try {
            $result = $action();
        } catch (FederationApiException $e) {
            Response::json(['error' => $e->getMessage()], $e->httpStatus);

            return;
        } catch (ValidationException $e) {
            Response::json(['error' => implode(' ', $e->errors())], 422);

            return;
        } catch (NotFoundException $e) {
            Response::json(['error' => $e->getMessage()], 404);

            return;
        } catch (ConflictException $e) {
            Response::json(['error' => $e->getMessage()], 409);

            return;
        }

        Response::json($result, $successStatus);
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    private function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        return is_string($header) && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m) === 1 ? $m[1] : null;
    }
}
