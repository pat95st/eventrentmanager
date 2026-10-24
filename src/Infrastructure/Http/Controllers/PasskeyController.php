<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Auth\Command\BeginPasskeyLogin;
use App\Application\Auth\Command\BeginPasskeyRegistration;
use App\Application\Auth\Command\CompletePasskeyLogin;
use App\Application\Auth\Command\CompletePasskeyRegistration;
use App\Application\Auth\Command\DeletePasskey;
use App\Application\Auth\Query\ListPasskeysForUser;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Domain\Auth\WebauthnCredential;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Auth\RecordUserSession;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * HTTP-Schicht für Passkey-/WebAuthn-Registrierung und -Anmeldung. Reine
 * "dünne" Controller-Methoden nach dem bestehenden Muster (siehe
 * AuthController/ProfileController) — sämtliche Fachlogik/Kryptografie
 * steckt in den Application/Auth-Commands bzw.
 * App\Infrastructure\WebAuthn\WebAuthnService.
 *
 * registerOptions()/registerComplete()/list()/delete() verlangen eine
 * bestehende Anmeldung (siehe Routen-Registrierung in src/bootstrap.php,
 * dort mit $auth->guard(null, …) geschützt); loginOptions()/loginComplete()
 * sind bewusst öffentlich — das ist ja gerade der Zweck von Passkey-Login
 * (siehe AuthGuard::PUBLIC_PATHS).
 */
final class PasskeyController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly RecordUserSession $recordUserSession,
    ) {
    }

    public function registerOptions(Request $request): void
    {
        $user = CurrentUser::get();

        $result = $this->commands->dispatch(new BeginPasskeyRegistration(
            userId: $user->id,
            rpId: $this->rpId(),
            rpName: 'Materialverleih Verwaltung',
        ));

        // Die Challenge-ID wird zusammen mit den Optionen an den Browser
        // gereicht und beim Abschluss unverändert zurückgesendet (siehe
        // registerComplete()) — sie ist nur ein Zeiger auf die serverseitig
        // gespeicherte Challenge, kein Geheimnis; die eigentliche
        // Sicherheit kommt aus der Signaturprüfung gegen den in der
        // Challenge gespeicherten Zufallswert.
        Response::json(['options' => $result['options'], 'challengeId' => $result['challengeId']]);
    }

    public function registerComplete(Request $request): void
    {
        $user = CurrentUser::get();

        try {
            $credential = $this->commands->dispatch(new CompletePasskeyRegistration(
                userId: $user->id,
                challengeId: $request->input('challengeId', '') ?? '',
                clientDataJsonB64: $request->input('clientDataJSON', '') ?? '',
                attestationObjectB64: $request->input('attestationObject', '') ?? '',
                expectedOrigin: $this->origin(),
                nickname: $request->input('nickname'),
            ));
        } catch (ValidationException $e) {
            Response::json(['error' => $e->errors()['passkey'] ?? 'Registrierung fehlgeschlagen.'], 422);

            return;
        }

        assert($credential instanceof WebauthnCredential);

        Response::json(['success' => true, 'id' => $credential->id, 'nickname' => $credential->nickname]);
    }

    public function loginOptions(Request $request): void
    {
        // Bewusst kein AuthGuard::guard() (öffentliche Route) — vor dem
        // Login ist per Definition niemand angemeldet.
        $result = $this->commands->dispatch(new BeginPasskeyLogin(rpId: $this->rpId()));

        Response::json(['options' => $result['options'], 'challengeId' => $result['challengeId']]);
    }

    public function loginComplete(Request $request): void
    {
        try {
            $user = $this->commands->dispatch(new CompletePasskeyLogin(
                challengeId: $request->input('challengeId', '') ?? '',
                credentialIdB64: $request->input('credentialId', '') ?? '',
                clientDataJsonB64: $request->input('clientDataJSON', '') ?? '',
                authenticatorDataB64: $request->input('authenticatorData', '') ?? '',
                signatureB64: $request->input('signature', '') ?? '',
                expectedOrigin: $this->origin(),
            ));
        } catch (ValidationException $e) {
            Response::json(['error' => $e->errors()['passkey'] ?? 'Anmeldung fehlgeschlagen.'], 401);

            return;
        }

        // Eine erfolgreich verifizierte Passkey-Anmeldung ist ein
        // vollwertiger, eigenständiger Anmeldeweg (Besitz des
        // Authenticators + i. d. R. biometrische/PIN-Freigabe davor) —
        // genau wie beim passwortbasierten Login ohne zweiten Faktor wird
        // die Session hier direkt vollständig angemeldet, siehe
        // AuthController::login().
        Session::login($user->id);
        $this->recordUserSession->record($user->id);

        Response::json(['success' => true, 'redirect' => Session::consumeRedirectAfterLogin()]);
    }

    public function list(Request $request): void
    {
        $user = CurrentUser::get();

        $credentials = $this->queries->ask(new ListPasskeysForUser($user->id));

        Response::json([
            'passkeys' => array_map(static fn (WebauthnCredential $c) => [
                'id' => $c->id,
                'nickname' => $c->nickname,
                'createdAt' => $c->createdAt->format(DATE_ATOM),
                'lastUsedAt' => $c->lastUsedAt?->format(DATE_ATOM),
            ], $credentials),
        ]);
    }

    public function delete(Request $request): void
    {
        $user = CurrentUser::get();

        try {
            $this->commands->dispatch(new DeletePasskey(
                passkeyId: $request->param('id'),
                requestingUserId: $user->id,
            ));
        } catch (NotFoundException) {
            // Absichtlich derselbe generische Fehler wie im Handler-Kommentar
            // beschrieben — kein Hinweis, ob die ID existiert, aber einem
            // anderen Benutzer gehört.
            Response::json(['error' => 'Passkey wurde nicht gefunden.'], 404);

            return;
        }

        Response::json(['success' => true]);
    }

    /** Host ohne Port, wie von WebAuthn für rp.id/rpId verlangt (keine hart kodierte Domain, siehe Projektvorgabe). */
    private function rpId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return (string) parse_url('http://' . $host, PHP_URL_HOST);
    }

    /** Vollständige Origin (Schema + Host + ggf. Port), wie sie im Browser in clientDataJSON.origin steht. */
    private function origin(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) === '443'
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        return ($isHttps ? 'https://' : 'http://') . $host;
    }
}
