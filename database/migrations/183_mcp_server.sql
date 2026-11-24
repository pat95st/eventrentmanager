-- MCP server (Model Context Protocol) at /mcp, built on top of the REST API:
-- global switch and global read-only switch on Settings › API, plus a per-token
-- release ("Für MCP zulassen"). See App\Infrastructure\Mcp\McpServer and docs/mcp.md.

ALTER TABLE api_settings
    ADD COLUMN mcp_enabled   TINYINT(1) NOT NULL DEFAULT 0 AFTER enabled,
    ADD COLUMN mcp_read_only TINYINT(1) NOT NULL DEFAULT 0 AFTER mcp_enabled;

-- Existing tokens are not released for MCP automatically.
ALTER TABLE api_tokens
    ADD COLUMN mcp_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER read_only;
