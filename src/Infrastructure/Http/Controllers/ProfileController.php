<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Auth\Command\RevokeOtherSessions;
use App\Application\Auth\Command\RevokeSession;
use App\Application\Auth\Query\ListPasskeysForUser;
use App\Application\Auth\Query\ListSessionsForUser;
use App\Application\AddressBook\Command\CreateCardDavCredential;
use App\Application\AddressBook\Command\CreatedCardDavCredential;
use App\Application\AddressBook\Command\DeleteCardDavCredential;
use App\Application\AddressBook\Query\ListCardDavCredentialsForUser;
use App\Application\Calendar\Command\CreateCalDavCredential;
use App\Application\Calendar\Command\CreatedCalDavCredential;
use App\Application\Calendar\Command\DeleteCalDavCredential;
use App\Application\Calendar\Query\ListCalDavCredentialsForUser;
use App\Application\Calendar\Query\ListCalendarsForUser;
use App\Application\Command\CommandBus;
use App\Application\Push\Command\SubscribeToPush;
use App\Application\Push\Command\UnsubscribeFromPush;
use App\Application\Push\Query\ListPushSubscriptionsForUser;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetCompanySettings;
use App\Application\Settings\Query\GetPushSettings;
use App\Application\User\Command\ChangeOwnPassword;
use App\Application\User\Command\DisableTwoFactor;
use App\Application\User\Command\EnableEmailOtp;
use App\Application\User\Command\EnableTotp;
use App\Application\User\Command\UpdateDefaultPageSize;
use App\Application\User\Command\UpdateLocale;
use App\Application\User\Command\UpdateNotifyOnNewReceiptEmail;
use App\Domain\AddressBook\AddressBook;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Push\PushSettings;
use App\Domain\User\User;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\CalDav\MobileConfigBuilder;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Locale;
use App\Infrastructure\Http\Origin;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Shared\Totp;
use function App\Infrastructure\Http\t;

final class ProfileController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly MobileConfigBuilder $mobileConfigBuilder,
    ) {
    }

    public function showPasswordForm(Request $request): void
    {
        $user = CurrentUser::get();

        echo View::page('profile/password', [
            'errors' => [],
            'forced' => $user?->mustChangePassword ?? false,
        ], activeNav: 'profile', title: t('profile.password.title'));
    }

    public function changePassword(Request $request): void
    {
        $user = CurrentUser::get();

        try {
            $this->commands->dispatch(new ChangeOwnPassword(
                userId: $user->id,
                currentPassword: $request->input('current_password', '') ?? '',
                newPassword: $request->input('new_password', '') ?? '',
            ));
        } catch (ValidationException $e) {
            echo View::page('profile/password', [
                'errors' => $e->errors(),
                'forced' => $user->mustChangePassword,
            ], activeNav: 'profile', title: t('profile.password.title'));

            return;
        }

        Flash::success(t('profile.password.flash.changed'));
        Response::redirect('/');
    }

    public function showSecurity(Request $request): void
    {
        $user = CurrentUser::get();

        $pendingSecret = Session::pendingTotpSecret();
        if ($pendingSecret === null) {
            $pendingSecret = Totp::generateSecret();
            Session::setPendingTotpSecret($pendingSecret);
        }

        echo View::page('profile/security', [
            'twoFactorMethod' => $user->twoFactorMethod,
            'pendingTotpSecret' => $pendingSecret,
            'provisioningUri' => Totp::provisioningUri($pendingSecret, $user->email),
            'errors' => [],
            'passkeys' => $this->queries->ask(new ListPasskeysForUser($user->id)),
        ], activeNav: 'profile', title: t('profile.security.title'));
    }

    public function enableTotp(Request $request): void
    {
        $user = CurrentUser::get();
        $secret = Session::pendingTotpSecret();

        if ($secret === null) {
            Flash::error(t('profile.security.flash.setup_expired'));
            Response::redirect('/profil/sicherheit');

            return;
        }

        try {
            $this->commands->dispatch(new EnableTotp($user->id, $secret, $request->input('code', '') ?? ''));
        } catch (ValidationException $e) {
            echo View::page('profile/security', [
                'twoFactorMethod' => $user->twoFactorMethod,
                'pendingTotpSecret' => $secret,
                'provisioningUri' => Totp::provisioningUri($secret, $user->email),
                'errors' => $e->errors(),
                'passkeys' => $this->queries->ask(new ListPasskeysForUser($user->id)),
            ], activeNav: 'profile', title: t('profile.security.title'));

            return;
        }

        Session::clearPendingTotpSecret();
        Flash::success(t('profile.security.flash.totp_enabled'));
        Response::redirect('/profil/sicherheit');
    }

    public function enableEmailOtp(Request $request): void
    {
        $user = CurrentUser::get();
        $this->commands->dispatch(new EnableEmailOtp($user->id));

        Flash::success(t('profile.security.flash.email_otp_enabled'));
        Response::redirect('/profil/sicherheit');
    }

    public function disableTwoFactor(Request $request): void
    {
        $user = CurrentUser::get();
        $this->commands->dispatch(new DisableTwoFactor($user->id));

        Flash::success(t('profile.security.flash.disabled'));
        Response::redirect('/profil/sicherheit');
    }

    public function showSessions(Request $request): void
    {
        $user = CurrentUser::get();

        echo View::page('profile/sessions', [
            'sessions' => $this->queries->ask(new ListSessionsForUser($user->id, session_id() ?: '')),
        ], activeNav: 'profile', title: t('profile.sessions.title'));
    }

    public function revokeSession(Request $request): void
    {
        $user = CurrentUser::get();
        $sessionId = $request->param('id');

        try {
            $this->commands->dispatch(new RevokeSession($sessionId, $user->id));
        } catch (NotFoundException) {
            Flash::error(t('profile.sessions.flash.not_found'));
            Response::redirect('/profil/sitzungen');

            return;
        }

        // Die eigene, gerade benutzte Sitzung zu beenden ist gleichbedeutend
        // mit einer bewussten Selbstabmeldung — sonst bliebe der aktuelle
        // Request in einem halb widerrufenen Zustand hängen (Cookie/Session
        // noch lokal gültig, aber die user_sessions-Zeile bereits weg, was
        // beim nächsten AuthGuard-Durchlauf ohnehin zum Logout führen würde).
        if ($sessionId === session_id()) {
            Session::logout();
            Response::redirect('/login');

            return;
        }

        Flash::success(t('profile.sessions.flash.revoked'));
        Response::redirect('/profil/sitzungen');
    }

    public function revokeOtherSessions(Request $request): void
    {
        $user = CurrentUser::get();

        $this->commands->dispatch(new RevokeOtherSessions($user->id, session_id() ?: ''));

        Flash::success(t('profile.sessions.flash.others_revoked'));
        Response::redirect('/profil/sitzungen');
    }

    public function showDisplaySettings(Request $request): void
    {
        $user = CurrentUser::get();

        echo View::page('profile/display', $this->displaySettingsViewData($user, []), activeNav: 'profile', title: t('profile.display.title'));
    }

    public function updateDisplaySettings(Request $request): void
    {
        $user = CurrentUser::get();
        $locale = $request->input('locale', '') ?? '';

        try {
            $this->commands->dispatch(new UpdateDefaultPageSize(
                userId: $user->id,
                pageSize: (int) ($request->input('default_page_size', '') ?? 0),
            ));
            $this->commands->dispatch(new UpdateLocale(
                userId: $user->id,
                locale: $locale,
            ));
            $this->commands->dispatch(new UpdateNotifyOnNewReceiptEmail(
                userId: $user->id,
                notify: $request->input('notify_on_new_receipt_email') !== null,
            ));
        } catch (ValidationException $e) {
            echo View::page('profile/display', $this->displaySettingsViewData($user, $e->errors()), activeNav: 'profile', title: t('profile.display.title'));

            return;
        }

        // Aktive Sprache für den Rest DIESES Requests (Redirect-Ziel) direkt
        // übernehmen, ohne auf den nächsten Request/AuthGuard-Durchlauf zu
        // warten (siehe Locale::rememberInSession()).
        if (Locale::isSupported($locale)) {
            Locale::rememberInSession($locale);
        }

        Flash::success(t('profile.display.flash.saved'));
        Response::redirect('/profil/darstellung');
    }

    /**
     * Registriert eine im Browser per pushManager.subscribe() erzeugte
     * Push-Subscription für den aktuellen Benutzer (siehe
     * resources/views/profile/display.php). Per fetch() angesprochen, daher
     * eine JSON-Antwort (analog zu PasskeyController).
     */
    public function storePushSubscription(Request $request): void
    {
        $user = CurrentUser::get();

        try {
            $id = $this->commands->dispatch(new SubscribeToPush(
                userId: $user->id,
                endpoint: $request->input('endpoint', '') ?? '',
                p256dh: $request->input('p256dh', '') ?? '',
                auth: $request->input('auth', '') ?? '',
                userAgent: $request->input('userAgent'),
            ));
        } catch (ValidationException $e) {
            Response::json(['error' => $e->errors()['push'] ?? 'Push-Abo konnte nicht gespeichert werden.'], 422);

            return;
        }

        Response::json(['success' => true, 'id' => $id]);
    }

    /** Entfernt ein Push-Abo (Gerät/Browser) des aktuellen Benutzers, siehe resources/views/profile/display.php. */
    public function deletePushSubscription(Request $request): void
    {
        $user = CurrentUser::get();

        try {
            $this->commands->dispatch(new UnsubscribeFromPush(
                subscriptionId: $request->param('id'),
                requestingUserId: $user->id,
            ));
        } catch (NotFoundException) {
            Response::json(['error' => 'Push-Abo wurde nicht gefunden.'], 404);

            return;
        }

        Response::json(['success' => true]);
    }

    public function showCalDav(Request $request): void
    {
        $user = CurrentUser::get();

        $calendars = $this->queries->ask(new ListCalendarsForUser($user->id));
        $calendarUrls = array_map(
            fn ($calendar) => [
                'calendar' => $calendar,
                'url' => Origin::current() . '/caldav/calendars/' . $user->id . '/' . $calendar->id . '/',
            ],
            $calendars
        );

        echo View::page('profile/caldav', [
            'credentials' => $this->queries->ask(new ListCalDavCredentialsForUser($user->id)),
            'calendarUrls' => $calendarUrls,
            'discoveryUrl' => Origin::current() . '/.well-known/caldav',
            'errors' => [],
            'newRawPassword' => null,
        ], activeNav: 'profile', title: t('profile.caldav.title'));
    }

    public function createCalDavCredential(Request $request): void
    {
        $user = CurrentUser::get();
        $errors = [];
        $newRawPassword = null;

        try {
            /** @var CreatedCalDavCredential $created */
            $created = $this->commands->dispatch(new CreateCalDavCredential($user->id, $request->input('name')));
            // Das Klartext-Passwort ist ab jetzt für immer weg — wird EINMALIG
            // direkt auf der Seite angezeigt (siehe profile/caldav.php),
            // analog zum API-Token in ApiSettingsController::createToken().
            $newRawPassword = $created->rawPassword;
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        $calendars = $this->queries->ask(new ListCalendarsForUser($user->id));
        $calendarUrls = array_map(
            fn ($calendar) => [
                'calendar' => $calendar,
                'url' => Origin::current() . '/caldav/calendars/' . $user->id . '/' . $calendar->id . '/',
            ],
            $calendars
        );

        echo View::page('profile/caldav', [
            'credentials' => $this->queries->ask(new ListCalDavCredentialsForUser($user->id)),
            'calendarUrls' => $calendarUrls,
            'discoveryUrl' => Origin::current() . '/.well-known/caldav',
            'errors' => $errors,
            'newRawPassword' => $newRawPassword,
        ], activeNav: 'profile', title: t('profile.caldav.title'));
    }

    public function deleteCalDavCredential(Request $request): void
    {
        $user = CurrentUser::get();

        try {
            $this->commands->dispatch(new DeleteCalDavCredential($request->param('id'), $user->id));
            Flash::success(t('profile.caldav.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/profil/caldav');
    }

    public function showCardDav(Request $request): void
    {
        $user = CurrentUser::get();

        echo View::page('profile/carddav', [
            'credentials' => $this->queries->ask(new ListCardDavCredentialsForUser($user->id)),
            'addressBookUrls' => $this->addressBookUrlsFor($user),
            'discoveryUrl' => Origin::current() . '/.well-known/carddav',
            'errors' => [],
            'newRawPassword' => null,
        ], activeNav: 'profile', title: t('profile.carddav.title'));
    }

    public function createCardDavCredential(Request $request): void
    {
        $user = CurrentUser::get();
        $errors = [];
        $newRawPassword = null;

        try {
            /** @var CreatedCardDavCredential $created */
            $created = $this->commands->dispatch(new CreateCardDavCredential($user->id, $request->input('name')));
            // Das Klartext-Passwort ist ab jetzt für immer weg — wird EINMALIG
            // direkt auf der Seite angezeigt (siehe profile/carddav.php),
            // analog zum CalDAV-App-Passwort (createCalDavCredential()).
            $newRawPassword = $created->rawPassword;
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        echo View::page('profile/carddav', [
            'credentials' => $this->queries->ask(new ListCardDavCredentialsForUser($user->id)),
            'addressBookUrls' => $this->addressBookUrlsFor($user),
            'discoveryUrl' => Origin::current() . '/.well-known/carddav',
            'errors' => $errors,
            'newRawPassword' => $newRawPassword,
        ], activeNav: 'profile', title: t('profile.carddav.title'));
    }

    public function deleteCardDavCredential(Request $request): void
    {
        $user = CurrentUser::get();

        try {
            $this->commands->dispatch(new DeleteCardDavCredential($request->param('id'), $user->id));
            Flash::success(t('profile.carddav.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/profil/carddav');
    }

    /** @return array{addressBook: AddressBook, url: string}[] Nur die Adressbücher, für die der User die nötige Berechtigung hat (siehe AddressBook::requiredPermission()). */
    private function addressBookUrlsFor(User $user): array
    {
        $origin = Origin::current();

        $entries = [];
        foreach (AddressBook::all() as $addressBook) {
            if (!$user->has($addressBook->requiredPermission())) {
                continue;
            }

            $entries[] = [
                'addressBook' => $addressBook,
                'url' => $origin . '/carddav/addressbooks/' . $user->id . '/' . $addressBook->value . '/',
            ];
        }

        return $entries;
    }

    /**
     * Erzeugt ein neues CalDAV-App-Passwort (ausschließlich für dieses
     * Profil, siehe createCalDavCredential()) und liefert direkt ein
     * fertig konfiguriertes Apple-Konfigurationsprofil (.mobileconfig)
     * damit aus — ein POST mit Seiteneffekt (neues Credential), daher
     * bewusst kein GET-Download-Link.
     */
    public function downloadMobileConfig(Request $request): void
    {
        $user = CurrentUser::get();

        $created = $this->commands->dispatch(new CreateCalDavCredential(
            $user->id,
            t('profile.caldav.mobileconfig.credential_name', ['date' => date('d.m.Y H:i')])
        ));
        assert($created instanceof CreatedCalDavCredential);

        $companySettings = $this->queries->ask(new GetCompanySettings());
        $organizationName = trim((string) $companySettings->name) !== '' ? $companySettings->name : 'Materialverleih';

        $origin = Origin::current();
        $useSsl = str_starts_with($origin, 'https://');
        $withoutScheme = preg_replace('#^https?://#', '', $origin) ?? $origin;
        $hostAndPort = explode(':', $withoutScheme, 2);
        $host = $hostAndPort[0];
        $port = isset($hostAndPort[1]) ? (int) $hostAndPort[1] : ($useSsl ? 443 : 80);

        $mobileConfig = $this->mobileConfigBuilder->build(
            organizationName: $organizationName,
            accountDescription: t('profile.caldav.mobileconfig.account_description'),
            host: $host,
            port: $port,
            useSsl: $useSsl,
            username: $user->email,
            password: $created->rawPassword,
        );

        Response::download($mobileConfig, 'caldav.mobileconfig', 'application/x-apple-aspen-config');
    }

    /** @param array<string, string> $errors @return array<string, mixed> */
    private function displaySettingsViewData(User $user, array $errors): array
    {
        $pushSettings = $this->queries->ask(new GetPushSettings());
        assert($pushSettings instanceof PushSettings);

        return [
            'defaultPageSize' => $user->defaultPageSize,
            'allowedPageSizes' => Pagination::ALLOWED_PAGE_SIZES,
            'locale' => $user->locale,
            'notifyOnNewReceiptEmail' => $user->notifyOnNewReceiptEmail,
            'isAdministrator' => $user->isAdministrator(),
            'errors' => $errors,
            'vapidPublicKey' => $pushSettings->vapidPublicKey,
            'pushSubscriptions' => $this->queries->ask(new ListPushSubscriptionsForUser($user->id)),
            // Farbschema-Presets fürs Template (siehe profile/display.php) —
            // rein clientseitig in localStorage gespeichert (kein DB-Feld,
            // wie schon der Hell-/Dunkelmodus-Umschalter auf derselben
            // Seite), daher hier nur die statische Liste zum Rendern der
            // Auswahl-Kacheln, keine Persistenz-Logik im Controller.
            'colorSchemes' => [
                ['key' => 'blue', 'label' => t('settings.color_scheme.blue'), 'hint' => t('settings.color_scheme.default'), 'swatch' => '#2563eb'],
                ['key' => 'green', 'label' => t('settings.color_scheme.green'), 'hint' => null, 'swatch' => '#059669'],
                ['key' => 'violet', 'label' => t('settings.color_scheme.violet'), 'hint' => null, 'swatch' => '#7c3aed'],
                ['key' => 'amber', 'label' => t('settings.color_scheme.amber'), 'hint' => null, 'swatch' => '#d97706'],
                ['key' => 'rose', 'label' => t('settings.color_scheme.rose'), 'hint' => null, 'swatch' => '#e11d48'],
                ['key' => 'teal', 'label' => t('settings.color_scheme.teal'), 'hint' => null, 'swatch' => '#0d9488'],
            ],
        ];
    }
}
