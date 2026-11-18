<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Application\Command\CommandBus;
use App\Application\Command\CommandInterface;
use App\Application\Query\QueryBus;
use App\Application\Query\QueryInterface;
use DateTimeImmutable;
use Exception;

/**
 * Base class of the /api/v1 controllers: thin JSON layer over the same
 * commands and queries the web UI uses.
 *
 * Lists share one behaviour (listResponse()): the full result of the
 * underlying query is normalized, then filtered by `q` (case-insensitive
 * substring over all top-level text fields), `updated_since` (ISO 8601,
 * compares `updated_at`), sorted by `sort` (`field` or `-field`) and paged
 * with `page`/`page_size` (default 100, max 500).
 */
abstract class ApiController
{
    public const DEFAULT_PAGE_SIZE = 100;
    public const MAX_PAGE_SIZE = 500;

    public function __construct(
        protected readonly QueryBus $queries,
        protected readonly CommandBus $commands,
    ) {
    }

    protected function ask(QueryInterface $query): mixed
    {
        return $this->queries->ask($query);
    }

    protected function dispatch(CommandInterface $command): mixed
    {
        return $this->commands->dispatch($command);
    }

    /**
     * Builds the command from the body and dispatches it (see CommandFactory).
     *
     * @param class-string<CommandInterface> $class
     * @param array<string, mixed> $overrides
     * @param array<string, mixed>|null $current
     * @param array<string, string> $aliases
     */
    protected function run(string $class, ApiRequest $request, array $overrides = [], ?array $current = null, array $aliases = []): mixed
    {
        return $this->commands->dispatch(CommandFactory::create($class, $request->body, $overrides, $current, $aliases));
    }

    /**
     * @param list<array<string, mixed>> $items normalized items
     * @param array<string, mixed> $extraMeta
     */
    protected function listResponse(ApiRequest $request, array $items, array $extraMeta = []): ApiResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $items = array_values(array_filter($items, static function (array $item) use ($needle): bool {
                foreach ($item as $value) {
                    if (is_string($value) && str_contains(mb_strtolower($value), $needle)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        $since = $request->query('updated_since');
        if ($since !== null && $since !== '') {
            try {
                $sinceAt = new DateTimeImmutable($since);
            } catch (Exception) {
                throw ApiException::badRequest('updated_since muss ein Zeitpunkt im Format ISO 8601 sein.');
            }
            if ($items !== [] && !array_key_exists('updated_at', $items[0])) {
                throw ApiException::badRequest('updated_since wird für diese Ressource nicht unterstützt (kein Feld updated_at).');
            }
            $items = array_values(array_filter($items, static function (array $item) use ($sinceAt): bool {
                $value = $item['updated_at'] ?? null;

                return is_string($value) && new DateTimeImmutable($value) >= $sinceAt;
            }));
        }

        $sort = trim((string) $request->query('sort', ''));
        if ($sort !== '') {
            $desc = str_starts_with($sort, '-');
            $field = ltrim($sort, '-+');
            if ($items !== [] && !array_key_exists($field, $items[0])) {
                throw ApiException::badRequest('Nach "' . $field . '" kann nicht sortiert werden.');
            }
            usort($items, static function (array $a, array $b) use ($field, $desc): int {
                $x = $a[$field] ?? null;
                $y = $b[$field] ?? null;
                $cmp = is_string($x) && is_string($y) ? strnatcasecmp($x, $y) : $x <=> $y;

                return $desc ? -$cmp : $cmp;
            });
        }

        [$page, $pageSize] = $this->paging($request);
        $total = count($items);

        return ApiResponse::json([
            'data' => array_slice($items, ($page - 1) * $pageSize, $pageSize),
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => (int) max(1, (int) ceil($total / $pageSize)),
            ] + $extraMeta,
        ]);
    }

    /** @return array{int, int} */
    protected function paging(ApiRequest $request): array
    {
        $page = max(1, (int) ($request->query('page') ?? '1'));
        $pageSize = (int) ($request->query('page_size') ?? (string) self::DEFAULT_PAGE_SIZE);

        return [$page, max(1, min(self::MAX_PAGE_SIZE, $pageSize))];
    }

    /** Required date query parameter (Y-m-d). */
    protected function dateQuery(ApiRequest $request, string $name, ?DateTimeImmutable $default = null): DateTimeImmutable
    {
        $raw = $request->query($name);
        if ($raw === null || $raw === '') {
            if ($default !== null) {
                return $default;
            }
            throw ApiException::badRequest('Parameter "' . $name . '" (Datum, Y-m-d) fehlt.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false) {
            throw ApiException::badRequest('Parameter "' . $name . '" muss ein Datum im Format Y-m-d sein.');
        }

        return $date;
    }

    /** Response for a created resource: 201 with the freshly loaded representation. */
    protected function created(mixed $data): ApiResponse
    {
        return ApiResponse::data($data, 201);
    }

    /** PDF/XRechnung etc. generated in memory. */
    protected function pdfResponse(string $content, string $fileName): ApiResponse
    {
        return ApiResponse::content($content, 'application/pdf', $fileName);
    }
}
