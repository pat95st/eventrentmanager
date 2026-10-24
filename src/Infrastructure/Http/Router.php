<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, paramNames: string[], handler: callable}> */
    private array $routes = [];

    /** Zustandsändernde HTTP-Methoden, für die vor dem Routen-Handler ein gültiges CSRF-Token verlangt wird. */
    private const CSRF_PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Pfade, die trotz zustandsändernder Methode OHNE CSRF-Token erreichbar
     * bleiben müssen: die Passkey-Anmeldung (loginOptions/loginComplete,
     * siehe PasskeyController) ist ein alternativer, VOR jeder Anmeldung
     * liegender Login-Weg — wie ein klassisches "Login-Formular" kann sie
     * sich nicht auf ein bereits authentifiziertes, langlebiges Session-Token
     * verlassen (WebAuthn selbst liefert die eigentliche
     * Fälschungssicherheit über die pro Versuch neu generierte,
     * serverseitig geprüfte Challenge, siehe WebAuthnService). Der normale
     * Passwort-Login (POST /login) ist BEWUSST NICHT hier aufgeführt: die
     * Login-Seite startet die Session bereits beim Aufruf (siehe
     * AuthController::showLogin() -> Session::userId() -> ensureStarted())
     * und kann daher ein session-gebundenes Token ausliefern — schützt
     * zusätzlich vor Login-CSRF.
     */
    private const CSRF_EXEMPT_PATHS = [
        '/passkeys/login/options',
        '/passkeys/login/complete',
    ];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    // Die folgenden drei WebDAV/CalDAV-Methoden (RFC 4918/4791) werden
    // ausschließlich vom CalDAV-Server (CalDavController) genutzt. Sie
    // tauchen bewusst NICHT in CSRF_PROTECTED_METHODS auf: CalDAV-Clients
    // schicken kein CSRF-Token mit (stateless Basic Auth, kein Browser-
    // Formular) — der Schutz dort ist CalDavAuthGuard, nicht CSRF.
    public function propfind(string $path, callable $handler): void
    {
        $this->add('PROPFIND', $path, $handler);
    }

    public function report(string $path, callable $handler): void
    {
        $this->add('REPORT', $path, $handler);
    }

    public function options(string $path, callable $handler): void
    {
        $this->add('OPTIONS', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $paramNames = [];
        $pattern = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];

            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $path);

        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $pattern . '$#',
            'paramNames' => $paramNames,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): void
    {
        // Zentrale CSRF-Prüfung VOR jedem Routen-Handler (statt verstreut in
        // einzelnen Controllern), siehe CSRF_PROTECTED_METHODS/CSRF_EXEMPT_PATHS
        // oben sowie Csrf.php für Token-Erzeugung/-Vergleich.
        if (in_array($request->method, self::CSRF_PROTECTED_METHODS, true)
            && !in_array($request->path, self::CSRF_EXEMPT_PATHS, true)
        ) {
            $this->enforceCsrf($request);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if (preg_match($route['pattern'], $request->path, $matches) === 1) {
                $params = [];
                foreach ($route['paramNames'] as $name) {
                    $params[$name] = $matches[$name];
                }
                $request->routeParams = $params;

                ($route['handler'])($request);

                return;
            }
        }

        Response::notFound("Für \"{$request->path}\" wurde keine passende Route gefunden.");
    }

    /**
     * Bricht mit HTTP 419 ab, wenn kein gültiges, session-gebundenes
     * CSRF-Token mitgeschickt wurde — entweder als Formularfeld
     * "csrf_token" (klassische HTML-Formulare) oder als Header
     * "X-CSRF-Token" (fetch()-basierte Aktionen, siehe layout.php für
     * "window.CSRF_TOKEN"). AJAX-Anfragen (erkannt an
     * X-Requested-With/Accept, siehe wantsJson()) erhalten eine
     * JSON-Fehlermeldung, klassische Formular-Posts eine Flash-Meldung samt
     * Redirect zurück zur aufrufenden Seite (POST/Redirect/GET-Muster,
     * siehe Flash.php).
     */
    private function enforceCsrf(Request $request): void
    {
        $submitted = $request->post['csrf_token'] ?? null;
        if (!is_string($submitted) || $submitted === '') {
            $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            $submitted = is_string($header) ? $header : '';
        }

        if (Csrf::verify($submitted)) {
            return;
        }

        $message = 'Sicherheitsprüfung fehlgeschlagen. Bitte versuche es erneut.';

        if ($this->wantsJson()) {
            Response::json(['error' => $message], 419);

            return;
        }

        Flash::error($message);
        Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    /** Unterscheidet fetch()-/AJAX-Aufrufe von klassischen Formular-Posts, siehe enforceCsrf(). */
    private function wantsJson(): bool
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (is_string($requestedWith) && strcasecmp($requestedWith, 'XMLHttpRequest') === 0) {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return is_string($accept) && str_contains($accept, 'application/json');
    }
}
