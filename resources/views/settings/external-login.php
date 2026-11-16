<?php
/** @var \App\Domain\Settings\ExternalLoginSettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var bool $ldapAvailable */
/** @var string $redirectUri */
/** @var \App\Domain\User\Permission[] $permissions */

use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\Settings\LdapEncryption;

use function App\Infrastructure\Http\e;

$submitted = $old !== [];
$val = static fn (string $key, ?string $stored): string => $submitted ? (is_scalar($old[$key] ?? null) ? (string) $old[$key] : '') : (string) $stored;
$on = static fn (string $key, bool $stored): bool => $submitted ? (($old[$key] ?? '') === '1') : $stored;
$selectedPermissions = $submitted
    ? (is_array($old['default_permissions'] ?? null) ? $old['default_permissions'] : [])
    : array_map(static fn ($p) => $p->value, $settings->defaultPermissions);

$inputClass = static fn (string $key): string => 'mt-1 w-full rounded-md border px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 '
    . (isset($errors[$key]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700');
$labelClass = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$hintClass = 'mt-1 text-xs text-slate-400 dark:text-slate-500';
$error = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
};
$field = static function (string $key, string $label, ?string $stored, string $hint = '', string $placeholder = '', bool $mono = false) use ($val, $inputClass, $labelClass, $hintClass, $error): string {
    return '<div><label for="' . $key . '" class="' . $labelClass . '">' . e($label) . '</label>'
        . '<input type="text" id="' . $key . '" name="' . $key . '" value="' . e($val($key, $stored)) . '" placeholder="' . e($placeholder) . '"'
        . ' autocomplete="off" spellcheck="false" class="' . $inputClass($key) . ($mono ? ' font-mono' : '') . '">'
        . $error($key)
        . ($hint !== '' ? '<p class="' . $hintClass . '">' . e($hint) . '</p>' : '')
        . '</div>';
};
$card = 'space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900';
$checkbox = 'mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700';
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Anmeldung (LDAP/OAuth)</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Benutzer können sich zusätzlich über ein Verzeichnis (LDAP, Active Directory) oder einen Identitätsanbieter
        (OAuth 2.0 / OpenID Connect, z. B. Keycloak, Authentik, Microsoft Entra ID, Google) anmelden.
        Die Zuordnung zum Benutzerkonto erfolgt über die E-Mail-Adresse.
    </p>
</div>

<p class="mb-5 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
    Die Anmeldung mit lokalem Passwort und Passkey bleibt immer möglich – so kommt ein Administrator auch dann noch hinein,
    wenn der externe Dienst ausfällt oder falsch eingerichtet ist.
</p>

<form method="post" action="/einstellungen/anmeldung" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <section class="<?= $card ?>">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">LDAP / Active Directory</h2>
            <p class="<?= $hintClass ?>">
                Benutzer geben im normalen Anmeldeformular ihren Verzeichnis-Benutzernamen (oder ihre E-Mail-Adresse) und ihr Verzeichnis-Passwort ein.
                Ein eingerichteter zweiter Faktor wird danach wie gewohnt abgefragt.
            </p>
        </div>

        <?php if (!$ldapAvailable): ?>
            <p class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                Auf diesem Server fehlt die PHP-Erweiterung „ldap“ (z. B. Paket <span class="font-mono">php-ldap</span>). Ohne sie lässt sich die LDAP-Anmeldung nicht aktivieren.
            </p>
        <?php endif; ?>

        <div>
            <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="ldap_enabled" value="1" <?= $on('ldap_enabled', $settings->ldapEnabled) ? 'checked' : '' ?> class="<?= $checkbox ?>">
                <span>Anmeldung über LDAP erlauben</span>
            </label>
            <?= $error('ldap_enabled') ?>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <?= $field('ldap_host', 'Server', $settings->ldapHost, 'Hostname oder IP-Adresse, ohne ldap://', 'ldap.example.org') ?>
            <div>
                <label for="ldap_encryption" class="<?= $labelClass ?>">Verschlüsselung</label>
                <select id="ldap_encryption" name="ldap_encryption" class="<?= $inputClass('ldap_encryption') ?>">
                    <?php foreach (LdapEncryption::cases() as $encryption): ?>
                        <option value="<?= e($encryption->value) ?>" data-port="<?= $encryption->defaultPort() ?>" <?= $val('ldap_encryption', $settings->ldapEncryption->value) === $encryption->value ? 'selected' : '' ?>><?= e($encryption->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $error('ldap_encryption') ?>
                <p class="<?= $hintClass ?>">Ohne Verschlüsselung werden Passwörter im Klartext übertragen.</p>
            </div>
            <?= $field('ldap_port', 'Port', (string) $settings->ldapPort, '389 (LDAP/STARTTLS) oder 636 (LDAPS)') ?>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <?= $field('ldap_bind_dn', 'Bind-DN (Dienstkonto)', $settings->ldapBindDn, 'Konto, mit dem nach Benutzern gesucht wird. Leer = anonyme Suche.', 'cn=readonly,dc=example,dc=org', true) ?>
            <div>
                <label for="ldap_bind_password" class="<?= $labelClass ?>">Passwort des Dienstkontos</label>
                <input type="password" id="ldap_bind_password" name="ldap_bind_password" autocomplete="new-password"
                       placeholder="<?= $settings->ldapBindPassword !== null ? '•••••••• (gespeichert)' : '' ?>" class="<?= $inputClass('ldap_bind_password') ?>">
                <?= $error('ldap_bind_password') ?>
                <p class="<?= $hintClass ?>"><?= $settings->ldapBindPassword !== null ? 'Leer lassen, um das gespeicherte Passwort zu behalten.' : 'Wird für die Benutzersuche benötigt, wenn eine Bind-DN angegeben ist.' ?></p>
            </div>
        </div>

        <?= $field('ldap_base_dn', 'Basis-DN', $settings->ldapBaseDn, 'Zweig des Verzeichnisses, in dem die Benutzer liegen (inklusive aller Unterzweige).', 'ou=people,dc=example,dc=org', true) ?>
        <?= $field('ldap_user_filter', 'Benutzerfilter', $settings->ldapUserFilter, '%s steht für den eingegebenen Anmeldenamen. Active Directory: (sAMAccountName=%s) oder (userPrincipalName=%s). Über den Filter lässt sich der Zugang auch auf eine Gruppe beschränken, z. B. (&(uid=%s)(memberOf=cn=verleih,ou=groups,dc=example,dc=org)).', ExternalLoginSettings::DEFAULT_LDAP_USER_FILTER, true) ?>

        <div class="grid gap-4 sm:grid-cols-3">
            <?= $field('ldap_email_attribute', 'Attribut E-Mail-Adresse', $settings->ldapEmailAttribute, '', 'mail', true) ?>
            <?= $field('ldap_first_name_attribute', 'Attribut Vorname', $settings->ldapFirstNameAttribute, '', 'givenName', true) ?>
            <?= $field('ldap_last_name_attribute', 'Attribut Nachname', $settings->ldapLastNameAttribute, '', 'sn', true) ?>
        </div>
    </section>

    <section class="<?= $card ?>">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">OAuth 2.0 / OpenID Connect</h2>
            <p class="<?= $hintClass ?>">
                Auf der Anmeldeseite erscheint ein zusätzlicher Button, der zum Identitätsanbieter weiterleitet.
            </p>
        </div>

        <div>
            <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="oauth_enabled" value="1" <?= $on('oauth_enabled', $settings->oauthEnabled) ? 'checked' : '' ?> class="<?= $checkbox ?>">
                <span>Anmeldung über OAuth / OpenID Connect erlauben</span>
            </label>
        </div>

        <div>
            <span class="<?= $labelClass ?>">Weiterleitungs-Adresse (Redirect URI)</span>
            <p class="mt-1 break-all rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"><?= e($redirectUri) ?></p>
            <p class="<?= $hintClass ?>">Diese Adresse beim Identitätsanbieter als erlaubte Redirect URI der Anwendung (Client) eintragen.</p>
        </div>

        <?= $field('oauth_label', 'Beschriftung des Buttons', $settings->oauthLabel, 'Auf der Anmeldeseite steht dann z. B. „Mit Firmenkonto anmelden“.', ExternalLoginSettings::DEFAULT_OAUTH_LABEL) ?>

        <div class="grid gap-4 sm:grid-cols-2">
            <?= $field('oauth_client_id', 'Client-ID', $settings->oauthClientId, '', '', true) ?>
            <div>
                <label for="oauth_client_secret" class="<?= $labelClass ?>">Client-Secret</label>
                <input type="password" id="oauth_client_secret" name="oauth_client_secret" autocomplete="new-password"
                       placeholder="<?= $settings->oauthClientSecret !== null ? '•••••••• (gespeichert)' : '' ?>" class="<?= $inputClass('oauth_client_secret') ?>">
                <?= $error('oauth_client_secret') ?>
                <p class="<?= $hintClass ?>"><?= $settings->oauthClientSecret !== null ? 'Leer lassen, um das gespeicherte Secret zu behalten.' : 'Vom Identitätsanbieter für diese Anwendung vergeben.' ?></p>
            </div>
        </div>

        <?= $field('oauth_issuer_url', 'Aussteller-URL (Issuer, OpenID Connect)', $settings->oauthIssuerUrl, 'Wenn angegeben, werden leere Endpunkte beim Speichern automatisch aus …/.well-known/openid-configuration ermittelt.', 'https://login.example.org/realms/firma', true) ?>

        <div class="grid gap-4">
            <?= $field('oauth_authorization_url', 'Autorisierungs-Endpunkt', $settings->oauthAuthorizationUrl, '', 'https://…/authorize', true) ?>
            <?= $field('oauth_token_url', 'Token-Endpunkt', $settings->oauthTokenUrl, '', 'https://…/token', true) ?>
            <?= $field('oauth_userinfo_url', 'Userinfo-Endpunkt', $settings->oauthUserinfoUrl, 'Muss die E-Mail-Adresse des Benutzers als JSON liefern.', 'https://…/userinfo', true) ?>
        </div>

        <?= $field('oauth_scopes', 'Scopes', $settings->oauthScopes, 'Durch Leerzeichen getrennt.', ExternalLoginSettings::DEFAULT_OAUTH_SCOPES, true) ?>

        <div class="grid gap-4 sm:grid-cols-3">
            <?= $field('oauth_email_claim', 'Feld E-Mail-Adresse', $settings->oauthEmailClaim, '', 'email', true) ?>
            <?= $field('oauth_first_name_claim', 'Feld Vorname', $settings->oauthFirstNameClaim, '', 'given_name', true) ?>
            <?= $field('oauth_last_name_claim', 'Feld Nachname', $settings->oauthLastNameClaim, '', 'family_name', true) ?>
        </div>

        <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="oauth_skip_two_factor" value="1" <?= $on('oauth_skip_two_factor', $settings->oauthSkipTwoFactor) ? 'checked' : '' ?> class="<?= $checkbox ?>">
            <span>
                Zweiten Faktor nach OAuth-Anmeldung nicht erneut abfragen
                <span class="block text-xs text-slate-400 dark:text-slate-500">Nur aktivieren, wenn der Identitätsanbieter selbst einen zweiten Faktor verlangt.</span>
            </span>
        </label>
    </section>

    <section class="<?= $card ?>">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Unbekannte Benutzer</h2>
            <p class="<?= $hintClass ?>">
                Was passiert, wenn sich jemand erfolgreich über LDAP oder OAuth anmeldet, für dessen E-Mail-Adresse es hier noch kein Benutzerkonto gibt?
                Ohne Haken wird die Anmeldung abgelehnt, bis ein Administrator das Konto unter „Benutzer“ anlegt.
            </p>
        </div>

        <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="auto_create_users" value="1" <?= $on('auto_create_users', $settings->autoCreateUsers) ? 'checked' : '' ?> class="<?= $checkbox ?>">
            <span>Benutzerkonto bei der ersten Anmeldung automatisch anlegen</span>
        </label>

        <div>
            <span class="<?= $labelClass ?>">Rechte automatisch angelegter Benutzer</span>
            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                <?php foreach ($permissions as $permission): ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="default_permissions[]" value="<?= e($permission->value) ?>" <?= in_array($permission->value, $selectedPermissions, true) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                        <?= e($permission->label()) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= $error('default_permissions') ?>
            <p class="<?= $hintClass ?>">Das Recht „Administration“ wird nie automatisch vergeben.</p>
        </div>
    </section>

    <div>
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
    </div>
</form>

<form method="post" action="/einstellungen/anmeldung/ldap-test" class="mt-5 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <button type="submit" <?= $ldapAvailable && $settings->ldapHost !== null ? '' : 'disabled' ?> class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">LDAP-Verbindung testen</button>
    <p class="text-xs text-slate-500 dark:text-slate-400">Prüft die <strong>gespeicherten</strong> Einstellungen: Verbindung, Dienstkonto und Basis-DN. Änderungen vorher speichern.</p>
</form>

<script>
    (function () {
        // Switching the encryption sets the matching standard port, unless a custom port was entered.
        var select = document.getElementById('ldap_encryption');
        var port = document.getElementById('ldap_port');
        select.addEventListener('change', function () {
            if (port.value === '' || port.value === '389' || port.value === '636') {
                port.value = select.options[select.selectedIndex].getAttribute('data-port');
            }
        });
    })();
</script>
