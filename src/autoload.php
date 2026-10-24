<?php

declare(strict_types=1);

// Schlanker PSR-4-artiger Autoloader für den Namespace "App\" -> src/.
// Erspart eine Composer-Pflichtabhängigkeit nur für den Autoloader; externe
// Pakete können bei Bedarf trotzdem klassisch per composer.json ergänzt werden.
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
