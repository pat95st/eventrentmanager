<?php

declare(strict_types=1);

// Router-Skript ausschließlich für den PHP-Built-in-Entwicklungsserver
// (`php -S localhost:8000 router.php`). Es liefert vorhandene Dateien unter
// public/ direkt aus (z. B. falls dort später CSS/JS abgelegt wird) und
// reicht alle anderen Requests an den Front-Controller weiter.
// Für Apache/Nginx im Produktivbetrieb wird stattdessen public/ als
// DocumentRoot konfiguriert (siehe public/.htaccess für Apache).

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicFile = __DIR__ . '/public' . $uri;

if ($uri !== '/' && is_file($publicFile)) {
    return false; // Built-in-Server liefert die Datei selbst aus.
}

require __DIR__ . '/public/index.php';
