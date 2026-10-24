<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class View
{
    private static ?string $viewsPath = null;

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/');
    }

    /**
     * Rendert eine View OHNE Layout (für Fehlerseiten / Ausschnitte).
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = []): string
    {
        return self::renderFile($view, $data);
    }

    /**
     * Rendert eine View eingebettet in resources/views/layout.php.
     *
     * @param array<string, mixed> $data
     * @param string $title Name der Funktionalität für den HTML-<title>, z. B. "Artikel".
     *                       Wird im Layout zu "(Titel) - Materialverleih Verwaltung" ergänzt;
     *                       bei leerem String bleibt es beim reinen "Materialverleih Verwaltung".
     */
    public static function page(string $view, array $data = [], string $activeNav = '', string $title = ''): string
    {
        $content = self::renderFile($view, $data);

        return self::renderFile('layout', [
            'content' => $content,
            'activeNav' => $activeNav,
            'title' => $title,
            'flashSuccess' => Flash::consumeSuccess(),
            'flashError' => Flash::consumeError(),
            // Wird von AuthGuard vor jedem geschützten Controller-Aufruf
            // gesetzt; auf öffentlichen Seiten (Login) bleibt es null, die
            // Navigation im Layout blendet sich dann komplett aus.
            'currentUser' => Auth\CurrentUser::get(),
            // Nicht-null, solange der angemeldete Benutzer tatsächlich ein
            // von einem Administrator imitierter Zielbenutzer ist (siehe
            // Auth\AuthGuard::guard()) — steuert den Hinweis-Banner in
            // layout.php.
            'impersonator' => Auth\ImpersonationContext::get(),
            // Wird einmal pro Request in bootstrap.php gesetzt, für die
            // Impressum-/Datenschutz-Links im Footer (siehe layout.php).
            'legalSettings' => LegalFooterLinks::get(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private static function renderFile(string $view, array $data): string
    {
        $path = self::$viewsPath . '/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException("View \"{$view}\" wurde unter \"{$path}\" nicht gefunden.");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;

        return (string) ob_get_clean();
    }
}

/** Kleiner HTML-Escaping-Helfer, global verfügbar für alle Views. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
