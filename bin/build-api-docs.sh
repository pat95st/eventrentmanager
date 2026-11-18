#!/usr/bin/env bash
# Builds the self-contained API documentation (api-docs.html in the repository
# root) from docs/openapi/openapi.yaml with Redoc. Requires Node.js/npx; the Redoc
# bundle is inlined so the HTML works offline without any CDN.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REDOCLY_CLI_VERSION="1.34.24"
REDOC_VERSION="2.5.3"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

cd "$ROOT"
php bin/generate-api-schemas.php
npx --yes "@redocly/cli@${REDOCLY_CLI_VERSION}" lint docs/openapi/openapi.yaml
npx --yes "@redocly/cli@${REDOCLY_CLI_VERSION}" build-docs docs/openapi/openapi.yaml \
    -o api-docs.html --title "Materialverleih – API"

(cd "$WORK" && npm init -y >/dev/null && npm install --silent "redoc@${REDOC_VERSION}")

node - "$ROOT/api-docs.html" "$WORK/node_modules/redoc/bundles/redoc.standalone.js" <<'JS'
const fs = require('fs');
const [html, bundle] = process.argv.slice(2);
const src = fs.readFileSync(html, 'utf8');
const tag = /<script src="https:\/\/cdn\.redocly\.com\/redoc\/[^"]+\/redoc\.standalone\.js"><\/script>/;
if (!tag.test(src)) {
    console.error('Redoc CDN script tag not found in ' + html);
    process.exit(1);
}
const js = fs.readFileSync(bundle, 'utf8').replace(/<\/script/g, '<\\/script');
fs.writeFileSync(html, src.replace(tag, () => '<script>' + js + '</script>'));
console.log('Inlined Redoc bundle into ' + html);
JS
