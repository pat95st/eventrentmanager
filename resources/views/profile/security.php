<?php
/** @var \App\Domain\User\TwoFactorMethod $twoFactorMethod */
/** @var string $pendingTotpSecret */
/** @var string $provisioningUri */
/** @var array<string, string> $errors */
/** @var \App\Domain\Auth\WebauthnCredential[] $passkeys */

use App\Domain\User\TwoFactorMethod;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('profile.security.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.security.subtitle')) ?></p>
</div>

<div class="max-w-lg rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <p class="text-sm text-slate-600 dark:text-slate-400">
        <?= e(t('profile.security.current_method')) ?>
        <span class="font-medium text-slate-900 dark:text-slate-100"><?= e($twoFactorMethod->label()) ?></span>
    </p>

    <?php if ($twoFactorMethod !== TwoFactorMethod::None): ?>
        <form method="post" action="/profil/sicherheit/deaktivieren" class="mt-4" data-confirm="<?= e(t('profile.security.confirm_disable')) ?>" data-confirm-style="danger">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                <?= e(t('profile.security.disable_button')) ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('profile.security.totp.title')) ?></h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
            <?= e(t('profile.security.totp.hint')) ?>
        </p>

        <p class="mb-1 break-all rounded-md bg-slate-100 px-3 py-2 font-mono text-sm text-slate-800 dark:bg-slate-800 dark:text-slate-200">
            <?= e($pendingTotpSecret) ?>
        </p>
        <p class="mb-4 text-xs text-slate-400 dark:text-slate-500">
            <?= e(t('profile.security.totp.uri_hint')) ?> <span class="break-all font-mono"><?= e($provisioningUri) ?></span>
        </p>

        <?php if (isset($errors['code'])): ?>
            <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300">
                <?= e($errors['code']) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/profil/sicherheit/totp" class="flex flex-wrap items-end gap-3">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label for="code" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('profile.security.totp.field.code')) ?></label>
                <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required
                       class="mt-1 w-32 rounded-md border border-slate-300 px-3 py-2 text-center font-mono text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('profile.security.totp.activate')) ?>
            </button>
        </form>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('profile.security.email.title')) ?></h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
            <?= e(t('profile.security.email.hint')) ?>
        </p>
        <form method="post" action="/profil/sicherheit/email">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <?= e(t('profile.security.email.activate')) ?>
            </button>
        </form>
    </div>
</div>

<div id="passkeys-section" class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
     data-label-waiting="<?= e(t('profile.security.passkeys.waiting')) ?>"
     data-label-add="<?= e(t('profile.security.passkeys.add')) ?>"
     data-error-register="<?= e(t('profile.security.passkeys.error_register')) ?>"
     data-error-remove="<?= e(t('profile.security.passkeys.error_remove')) ?>"
     data-confirm-remove="<?= e(t('profile.security.passkeys.confirm_remove')) ?>">
    <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('profile.security.passkeys.title')) ?></h2>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('profile.security.passkeys.hint')) ?>
    </p>

    <div id="passkey-unsupported-note" class="hidden rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
        <?= e(t('profile.security.passkeys.unsupported')) ?>
    </div>

    <div id="passkey-error" class="mb-4 hidden rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300"></div>

    <ul id="passkey-list" class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($passkeys as $passkey): ?>
            <li class="flex items-center justify-between py-3" data-passkey-id="<?= e($passkey->id) ?>">
                <div>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($passkey->nickname ?? t('profile.security.passkeys.unnamed')) ?></p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">
                        <?= e(t('profile.security.passkeys.created_on', ['date' => $passkey->createdAt->format('d.m.Y')])) ?><?php if ($passkey->lastUsedAt !== null): ?><?= e(t('profile.security.passkeys.last_used_on', ['date' => $passkey->lastUsedAt->format('d.m.Y H:i')])) ?><?php endif; ?>
                    </p>
                </div>
                <button type="button" class="passkey-delete-button rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                    <?= e(t('profile.security.passkeys.remove')) ?>
                </button>
            </li>
        <?php endforeach; ?>
        <?php if ($passkeys === []): ?>
            <li id="passkey-empty-hint" class="py-3 text-sm text-slate-400 dark:text-slate-500"><?= e(t('profile.security.passkeys.empty')) ?></li>
        <?php endif; ?>
    </ul>

    <div id="passkey-add-form" class="flex flex-wrap items-end gap-3">
        <div>
            <label for="passkey-nickname" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('profile.security.passkeys.field.nickname')) ?></label>
            <input type="text" id="passkey-nickname" maxlength="100" placeholder="z. B. MacBook Touch ID"
                   class="mt-1 w-56 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <button type="button" id="passkey-add-button" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('profile.security.passkeys.add')) ?>
        </button>
    </div>
</div>

<script>
(function () {
    function base64UrlToBuffer(base64url) {
        var padded = base64url.replace(/-/g, '+').replace(/_/g, '/');
        while (padded.length % 4 !== 0) { padded += '='; }
        var binary = atob(padded);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i); }
        return bytes.buffer;
    }

    function bufferToBase64Url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.length; i++) { binary += String.fromCharCode(bytes[i]); }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    var section = document.getElementById('passkeys-section');
    var addForm = document.getElementById('passkey-add-form');
    var addButton = document.getElementById('passkey-add-button');
    var nicknameInput = document.getElementById('passkey-nickname');
    var errorBox = document.getElementById('passkey-error');
    var list = document.getElementById('passkey-list');
    var emptyHint = document.getElementById('passkey-empty-hint');
    var labelWaiting = section.getAttribute('data-label-waiting');
    var labelAdd = section.getAttribute('data-label-add');
    var errorRegister = section.getAttribute('data-error-register');
    var errorRemove = section.getAttribute('data-error-remove');
    var confirmRemove = section.getAttribute('data-confirm-remove');

    if (!window.PublicKeyCredential) {
        addForm.classList.add('hidden');
        document.getElementById('passkey-unsupported-note').classList.remove('hidden');
        return;
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    }

    addButton.addEventListener('click', function () {
        errorBox.classList.add('hidden');
        addButton.disabled = true;
        addButton.textContent = labelWaiting;

        var nickname = nicknameInput.value;

        fetch('/passkeys/register/options', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) { return res.json(); })
            .then(function (body) {
                var options = body.options;
                var publicKey = {
                    rp: options.rp,
                    user: {
                        id: base64UrlToBuffer(options.user.id),
                        name: options.user.name,
                        displayName: options.user.displayName,
                    },
                    challenge: base64UrlToBuffer(options.challenge),
                    pubKeyCredParams: options.pubKeyCredParams,
                    authenticatorSelection: options.authenticatorSelection,
                    timeout: options.timeout,
                    attestation: options.attestation,
                    excludeCredentials: (options.excludeCredentials || []).map(function (c) {
                        return { type: c.type, id: base64UrlToBuffer(c.id) };
                    }),
                };

                return navigator.credentials.create({ publicKey: publicKey }).then(function (credential) {
                    return fetch('/passkeys/register/complete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-Token': window.CSRF_TOKEN,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new URLSearchParams({
                            challengeId: body.challengeId,
                            clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                            attestationObject: bufferToBase64Url(credential.response.attestationObject),
                            nickname: nickname,
                        }),
                    });
                });
            })
            .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
            .then(function (result) {
                if (!result.ok || !result.body.success) {
                    throw new Error((result.body && result.body.error) || errorRegister);
                }
                window.location.reload();
            })
            .catch(function (err) {
                showError(err && err.message ? err.message : errorRegister);
                addButton.disabled = false;
                addButton.textContent = labelAdd;
            });
    });

    list.addEventListener('click', async function (event) {
        var button = event.target.closest('.passkey-delete-button');
        if (!button) { return; }
        if (!event.shiftKey) {
            var confirmed = await window.confirmModal(confirmRemove);
            if (!confirmed) { return; }
        }

        var item = button.closest('[data-passkey-id]');
        var id = item.getAttribute('data-passkey-id');

        fetch('/passkeys/' + encodeURIComponent(id) + '/loeschen', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) { return res.json(); })
            .then(function (body) {
                if (!body.success) {
                    throw new Error(body.error || errorRemove);
                }
                item.remove();
                if (!list.querySelector('[data-passkey-id]') && emptyHint) {
                    emptyHint.classList.remove('hidden');
                }
            })
            .catch(function (err) {
                showError(err && err.message ? err.message : errorRemove);
            });
    });
})();
</script>
