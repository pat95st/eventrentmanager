<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Auth\Command\AuthenticatePassword;
use App\Application\Auth\Command\RequestEmailOtp;
use App\Application\Auth\Command\RequestPasswordSetup;
use App\Application\Auth\Command\VerifyTwoFactorCode;
use App\Application\Command\CommandBus;
use App\Domain\Auth\UserSessionRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Http\Auth\RecordUserSession;
use App\Infrastructure\Http\Auth\Session;
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
    ) {
    }

    public function showLogin(Request $request): void
    {
        if (Session::userId() !== null) {
            Response::redirect('/');

            return;
        }

        echo View::render('auth/login', ['errors' => [], 'email' => '']);
    }

    public function login(Request $request): void
    {
        $email = $request->input('email', '') ?? '';

        try {
            $user = $this->commands->dispatch(new AuthenticatePassword($email, $request->input('password', '') ?? ''));
        } catch (ValidationException $e) {
            echo View::render('auth/login', ['errors' => $e->errors(), 'email' => $email]);

            return;
        }

        if ($user->twoFactorMethod === TwoFactorMethod::None) {
            Session::login($user->id);
            $this->recordUserSession->record($user->id);
            Response::redirect(Session::consumeRedirectAfterLogin());

            return;
        }

        Session::startTwoFactorChallenge($user->id);

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

        try {
            $this->commands->dispatch(new VerifyTwoFactorCode($userId, $request->input('code', '') ?? ''));
        } catch (ValidationException $e) {
            echo View::render('auth/two-factor', [
                'method' => $user->twoFactorMethod,
                'errors' => $e->errors(),
                'flashSuccess' => null,
            ]);

            return;
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
