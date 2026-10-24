<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Domain\Api\ApiTokenRepositoryInterface;
use App\Domain\Api\IpAllowlist;
use App\Domain\Settings\ApiSettingsRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use DateTimeImmutable;

/**
 * Wrappt die /api/v1/*-Routen-Handler mit einer Token-Prüfung, analog zu
 * AuthGuard::guard() — aber bewusst ohne jede Berührung von Session/
 * CurrentUser: die REST-API ist zustandslos und wird von externen Systemen
 * angesprochen, nicht von im Browser angemeldeten Personen. Prüfreihenfolge:
 * 1. globaler Schalter (ApiSettings::$enabled) — aus, antwortet JEDE Route
 *    mit 404, um die Existenz der API nicht preiszugeben,
 * 2. "Authorization: Bearer <token>"-Header vorhanden und bekannt,
 * 3. Token ist nicht deaktiviert,
 * 4. Client-IP ist in der Allowlist des Tokens (falls eine hinterlegt ist).
 */
final class ApiTokenGuard
{
    public function __construct(
        private readonly ApiSettingsRepositoryInterface $apiSettings,
        private readonly ApiTokenRepositoryInterface $apiTokens,
    ) {
    }

    public function guard(callable $handler): callable
    {
        return function (Request $request) use ($handler): void {
            $settings = $this->apiSettings->get();
            if (!$settings->enabled) {
                Response::json(['error' => 'Nicht gefunden.'], 404);

                return;
            }

            $rawToken = $this->bearerToken();
            if ($rawToken === null) {
                Response::json(['error' => 'Es wurde kein gültiger "Authorization: Bearer <token>"-Header mitgeschickt.'], 401);

                return;
            }

            $token = $this->apiTokens->findByTokenHash(hash('sha256', $rawToken));
            if ($token === null || !$token->enabled) {
                Response::json(['error' => 'Ungültiges oder deaktiviertes Token.'], 401);

                return;
            }

            $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            if (!IpAllowlist::matches($clientIp, $token->ipAllowlist)) {
                Response::json(['error' => 'Der Zugriff von dieser IP-Adresse ist für dieses Token nicht erlaubt.'], 403);

                return;
            }

            $this->apiTokens->touchLastUsed($token->id, new DateTimeImmutable());

            $handler($request);
        };
    }

    /**
     * Liest den Bearer-Token aus dem Authorization-Header. Manche
     * PHP-Setups (v. a. Apache+mod_php ohne CGIPassAuth) liefern diesen
     * Header nicht unter HTTP_AUTHORIZATION, sondern nur unter
     * REDIRECT_HTTP_AUTHORIZATION — daher der Fallback.
     */
    private function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}
