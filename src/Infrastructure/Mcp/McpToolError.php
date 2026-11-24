<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use RuntimeException;

/**
 * Error of a single tool call that the model should see and can react to
 * (invalid input, record not found, business rule violated, missing
 * permission). Reported as a tool result with "isError": true, not as a
 * JSON-RPC protocol error, as the MCP specification recommends.
 */
final class McpToolError extends RuntimeException
{
    /** @param array<string, string> $fieldErrors */
    public function __construct(string $message, public readonly array $fieldErrors = [], public readonly int $logStatus = 400)
    {
        parent::__construct($message);
    }
}
