<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Federation\FederationApiException;
use App\Application\Federation\FederationClientInterface;
use App\Application\Federation\FederationException;
use App\Application\Federation\InboundFederationService;
use App\Domain\Exception\ValidationException;

/**
 * Routes federation calls directly to the InboundFederationService of other
 * in-memory installations (by base URL), with a JSON round trip like HTTP.
 */
final class FakeFederationNetwork implements FederationClientInterface
{
    /** @var array<string, InboundFederationService> */
    private array $instances = [];
    /** @var list<string> base URLs that are currently unreachable */
    public array $offline = [];
    /** @var list<string> */
    public array $log = [];

    public function register(string $baseUrl, InboundFederationService $service): void
    {
        $this->instances[$baseUrl] = $service;
    }

    public function request(string $method, string $baseUrl, string $path, ?string $token = null, ?array $body = null): array
    {
        $this->log[] = $method . ' ' . $baseUrl . $path;
        $service = $this->instances[$baseUrl] ?? null;
        if ($service === null || in_array($baseUrl, $this->offline, true)) {
            throw new FederationException('Die Partner-Instanz ist nicht erreichbar (fake).');
        }
        $body = $body !== null ? json_decode(json_encode($body, JSON_THROW_ON_ERROR), true) : [];
        $parts = parse_url($path);
        parse_str($parts['query'] ?? '', $query);
        $route = substr($parts['path'] ?? '', strlen('/api/federation/v1'));

        try {
            $result = $this->dispatch($service, strtoupper($method), $route, $token, $body, $query);
        } catch (FederationApiException $e) {
            throw new FederationException($e->getMessage(), $e->httpStatus);
        } catch (ValidationException $e) {
            throw new FederationException(implode(' ', $e->errors()), 422);
        }

        return json_decode(json_encode($result, JSON_THROW_ON_ERROR), true);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function dispatch(InboundFederationService $service, string $method, string $route, ?string $token, array $body, array $query): array
    {
        if (preg_match('#^/invitations/([^/]+)$#', $route, $m) === 1 && $method === 'GET') {
            return $service->invitationInfo($m[1]);
        }
        if (preg_match('#^/invitations/([^/]+)/accept$#', $route, $m) === 1 && $method === 'POST') {
            return $service->acceptInvitation($m[1], $body);
        }
        $partner = $service->authenticate($token);

        return match (true) {
            $method === 'GET' && $route === '/partnership' => $service->partnership($partner),
            $method === 'POST' && $route === '/partnership/confirm' => $service->confirm($partner, $body),
            $method === 'POST' && $route === '/partnership/discount' => $service->discount($partner, $body),
            $method === 'POST' && $route === '/partnership/end' => $service->end($partner),
            $method === 'GET' && $route === '/articles' => $service->articles($partner, $query),
            $method === 'GET' && $route === '/technicians' => $service->technicians($partner),
            $method === 'POST' && $route === '/requests' => $service->createRequest($partner, $body),
            $method === 'GET' && preg_match('#^/requests/([^/]+)$#', $route, $m) === 1 => $service->requestStatus($partner, $m[1]),
            $method === 'POST' && preg_match('#^/requests/([^/]+)/cancel$#', $route, $m) === 1 => $service->cancelRequest($partner, $m[1]),
            $method === 'POST' && preg_match('#^/requests/([^/]+)/status$#', $route, $m) === 1 => $service->receiveStatus($partner, $m[1], $body),
            default => throw new FederationApiException('Not found', 404),
        };
    }
}
