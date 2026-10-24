<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Auth\Command\CompletePasswordSetup;
use App\Application\Command\CommandBus;
use App\Domain\Auth\PasswordSetupTokenRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Auth\RecordUserSession;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use DateTimeImmutable;

/**
 * Öffentlicher (nicht angemeldeter) Endpunkt "Passwort festlegen": bedient
 * sowohl die Admin-Einladung eines Benutzers ohne Erstpasswort (siehe
 * CreateUserHandler) als auch "Passwort vergessen" (siehe
 * AuthController::forgotPassword()) — beide erzeugen denselben Token-Typ
 * (App\Domain\Auth\PasswordSetupToken) und landen hier.
 */
final class SetupPasswordController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly PasswordSetupTokenRepositoryInterface $tokens,
        private readonly RecordUserSession $recordUserSession,
    ) {
    }

    public function show(Request $request): void
    {
        $token = $request->param('token');

        if (!$this->isTokenUsable($token)) {
            echo View::render('auth/setup-password-invalid', []);

            return;
        }

        echo View::render('auth/setup-password', ['token' => $token, 'errors' => []]);
    }

    public function submit(Request $request): void
    {
        $token = $request->param('token');

        try {
            $userId = $this->commands->dispatch(new CompletePasswordSetup(
                token: $token,
                newPassword: $request->input('password', '') ?? '',
                newPasswordConfirmation: $request->input('password_confirmation', '') ?? '',
            ));
        } catch (NotFoundException) {
            echo View::render('auth/setup-password-invalid', []);

            return;
        } catch (ValidationException $e) {
            echo View::render('auth/setup-password', ['token' => $token, 'errors' => $e->errors()]);

            return;
        }

        // Der Besitz des Tokens (per E-Mail zugestellt) gilt als Nachweis
        // der E-Mail-Adresse — eine direkte Anmeldung ist hier zumutbar und
        // die deutlich reibungsärmere UX, statt zusätzlich noch einmal zum
        // Login zu schicken.
        Session::login($userId);
        $this->recordUserSession->record($userId);
        Flash::success('Passwort wurde festgelegt. Willkommen!');
        Response::redirect('/');
    }

    private function isTokenUsable(string $rawToken): bool
    {
        if ($rawToken === '') {
            return false;
        }

        $token = $this->tokens->findByTokenHash(hash('sha256', $rawToken));

        return $token !== null && $token->isUsable(new DateTimeImmutable());
    }
}
