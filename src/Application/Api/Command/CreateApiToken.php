<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

use App\Application\Command\CommandInterface;

final class CreateApiToken implements CommandInterface
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $ipAllowlist,
        /** @var list<string> scope values, see App\Domain\Api\ApiScope */
        public readonly array $scopes = [],
        public readonly bool $readOnly = false,
        // Empty/null = global default from the API settings.
        public readonly ?string $rateLimitPerMinute = null,
        // Released for the MCP server /mcp.
        public readonly bool $mcpAllowed = false,
    ) {
    }
}
