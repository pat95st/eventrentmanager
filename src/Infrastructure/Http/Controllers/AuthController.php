<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Auth\Command\AuthenticateLdap;
use App\Application\Auth\Command\AuthenticatePassword;
use App\Application\Auth\Command\CompleteOAuthLogin;
use App\Application\Auth\Command\RequestEmailOtp;
use App\Application\Auth\Command\RequestPasswordSetup;
use App\Application\Auth\Command\VerifyTwoFactorCode;
use App\Application\Auth\OAuthAuthorizationRequest;
use App\Application\Auth\TrustedDeviceService;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetExternalLoginSettings;
use App\Domain\Auth\UserSessionRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Http\Auth\RecordUserSession;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Auth\TrustedDeviceCookie;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Origin;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class AuthController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly UserRepositoryInterface $users,
        private readonly UserSessionRepositoryInterface $userSessions,
        private readonly RecordUserSession $recordUserSession,
        private readonly TrustedDeviceService $trustedDevices,
        private readonly QueryBus $queries,
    ) {
    }

    public function showLogin(Request $request): void
    {
        if (Session::userId() !== null) {
            Response::redirect('/');

            return;
        }

        // Hinweis z. B. nach automatischer Abmeldung wegen Inaktivität (AuthGuard).
        $this->renderLogin([], '', false, Flash::consumeError());
    }

    public function login(Request $request): void
    {
        $email = $request->input('email', '') ?? '';
        $password = $request->input('password', '') ?? '';
        $trustDevice = ($request->input('trust_device', '') ?? '') === '1';

        try {
            $user = $this->commands->dispatch(new AuthenticatePassword($email, $password));
        } catch (ValidationException $e) {
            // Local accounts come first, so a broken directory never locks
            // out the administrators. Only if the local check fails, the
            // same input is tried against LDAP (if configured).
            try {
                if (!$this->externalLoginSettings()->ldapActive()) {
                    throw $e;
                }
                $user = $this->commands->dispatch(new AuthenticateLdap($email, $password));
            } catch (ValidationException $e) {
                $this->renderLogin($e->errors(), $email, $trustDevice);

                return;
            }
        }

        $this->continueAfterFirstFactor($user, $trustDevice, skipTwoFactor: false);
    }

    /** Sends the browser to the OAuth / OpenID Connect identity provider. */
    public function startOAuthLogin(Request $request): void
    {
        $settings = $this->externalLoginSettings();
        if (Session::userId() !== null || !$settings->oauthActive()) {
            Response::redirect(Session::userId() !== null ? '/' : '/login');

            return;
        }

        $authorization = OAuthAuthorizationRequest::create($settings, $this->oauthRedirectUri());
        Session::startOAuthLogin($authorization->state, $authorization->codeVerifier);

        Response::redirect($authorization->url);
    }

    /** The identity provider redirects back to this address with "code" and "state". */
    public function oauthCallback(Request $request): void
    {
        // "state" ties the answer to the login this browser session started
        // (protects against forged callbacks / login CSRF).
        $codeVerifier = Session::consumeOAuthLogin($request->query('state', '') ?? '');
        $code = $request->query('code', '') ?? '';

        if ($codeVerifier === null || $code === '' || $request->query('error') !== null) {
            Flash::error(t('auth.login.error_oauth_failed'));
            Response::redirect('/login');

            return;
        }

        try {
            $user = $this->commands->dispatch(new CompleteOAuthLogin($code, $this->oauthRedirectUri(), $codeVerifier));
        } catch (ValidationException $e) {
            Flash::error($e->errors()['credentials'] ?? t('auth.login.error_oauth_failed'));
            Response::redirect('/login');

            return;
        }

        $this->continueAfterFirstFactor($user, false, skipTwoFactor: $this->externalLoginSettings()->oauthSkipTwoFactor);
    }

    /** Must be registered as redirect URI at the identity provider (shown on the settings page). */
    private function oauthRedirectUri(): string
    {
        return Origin::current() . '/login/oauth/callback';
    }

    private function externalLoginSettings(): ExternalLoginSettings
    {
        return $this->queries->ask(new GetExternalLoginSettings());
    }

    /** @param array<string, string> $errors */
    private function renderLogin(array $errors, string $email, bool $trustDevice, ?string $notice = null): void
    {
        $settings = $this->externalLoginSettings();

        echo View::render('auth/login', [
            'errors' => $errors,
            'email' => $email,
            'trustDevice' => $trustDevice,
            'notice' => $notice,
            'ldapEnabled' => $settings->ldapActive(),
            'oauthLabel' => $settings->oauthActive() ? $settings->oauthLabel : null,
        ]);
    }

    /**
     * The first factor (local password, LDAP password or OAuth) is verified:
     * either the session is logged in right away or the second factor is
     * requested.
     */
    private function continueAfterFirstFactor(User $user, bool $trustDevice, bool $skipTwoFactor): void
    {
        // Ohne zweiten Faktor gibt es nichts zu überspringen — der Haken
        // bleibt dann wirkungslos (das Passwort ist immer Pflicht).
        // Mit zweitem Faktor: Ist dieses Gerät für genau diesen Benutzer
        // noch vertraut (Cookie, max. 10 Tage), entfällt die Code-Abfrage.
        if (
            $skipTwoFactor
            || $user->twoFactorMethod === TwoFactorMethod::None
            || $this->trustedDevices->isTrusted(TrustedDeviceCookie::read(), $user->id)
        ) {
            Session::login($user->id);
            $this->recordUserSession->record($user->id);
            Response::redirect(Session::consumeRedirectAfterLogin());

            return;
        }

        Session::startTwoFactorChallenge($user->id, $trustDevice);

        if ($user->twoFactorMethod === TwoFactorMethod::Email) {
            $this->commands->dispatch(new RequestEmailOtp($user->id));
        }

        Response::redirect('/login/zwei-faktor');
    }

    public function showTwoFactor(Request $request): void
    {
        $userId = Session::pendingTwoFactorUserId();
        if ($userId === null) {
            Response::redirect('/login');

            return;
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            Session::logout();
            Response::redirect('/login');

            return;
        }

        echo View::render('auth/two-factor', [
            'method' => $user->twoFactorMethod,
            'errors' => [],
            'flashSuccess' => Flash::consumeSuccess(),
            'trustDevice' => Session::pendingTrustDevice(),
        ]);
    }

    public function verifyTwoFactor(Request $request): void
    {
        $userId = Session::pendingTwoFactorUserId();
        if ($userId === null) {
            Response::redirect('/login');

            return;
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            Session::logout();
            Response::redirect('/login');

            return;
        }

        $trustDevice = ($request->input('trust_device', '') ?? '') === '1';

        try {
            $this->commands->dispatch(new VerifyTwoFactorCode($userId, $request->input('code', '') ?? ''));
        } catch (ValidationException $e) {
            echo View::render('auth/two-factor', [
                'method' => $user->twoFactorMethod,
                'errors' => $e->errors(),
                'flashSuccess' => null,
                'trustDevice' => $trustDevice,
            ]);

            return;
        }

        if ($trustDevice) {
            // Ein evtl. vorhandenes älteres Token dieses Browsers ersetzen.
            $this->trustedDevices->forget(TrustedDeviceCookie::read());
            TrustedDeviceCookie::write($this->trustedDevices->issue(
                $userId,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ));
        }

        Session::login($userId);
        $this->recordUserSession->record($userId);
        Response::redirect(Session::consumeRedirectAfterLogin());
    }

    public function resendEmailCode(Request $request): void
    {
        $userId = Session::pendingTwoFactorUserId();
        if ($userId === null) {
            Response::redirect('/login');

            return;
        }

        $user = $this->users->findById($userId);
        if ($user !== null && $user->twoFactorMethod === TwoFactorMethod::Email) {
            $this->commands->dispatch(new RequestEmailOtp($userId));
            Flash::success(t('auth.two_factor.resend_success'));
        }

        Response::redirect('/login/zwei-faktor');
    }

    public function logout(Request $request): void
    {
        // Session-ID VOR Session::logout() sichern (dort wird sie per
        // session_regenerate_id() erneuert) — nur so lässt sich die
        // zugehörige user_sessions-Zeile eindeutig entfernen und die
        // Sitzung taucht nicht länger unter "Aktive Sitzungen" auf.
        Session::ensureStarted();

        // Abmelden während einer Impersonation (Nutzerwunsch): nicht komplett
        // abmelden, sondern zur eigenen Sitzung des Administrators zurück.
        if (Session::isImpersonating()) {
            $impersonationId = Session::impersonationId();
            Session::stopImpersonation();
            if ($impersonationId !== null) {
                $this->commands->dispatch(new \App\Application\Auth\Command\StopImpersonation($impersonationId));
            }
            \App\Infrastructure\Http\Flash::success('Abmeldung als ausgegebener Benutzer – zurück zu Ihrem eigenen Konto.');
            Response::redirect('/benutzer');

            return;
        }

        $sessionId = session_id();

        Session::logout();

        if ($sessionId !== false && $sessionId !== '') {
            $this->userSessions->deleteById($sessionId);
        }

        Response::redirect('/login');
    }

    public function showForgotPassword(Request $request): void
    {
        if (Session::userId() !== null) {
            Response::redirect('/');

            return;
        }

        echo View::render('auth/forgot-password', ['submitted' => false, 'email' => '']);
    }

    /**
     * Zeigt absichtlich IMMER dieselbe generische Erfolgsmeldung, egal ob
     * die E-Mail-Adresse einem Konto zugeordnet werden konnte (siehe
     * RequestPasswordSetupHandler) — verhindert User-Enumeration über dieses
     * Formular.
     */
    public function forgotPassword(Request $request): void
    {
        $email = $request->input('email', '') ?? '';

        $this->commands->dispatch(new RequestPasswordSetup($email, Origin::current()));

        echo View::render('auth/forgot-password', ['submitted' => true, 'email' => '']);
    }
}
