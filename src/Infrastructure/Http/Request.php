<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class Request
{
    /** @param array<string, string> $routeParams */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public array $routeParams = [],
        // Per fetch() ausgelöste Anfrage (siehe X-Requested-With-Konvention
        // der Passkey-Funktionalität). Wird für die Unterscheidung
        // "volle Seite vs. Tabellen-Fragment" bei paginierten Listen
        // genutzt (siehe Pagination::isAjax()).
        public readonly bool $ajax = false,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Erlaubt Methoden-Override für HTML-Formulare (kein natives PUT/DELETE).
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper((string) $_POST['_method']);
        }

        $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        return new self($method, $path, $_GET, $_POST, $_FILES, ajax: $ajax);
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? null;

        return $value !== null ? (string) $value : $default;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        return isset($this->query[$key]) ? (string) $this->query[$key] : $default;
    }

    /** @return string[] Für Checkbox-Gruppen (name="feld[]") — leeres Array, falls nichts ausgewählt/übermittelt wurde. */
    public function inputArray(string $key): array
    {
        $value = $this->post[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($v) => is_scalar($v) ? (string) $v : null, $value), static fn ($v) => $v !== null));
    }

    public function param(string $key): string
    {
        return $this->routeParams[$key] ?? '';
    }

    /** @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    /** @return array{name: string, type: string, tmp_name: string, error: int, size: int}[] */
    public function fileList(string $key): array
    {
        $files = $this->files[$key] ?? null;
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            return [];
        }

        $list = [];
        foreach ($files['name'] as $i => $name) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $list[] = [
                'name' => $name,
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }

        return $list;
    }
}
