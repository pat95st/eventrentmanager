<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Infrastructure\DocumentStorage\Cloud\CloudHttpClientInterface;
use App\Infrastructure\DocumentStorage\Cloud\CloudHttpResponse;

/**
 * HTTP transport without network for the cloud storage clients: every
 * request is recorded and answered by the first matching route
 * ("METHOD url-prefix"); unmatched requests get HTTP 404.
 */
final class FakeCloudHttpClient implements CloudHttpClientInterface
{
    /** @var list<array{method: string, url: string, headers: list<string>, body: ?string}> */
    public array $requests = [];

    /** @var list<array{0: string, 1: string, 2: callable|CloudHttpResponse, 3: bool}> */
    private array $routes = [];

    /**
     * @param callable(string, string, list<string>, ?string): CloudHttpResponse|CloudHttpResponse $response
     * @param bool $once Remove the route after its first use.
     */
    public function on(string $method, string $urlPrefix, callable|CloudHttpResponse $response, bool $once = false): self
    {
        $this->routes[] = [$method, $urlPrefix, $response, $once];

        return $this;
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null): CloudHttpResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        foreach ($this->routes as $index => [$routeMethod, $prefix, $response, $once]) {
            if ($routeMethod === $method && str_starts_with($url, $prefix)) {
                if ($once) {
                    unset($this->routes[$index]);
                    $this->routes = array_values($this->routes);
                }

                return $response instanceof CloudHttpResponse ? $response : $response($method, $url, $headers, $body);
            }
        }

        return new CloudHttpResponse(404, '{"error":{"message":"not found"}}');
    }

    public static function json(int $status, mixed $payload, array $headers = []): CloudHttpResponse
    {
        return new CloudHttpResponse($status, json_encode($payload, JSON_THROW_ON_ERROR), $headers);
    }

    /** @return list<string> "METHOD url" of all recorded requests */
    public function log(): array
    {
        return array_map(static fn (array $r) => $r['method'] . ' ' . $r['url'], $this->requests);
    }

    public static function header(array $request, string $name): ?string
    {
        foreach ($request['headers'] as $line) {
            [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
            if (strcasecmp(trim($key), $name) === 0) {
                return trim($value);
            }
        }

        return null;
    }
}
