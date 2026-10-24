<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Auth\Command\AdminRevokeAllSessions;
use App\Application\Auth\Command\AdminRevokeSession;
use App\Application\Auth\Command\StartImpersonation;
use App\Application\Auth\Command\StopImpersonation;
use App\Application\Auth\Query\ListImpersonations;
use App\Application\Auth\Query\ListSessionsForUser;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\User\Command\ChangePassword;
use App\Application\User\Command\CreateUser;
use App\Application\User\Command\DeleteUser;
use App\Application\User\Command\DisableTwoFactor;
use App\Application\User\Command\UpdateUser;
use App\Application\User\Query\GetUser;
use App\Application\User\Query\ListUsers;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\Permission;
use App\Domain\User\User;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Origin;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class UserController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListUsers($page, $pageSize));

        $viewData = [
            'users' => $result->items,
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/benutzer',
            'paginationQuery' => [],
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('users/_table', $viewData);

            return;
        }

        echo View::page('users/index', $viewData, activeNav: 'users', title: 'Benutzer');
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        // Bewusst NICHT auf das Client-seitige "account_mode"-Radio
        // verlassen (das steuert nur die Anzeige/Pflichtfeld-Markierung im
        // Formular per JS) — maßgeblich ist allein, ob tatsächlich ein
        // Passwort übermittelt wurde. Ein leeres Passwort bedeutet "Per
        // E-Mail einladen" (siehe CreateUserHandler).
        $command = new CreateUser(
            email: $request->input('email', '') ?? '',
            firstName: $request->input('first_name', '') ?? '',
            lastName: $request->input('last_name', '') ?? '',
            password: $request->input('password', '') ?? '',
            permissions: $request->post['permissions'] ?? [],
            mustSetupTwoFactor: $request->input('must_setup_two_factor') !== null,
            originUrl: Origin::current(),
        );

        try {
            /** @var array{id: string, inviteEmailSent: bool|null} $result */
            $result = $this->commands->dispatch($command);

            if ($result['inviteEmailSent'] === false) {
                Flash::error('Benutzer wurde angelegt, aber die Einladungs-E-Mail konnte nicht versendet werden. Bitte den Zugang manuell nachreichen (z. B. über "Passwort" in der Benutzerliste).');
            } elseif ($result['inviteEmailSent'] === true) {
                Flash::success('Benutzer wurde angelegt. Eine E-Mail zum Festlegen des Passworts wurde versendet.');
            } else {
                Flash::success('Benutzer wurde angelegt.');
            }

            Response::redirect('/benutzer');
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromRequest($request));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $user = $this->queries->ask(new GetUser($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($user, [], [], $user->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateUser(
            id: $id,
            email: $request->input('email', '') ?? '',
            firstName: $request->input('first_name', '') ?? '',
            lastName: $request->input('last_name', '') ?? '',
            permissions: $request->post['permissions'] ?? [],
            isActive: $request->input('is_active') !== null,
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Benutzer wurde aktualisiert.');
            Response::redirect('/benutzer');
        } catch (ValidationException $e) {
            $user = $this->queries->ask(new GetUser($id));
            $this->renderForm($user, $e->errors(), $this->oldFromRequest($request), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        if (CurrentUser::get()?->id === $id) {
            Flash::error('Sie können Ihr eigenes Benutzerkonto nicht löschen.');
            Response::redirect('/benutzer');

            return;
        }

        try {
            $this->commands->dispatch(new DeleteUser($id));
            Flash::success('Benutzer wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/benutzer');
    }

    public function showResetPassword(Request $request): void
    {
        $id = $request->param('id');

        try {
            $user = $this->queries->ask(new GetUser($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('users/reset-password', [
            'user' => $user,
            'errors' => [],
        ], activeNav: 'users', title: 'Passwort zurücksetzen');
    }

    public function resetPassword(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new ChangePassword($id, $request->input('new_password', '') ?? ''));
            Flash::success('Passwort wurde zurückgesetzt. Der Benutzer muss es beim nächsten Login ändern.');
            Response::redirect('/benutzer');
        } catch (ValidationException $e) {
            $user = $this->queries->ask(new GetUser($id));
            echo View::page('users/reset-password', [
                'user' => $user,
                'errors' => $e->errors(),
            ], activeNav: 'users', title: 'Passwort zurücksetzen');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function resetTwoFactor(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DisableTwoFactor($id));
            Flash::success('Zweiter Faktor wurde zurückgesetzt.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/benutzer');
    }

    /** Zeigt einem Administrator, welche Sitzungen dieses Benutzers aktuell aktiv sind, siehe profile/sessions.php für die Selbstbedienungs-Variante. */
    public function showSessions(Request $request): void
    {
        $id = $request->param('id');

        try {
            $user = $this->queries->ask(new GetUser($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        // session_id() der aktuellen Admin-Sitzung: markiert "Diese Sitzung"
        // nur in dem (eher seltenen) Fall, dass ein Administrator sich hier
        // selbst betrachtet — für alle anderen Benutzer bleibt isCurrent
        // immer false, was korrekt ist.
        $sessions = $this->queries->ask(new ListSessionsForUser($user->id, session_id() ?: ''));

        echo View::page('users/sessions', [
            'targetUser' => $user,
            'sessions' => $sessions,
        ], activeNav: 'users', title: 'Sitzungen: ' . $user->displayName());
    }

    public function revokeSession(Request $request): void
    {
        $id = $request->param('id');
        $sessionId = $request->param('sessionId');

        try {
            $this->commands->dispatch(new AdminRevokeSession($sessionId, $id));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/benutzer/' . $id . '/sitzungen');

            return;
        }

        // Beendet ein Administrator hier zufällig die eigene, gerade
        // benutzte Sitzung (z. B. weil er seinen eigenen Account über die
        // Benutzerverwaltung verwaltet), gilt dasselbe wie im eigenen Profil
        // (siehe ProfileController::revokeSession()): sofortige
        // Selbstabmeldung, statt in einem halb widerrufenen Zustand hängen
        // zu bleiben.
        if ($sessionId === session_id()) {
            Session::logout();
            Response::redirect('/login');

            return;
        }

        Flash::success('Sitzung wurde beendet.');
        Response::redirect('/benutzer/' . $id . '/sitzungen');
    }

    public function revokeAllSessions(Request $request): void
    {
        $id = $request->param('id');

        $this->commands->dispatch(new AdminRevokeAllSessions($id));

        // Siehe revokeSession(): verwaltet ein Administrator hier zufällig
        // sein EIGENES Konto, war die soeben beendete eigene, gerade
        // benutzte Sitzung zwangsläufig auch dabei — sofortige
        // Selbstabmeldung, statt in einem halb widerrufenen Zustand hängen
        // zu bleiben.
        if (CurrentUser::get()?->id === $id) {
            Session::logout();
            Response::redirect('/login');

            return;
        }

        Flash::success('Alle Sitzungen dieses Benutzers wurden beendet.');
        Response::redirect('/benutzer/' . $id . '/sitzungen');
    }

    /**
     * Startet eine Impersonation: der angemeldete Administrator agiert ab
     * sofort mit den Rechten des Zielbenutzers (siehe Session::
     * startImpersonation()). Rechte-/Guard-seitig ist das ein normaler,
     * durch Permission::Administration geschützter Aufruf — erst NACH dem
     * Umschalten der Session hat der Administrator nur noch die Rechte des
     * Zielbenutzers.
     */
    public function startImpersonation(Request $request): void
    {
        $targetId = $request->param('id');
        $admin = CurrentUser::get();

        if ($admin === null) {
            Response::redirect('/login');

            return;
        }

        if (Session::isImpersonating()) {
            Flash::error('Es läuft bereits eine Impersonation. Bitte zuerst zum eigenen Konto zurückkehren.');
            Response::redirect('/benutzer');

            return;
        }

        try {
            /** @var array{id: string} $result */
            $result = $this->commands->dispatch(new StartImpersonation($admin->id, $targetId));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/benutzer');

            return;
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/benutzer');

            return;
        }

        Session::startImpersonation($result['id'], $admin->id, $targetId);
        Flash::success('Sie sind jetzt als dieser Benutzer angemeldet.');
        Response::redirect('/');
    }

    /**
     * Beendet eine laufende Impersonation und stellt die ursprüngliche
     * Administrator-Identität wieder her. Über AuthGuard::IMPERSONATION_STOP_PATH
     * bewusst OHNE Rechteprüfung erreichbar (der aktuell wirksame Benutzer
     * während der Impersonation hat ja möglicherweise gar kein
     * Administration-Recht mehr).
     */
    public function stopImpersonation(Request $request): void
    {
        $impersonationId = Session::impersonationId();
        $adminUserId = Session::stopImpersonation();

        if ($impersonationId !== null) {
            $this->commands->dispatch(new StopImpersonation($impersonationId));
        }

        if ($adminUserId === null) {
            // Es lief gar keine Impersonation (z. B. doppelter Klick) —
            // nichts zu tun, aber auch kein Fehlerfall.
            Response::redirect('/');

            return;
        }

        Flash::success('Zurück zu Ihrem eigenen Konto.');
        Response::redirect('/benutzer');
    }

    /** Impersonations-Protokoll: wer hat sich wann als wen ausgegeben (siehe "Audit-Log"-Entscheidung). */
    public function listImpersonations(Request $request): void
    {
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListImpersonations($page, $pageSize));

        $viewData = [
            'rows' => $result->items,
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/benutzer/impersonationen',
            'paginationQuery' => [],
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('users/impersonations-table', $viewData);

            return;
        }

        echo View::page('users/impersonations', $viewData, activeNav: 'users', title: 'Impersonations-Protokoll');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?User $user, array $errors, array $old, ?string $editingId = null): void
    {
        echo View::page('users/form', [
            'user' => $user,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
            'allPermissions' => Permission::all(),
        ], activeNav: 'users', title: $user !== null ? 'Benutzer bearbeiten' : 'Neuer Benutzer');
    }

    /** @return array<string, mixed> */
    private function oldFromRequest(Request $request): array
    {
        return [
            'email' => $request->input('email', ''),
            'first_name' => $request->input('first_name', ''),
            'last_name' => $request->input('last_name', ''),
            'permissions' => $request->post['permissions'] ?? [],
            'is_active' => $request->input('is_active') !== null,
            'must_setup_two_factor' => $request->input('must_setup_two_factor') !== null,
        ];
    }
}
