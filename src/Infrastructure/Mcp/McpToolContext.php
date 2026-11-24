<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;

/** Per-call context handed to tool handlers: the token and links into the web UI. */
final class McpToolContext
{
    public function __construct(
        public readonly ApiToken $token,
        private readonly string $baseUrl,
    ) {
    }

    /** Absolute link to a page of the web interface, e.g. url('/angebote/' . $id). */
    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . $path;
    }

    public function allows(ApiScope ...$scopes): bool
    {
        foreach ($scopes as $scope) {
            if (!$this->token->allows($scope)) {
                return false;
            }
        }

        return true;
    }

    /** For tools whose required scopes depend on the arguments. */
    public function requireScopes(ApiScope ...$scopes): void
    {
        $missing = array_values(array_filter($scopes, fn (ApiScope $s) => !$this->token->allows($s)));
        if ($missing !== []) {
            throw new McpToolError(
                'Missing permission for this action: ' . implode(', ', array_map(static fn (ApiScope $s) => $s->value, $missing)) . '.',
                logStatus: 403,
            );
        }
    }
}
