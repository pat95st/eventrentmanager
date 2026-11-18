#!/usr/bin/env php
<?php

declare(strict_types=1);

// Regenerates docs/openapi/components/generated.yaml from the domain classes
// (see App\Infrastructure\Http\Api\OpenApiSchemaGenerator). Run after changing
// an entity exposed by the REST API, then rebuild api-docs.html with
// bin/build-api-docs.sh.

require __DIR__ . '/../src/autoload.php';

$target = __DIR__ . '/../docs/openapi/components/generated.yaml';
if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, true);
}
file_put_contents($target, App\Infrastructure\Http\Api\OpenApiSchemaGenerator::yaml());
echo 'Written ' . realpath($target) . PHP_EOL;
