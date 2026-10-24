<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\User\Command\UpdateLocale;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Locale;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Sprachumschalter im Header/der mobilen Navigation (siehe layout.php,
 * "DE | EN"). Bewusst als eigener, einfacher POST-Endpunkt ohne
 * Anmeldepflicht (siehe AuthGuard::PUBLIC_PATHS-analoge Registrierung in
 * bootstrap.php), damit auch die Login-Seite umschaltbar ist. Für bereits
 * angemeldete Benutzer wird zusätzlich die dauerhafte Präferenz in der
 * Datenbank aktualisiert (UpdateLocale-UseCase), für alle anderen bleibt es
 * bei der Session (siehe Locale::rememberInSession()).
 *
 * WICHTIG: Weil diese Route ABSICHTLICH nicht über AuthGuard::guard() läuft
 * (siehe Klassendoc oben), ist App\Infrastructure\Http\Auth\CurrentUser HIER
 * NIE gesetzt — die einzige Stelle, die CurrentUser::set() aufruft, ist
 * genau dieser Guard. Der angemeldete Benutzer wird daher bewusst direkt
 * über Session::userId() + UserRepositoryInterface aufgelöst, statt (wie
 * früher, fälschlich) über CurrentUser::get() — sonst würde die
 * Datenbank-Präferenz nie aktualisiert, und AuthGuard::guard() würde beim
 * nächsten Request per Locale::setFromUser() die gerade erst gewählte
 * Sprache sofort wieder mit der alten, nie aktualisierten DB-Präferenz
 * überschreiben (die Umschaltung wirkte dadurch nur für angemeldete
 * Benutzer nicht).
 */
final class LocaleController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function switch(Request $request): void
    {
        $locale = $request->input('locale', '') ?? '';

        if (Locale::isSupported($locale)) {
            Locale::rememberInSession($locale);

            $userId = Session::userId();
            if ($userId !== null) {
                $user = $this->users->findById($userId);
                if ($user !== null) {
                    $this->commands->dispatch(new UpdateLocale($user->id, $locale));
                }
            }
        }

        $redirect = $request->input('redirect', '') ?? '';
        if ($redirect === '' || !str_starts_with($redirect, '/')) {
            $redirect = $_SERVER['HTTP_REFERER'] ?? '/';
        }

        Response::redirect($redirect);
    }
}
