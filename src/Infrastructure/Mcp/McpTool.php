<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;
use Closure;

/**
 * One MCP tool: name, description and JSON Schema of its arguments as shown
 * to the model, the API scopes a token needs to use it, and the handler.
 *
 * A tool is offered to a token only if the token holds all $scopes and —
 * for write tools — neither the token nor the global MCP settings are read
 * only (see isAvailableFor()).
 */
final class McpTool
{
    /**
     * @param array<string, mixed> $inputSchema JSON Schema (type "object")
     * @param list<ApiScope> $scopes all of them are required
     * @param Closure(array<string, mixed>, McpToolContext): array<string, mixed> $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly array $scopes,
        public readonly bool $readOnly,
        private readonly Closure $handler,
        public readonly bool $idempotent = false,
    ) {
    }

    public function isAvailableFor(ApiToken $token, bool $globalReadOnly): bool
    {
        if (!$this->readOnly && ($globalReadOnly || $token->readOnly)) {
            return false;
        }
        foreach ($this->scopes as $scope) {
            if (!$token->allows($scope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function call(array $arguments, McpToolContext $context): array
    {
        return ($this->handler)($arguments, $context);
    }

    /** @return array<string, mixed> entry of the tools/list result */
    public function definition(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
            'annotations' => [
                'title' => $this->title,
                'readOnlyHint' => $this->readOnly,
                // No tool deletes anything or does something irreversible
                // (finalizing invoices, sending e-mails and deleting are not offered).
                'destructiveHint' => false,
                'idempotentHint' => $this->readOnly || $this->idempotent,
                'openWorldHint' => false,
            ],
        ];
    }
}
