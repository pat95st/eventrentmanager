<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Api;

use App\Infrastructure\Http\Api\ApiRoutes;
use App\Infrastructure\Http\Api\OpenApiSchemaGenerator;
use Codeception\Test\Unit;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps docs/openapi/ in sync with the code: every route of ApiRoutes must be
 * documented with the same scopes, nothing undocumented may be listed, and
 * the generated entity/request schemas must be current
 * (fix: php bin/generate-api-schemas.php, then bin/build-api-docs.sh).
 */
final class ApiDocumentationTest extends Unit
{
    private const DOCS = __DIR__ . '/../../../../../docs/openapi';

    public function testEveryRouteIsDocumentedWithItsScopes(): void
    {
        $documented = $this->documentedOperations();
        foreach (ApiRoutes::definitions() as [$method, $path, $scopes]) {
            $key = $method . ' ' . ApiRoutes::PREFIX . $path;
            $this->assertArrayHasKey($key, $documented, $key . ' is not documented in docs/openapi/paths/.');
            $this->assertSame(
                array_map(static fn ($s) => $s->value, $scopes),
                $documented[$key]['x-required-scopes'] ?? null,
                $key . ': documented scopes differ from ApiRoutes.',
            );
        }
    }

    public function testNoUndocumentedRouteIsListed(): void
    {
        $routes = [];
        foreach (ApiRoutes::definitions() as [$method, $path]) {
            $routes[$method . ' ' . ApiRoutes::PREFIX . $path] = true;
        }
        foreach (array_keys($this->documentedOperations()) as $key) {
            if (str_contains($key, ' /api/v1/')) {
                $this->assertArrayHasKey($key, $routes, $key . ' is documented but not routed.');
            }
        }
    }

    public function testEveryPathFileEntryIsReferencedFromTheRoot(): void
    {
        $root = Yaml::parseFile(self::DOCS . '/openapi.yaml');
        foreach (glob(self::DOCS . '/paths/*.yaml') ?: [] as $file) {
            foreach (array_keys(Yaml::parseFile($file) ?? []) as $path) {
                $this->assertArrayHasKey($path, $root['paths'], $path . ' (' . basename($file) . ') is missing in openapi.yaml.');
            }
        }
    }

    public function testGeneratedSchemasAreUpToDate(): void
    {
        $this->assertSame(
            OpenApiSchemaGenerator::yaml(),
            file_get_contents(self::DOCS . '/components/generated.yaml'),
            'docs/openapi/components/generated.yaml is stale — run php bin/generate-api-schemas.php.',
        );
    }

    /** @return array<string, array<string, mixed>> "METHOD /path" => operation */
    private function documentedOperations(): array
    {
        $operations = [];
        foreach (glob(self::DOCS . '/paths/*.yaml') ?: [] as $file) {
            foreach (Yaml::parseFile($file) ?? [] as $path => $item) {
                foreach ($item as $method => $operation) {
                    $operations[strtoupper($method) . ' ' . $path] = $operation;
                }
            }
        }

        return $operations;
    }
}
