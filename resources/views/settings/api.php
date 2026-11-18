<?php
/**
 * @var \App\Domain\Settings\ApiSettings $apiSettings
 * @var \App\Domain\Api\ApiToken[] $tokens
 * @var \App\Domain\Api\ApiRequestLogEntry[] $requestLog
 * @var array<string, string> $errors
 * @var string|null $newRawToken
 * @var string|null $editTokenId
 */

use App\Domain\Api\ApiScope;
use App\Domain\Settings\ApiSettings;

use function App\Infrastructure\Http\e;

$inputClass = static fn (string $field, bool $active = true): string => 'mt-1 w-full rounded-md border ' . ($active && isset($errors[$field]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900';
$errorLine = static function (string $field, bool $active = true) use ($errors): string {
    return $active && isset($errors[$field]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$field]) . '</p>' : '';
};
$postedScopes = isset($_POST['scopes']) && is_array($_POST['scopes']) ? array_map('strval', $_POST['scopes']) : null;

/**
 * Token fields shared by "create" and "edit": name, IP filter, scopes, read-only, rate limit.
 */
$tokenFields = static function (?\App\Domain\Api\ApiToken $token, bool $showErrors) use ($inputClass, $errorLine, $postedScopes, $errors): void {
    $selected = $showErrors && $postedScopes !== null ? $postedScopes : ($token !== null ? $token->scopeValues() : []);
    $readOnly = $showErrors && $errors !== [] ? (($_POST['read_only'] ?? '') === '1') : ($token?->readOnly ?? false);
    $formId = 'token-' . ($token?->id ?? 'new');
    ?>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
        <input type="text" name="name" placeholder="z. B. Website-Integration" value="<?= e($showErrors && $errors !== [] ? (string) ($_POST['name'] ?? '') : ($token?->name ?? '')) ?>" class="<?= $inputClass('name', $showErrors) ?>">
        <?= $errorLine('name', $showErrors) ?>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">IP-Filter (eine IP oder ein CIDR-Netz pro Zeile, leer = von überall erlaubt)</label>
        <textarea name="ip_allowlist" rows="2" placeholder="203.0.113.5&#10;198.51.100.0/24" class="<?= $inputClass('ipAllowlist', $showErrors) ?> font-mono"><?= e($showErrors && $errors !== [] ? (string) ($_POST['ip_allowlist'] ?? '') : ($token?->ipAllowlist ?? '')) ?></textarea>
        <?= $errorLine('ipAllowlist', $showErrors) ?>
    </div>
    <fieldset data-scope-group="<?= e($formId) ?>">
        <legend class="block text-xs font-medium text-slate-600 dark:text-slate-400">Berechtigungen (Scopes)</legend>
        <div class="mt-1 flex flex-wrap gap-3 text-xs">
            <button type="button" class="font-medium text-brand-700 hover:underline dark:text-brand-400" onclick="this.closest('fieldset').querySelectorAll('input[data-read]').forEach(c => c.checked = true)">Alle Lesescopes auswählen</button>
            <button type="button" class="font-medium text-slate-500 hover:underline dark:text-slate-400" onclick="this.closest('fieldset').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false)">Keine</button>
        </div>
        <div class="mt-2 grid gap-1 sm:grid-cols-2">
            <?php foreach (ApiScope::cases() as $scope): ?>
                <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="scopes[]" value="<?= e($scope->value) ?>" <?= $scope->isRead() ? 'data-read="1"' : '' ?> <?= in_array($scope->value, $selected, true) ? 'checked' : '' ?> class="mt-0.5 rounded border-slate-300">
                    <span><span class="font-mono text-xs <?= $scope->isRead() ? 'text-slate-500' : 'text-amber-700 dark:text-amber-400' ?>"><?= e($scope->value) ?></span><br><span class="text-xs text-slate-500 dark:text-slate-400"><?= e($scope->label()) ?></span></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?= $errorLine('scopes', $showErrors) ?>
    </fieldset>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="inline-flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="read_only" value="1" <?= $readOnly ? 'checked' : '' ?> class="mt-0.5 rounded border-slate-300">
            <span><span class="font-medium">Nur lesen</span><br><span class="text-xs text-slate-500 dark:text-slate-400">Lehnt jede schreibende Anfrage ab — unabhängig von den gewählten Scopes.</span></span>
        </label>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Anfragen pro Minute (leer = Standard, 0 = unbegrenzt)</label>
            <input type="text" inputmode="numeric" name="rate_limit_per_minute" value="<?= e($showErrors && $errors !== [] ? (string) ($_POST['rate_limit_per_minute'] ?? '') : ($token?->rateLimitPerMinute !== null ? (string) $token->rateLimitPerMinute : '')) ?>" class="<?= $inputClass('rateLimitPerMinute', $showErrors) ?>">
            <?= $errorLine('rateLimitPerMinute', $showErrors) ?>
        </div>
    </div>
    <?php
};
$settingsErrors = $editTokenId === null && $newRawToken === null && array_intersect_key($errors, array_flip(['rateLimitPerMinute', 'rateLimitBackend', 'redisConnection', 'redisHost', 'redisPort', 'redisSocketPath', 'redisDatabase', 'redisKeyPrefix', 'redisTimeoutMs'])) !== [] && !isset($_POST['name']);
$val = static fn (string $field, string $current): string => $settingsErrors && isset($_POST[$field]) ? (string) $_POST[$field] : $current;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">REST-API</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Token-gesicherte Schnittstelle für Website, Buchhaltung und Automatisierung. Die vollständige Beschreibung aller Endpunkte steht in <code>api-docs.html</code> im Installationsverzeichnis.</p>
</div>

<?php if ($newRawToken !== null): ?>
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
        <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Neues Token erstellt — jetzt kopieren, es wird aus Sicherheitsgründen nie wieder angezeigt:</p>
        <p class="mt-2 select-all break-all rounded-md border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-slate-800 dark:border-amber-800 dark:bg-slate-900 dark:text-slate-200"><?= e($newRawToken) ?></p>
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">Im Anfragen-Header mitschicken: <code>Authorization: Bearer &lt;Token&gt;</code></p>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <!-- Ein-/Ausschalter, Ratenbegrenzung, Redis -->
    <form method="post" action="/einstellungen/api" class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div>
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">API aktivieren</h2>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Solange deaktiviert, antworten alle Endpunkte mit "nicht gefunden" — unabhängig von gültigen Tokens.</p>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="enabled" value="1" <?= $apiSettings->enabled ? 'checked' : '' ?> class="rounded border-slate-300">
                API ist aktiviert
            </label>
        </div>

        <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">Ratenbegrenzung</h2>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Begrenzt die Anfragen je Token und Minute (Antwort <code>429</code> mit <code>Retry-After</code>). Einzelne Tokens können einen eigenen Wert haben.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Anfragen pro Minute und Token (0 = unbegrenzt)</label>
                    <input type="text" inputmode="numeric" name="rate_limit_per_minute" value="<?= e($val('rate_limit_per_minute', (string) $apiSettings->rateLimitPerMinute)) ?>" class="<?= $inputClass('rateLimitPerMinute', $settingsErrors) ?>">
                    <?= $errorLine('rateLimitPerMinute', $settingsErrors) ?>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Zähler speichern in</label>
                    <?php $backend = $val('rate_limit_backend', $apiSettings->rateLimitBackend); ?>
                    <select name="rate_limit_backend" class="<?= $inputClass('rateLimitBackend', $settingsErrors) ?>" onchange="document.getElementById('redis-settings').classList.toggle('hidden', this.value !== 'redis')">
                        <option value="<?= ApiSettings::BACKEND_DATABASE ?>" <?= $backend === ApiSettings::BACKEND_DATABASE ? 'selected' : '' ?>>Datenbank</option>
                        <option value="<?= ApiSettings::BACKEND_REDIS ?>" <?= $backend === ApiSettings::BACKEND_REDIS ? 'selected' : '' ?>>Redis</option>
                    </select>
                </div>
            </div>

            <div id="redis-settings" class="mt-4 space-y-3 rounded-md border border-slate-200 p-3 dark:border-slate-700 <?= $backend === ApiSettings::BACKEND_REDIS ? '' : 'hidden' ?>">
                <p class="text-xs text-slate-500 dark:text-slate-400">Ist Redis nicht erreichbar, zählt die API automatisch in der Datenbank weiter. Keine PHP-Erweiterung nötig.</p>
                <?php if ($apiSettings->redisLastError !== null): ?>
                    <p class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">Redis zuletzt nicht erreichbar<?= $apiSettings->redisLastErrorAt !== null ? ' (' . e($apiSettings->redisLastErrorAt->format('d.m.Y H:i')) . ')' : '' ?>: <?= e($apiSettings->redisLastError) ?></p>
                <?php endif; ?>
                <?php $connection = $val('redis_connection', $apiSettings->redisConnection); ?>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Verbindung</label>
                        <select name="redis_connection" class="<?= $inputClass('redisConnection', $settingsErrors) ?>">
                            <option value="<?= ApiSettings::REDIS_TCP ?>" <?= $connection === ApiSettings::REDIS_TCP ? 'selected' : '' ?>>TCP/IP</option>
                            <option value="<?= ApiSettings::REDIS_TLS ?>" <?= $connection === ApiSettings::REDIS_TLS ? 'selected' : '' ?>>TCP/IP mit TLS</option>
                            <option value="<?= ApiSettings::REDIS_SOCKET ?>" <?= $connection === ApiSettings::REDIS_SOCKET ? 'selected' : '' ?>>Unix-Socket</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Host (TCP/TLS)</label>
                        <input type="text" name="redis_host" value="<?= e($val('redis_host', $apiSettings->redisHost)) ?>" class="<?= $inputClass('redisHost', $settingsErrors) ?>">
                        <?= $errorLine('redisHost', $settingsErrors) ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Port (TCP/TLS)</label>
                        <input type="text" inputmode="numeric" name="redis_port" value="<?= e($val('redis_port', (string) $apiSettings->redisPort)) ?>" class="<?= $inputClass('redisPort', $settingsErrors) ?>">
                        <?= $errorLine('redisPort', $settingsErrors) ?>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Socket-Pfad (Unix-Socket)</label>
                    <input type="text" name="redis_socket_path" placeholder="/run/redis/redis.sock" value="<?= e($val('redis_socket_path', $apiSettings->redisSocketPath ?? '')) ?>" class="<?= $inputClass('redisSocketPath', $settingsErrors) ?> font-mono">
                    <?= $errorLine('redisSocketPath', $settingsErrors) ?>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Benutzer (ACL, optional)</label>
                        <input type="text" name="redis_username" autocomplete="off" value="<?= e($val('redis_username', $apiSettings->redisUsername ?? '')) ?>" class="<?= $inputClass('redisUsername', $settingsErrors) ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Passwort <?= $apiSettings->redisPassword !== null ? '(gespeichert — leer lassen, um es zu behalten)' : '(optional)' ?></label>
                        <input type="password" name="redis_password" autocomplete="new-password" class="<?= $inputClass('redisPassword', $settingsErrors) ?>">
                        <?php if ($apiSettings->redisPassword !== null): ?>
                            <label class="mt-1 inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400"><input type="checkbox" name="redis_password_clear" value="1" class="rounded border-slate-300"> Passwort entfernen</label>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Datenbank-Nummer</label>
                        <input type="text" inputmode="numeric" name="redis_database" value="<?= e($val('redis_database', (string) $apiSettings->redisDatabase)) ?>" class="<?= $inputClass('redisDatabase', $settingsErrors) ?>">
                        <?= $errorLine('redisDatabase', $settingsErrors) ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Schlüssel-Präfix</label>
                        <input type="text" name="redis_key_prefix" value="<?= e($val('redis_key_prefix', $apiSettings->redisKeyPrefix)) ?>" class="<?= $inputClass('redisKeyPrefix', $settingsErrors) ?> font-mono">
                        <?= $errorLine('redisKeyPrefix', $settingsErrors) ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Timeout (ms)</label>
                        <input type="text" inputmode="numeric" name="redis_timeout_ms" value="<?= e($val('redis_timeout_ms', (string) (int) round($apiSettings->redisTimeoutSeconds * 1000))) ?>" class="<?= $inputClass('redisTimeoutMs', $settingsErrors) ?>">
                        <?= $errorLine('redisTimeoutMs', $settingsErrors) ?>
                    </div>
                </div>
                <button type="submit" form="redis-test-form" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">Gespeicherte Verbindung testen</button>
            </div>
        </div>

        <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
    </form>
    <form id="redis-test-form" method="post" action="/einstellungen/api/redis-test" class="hidden">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    </form>

    <!-- Token-Liste -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Zugriffs-Tokens</h2>

        <?php if ($tokens === []): ?>
            <p class="mb-4 text-sm text-slate-400 dark:text-slate-500">Noch kein Token angelegt.</p>
        <?php else: ?>
            <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($tokens as $token): ?>
                    <?php if ($editTokenId === $token->id): ?>
                        <form method="post" action="/einstellungen/api/tokens/<?= e($token->id) ?>" class="space-y-3 py-3">
                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <?php $tokenFields($token, true); ?>
                            <div class="flex items-center gap-3">
                                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
                                <a href="/einstellungen/api" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($token->name) ?></p>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">…<?= e($token->tokenLastFour) ?></span>
                                    <?php if ($token->enabled): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-700">Aktiv</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">Deaktiviert</span>
                                    <?php endif; ?>
                                    <?php if ($token->readOnly): ?>
                                        <span class="inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-inset ring-sky-300 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-700">Nur lesen</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-1 flex flex-wrap gap-1">
                                    <?php foreach ($token->scopes as $scope): ?>
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] <?= $scope->isRead() || $token->readOnly ? 'text-slate-500 dark:text-slate-400' : 'text-amber-700 dark:text-amber-400' ?> dark:bg-slate-800 <?= !$scope->isRead() && $token->readOnly ? 'line-through' : '' ?>"><?= e($scope->value) ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($token->scopes === []): ?><span class="text-xs text-red-600 dark:text-red-400">Keine Scopes — das Token hat keinen Zugriff.</span><?php endif; ?>
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    <?php if ($token->ipAllowlist !== null): ?>
                                        IP-Filter: <?= count(\App\Domain\Api\IpAllowlist::entries($token->ipAllowlist)) ?> Eintrag/-Einträge
                                    <?php else: ?>
                                        Kein IP-Filter (von überall erreichbar)
                                    <?php endif; ?>
                                    · Limit: <?= $token->rateLimitPerMinute !== null ? ($token->rateLimitPerMinute === 0 ? 'unbegrenzt' : e((string) $token->rateLimitPerMinute) . '/min') : 'Standard' ?>
                                    · Zuletzt genutzt: <?= $token->lastUsedAt !== null ? e($token->lastUsedAt->format('d.m.Y H:i')) : 'noch nie' ?>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs">
                                <a href="/einstellungen/api?bearbeiten=<?= e($token->id) ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                                <?php if ($token->enabled): ?>
                                    <form method="post" action="/einstellungen/api/tokens/<?= e($token->id) ?>/deaktivieren">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <button type="submit" class="font-medium text-slate-600 hover:underline dark:text-slate-400">Deaktivieren</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="/einstellungen/api/tokens/<?= e($token->id) ?>/aktivieren">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <button type="submit" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Aktivieren</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/einstellungen/api/tokens/<?= e($token->id) ?>/loeschen" data-confirm-key="settings.api.token.delete" data-confirm="Token wirklich löschen? Bestehende Integrationen können sich danach nicht mehr anmelden." data-confirm-style="danger">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Neues Token anlegen</p>
            <form method="post" action="/einstellungen/api/tokens" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <?php $tokenFields(null, $editTokenId === null && isset($_POST['name'])); ?>
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">Token erstellen</button>
            </form>
        </div>
    </div>

    <!-- Protokoll schreibender Zugriffe -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">Protokoll schreibender Zugriffe</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Die letzten <?= count($requestLog) ?> Anfragen mit POST, PATCH, PUT oder DELETE. Einträge werden nach <?= \App\Domain\Api\ApiRequestLogRepositoryInterface::RETENTION_DAYS ?> Tagen automatisch gelöscht.</p>
        <?php if ($requestLog === []): ?>
            <p class="text-sm text-slate-400 dark:text-slate-500">Noch keine schreibenden Zugriffe.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="text-slate-500 dark:text-slate-400">
                        <tr><th class="py-1 pr-3 font-medium">Zeit</th><th class="py-1 pr-3 font-medium">Token</th><th class="py-1 pr-3 font-medium">Anfrage</th><th class="py-1 pr-3 font-medium">Status</th><th class="py-1 font-medium">IP</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                        <?php foreach ($requestLog as $entry): ?>
                            <tr>
                                <td class="whitespace-nowrap py-1 pr-3"><?= e($entry->createdAt->format('d.m.Y H:i:s')) ?></td>
                                <td class="py-1 pr-3"><?= e($entry->tokenName) ?></td>
                                <td class="py-1 pr-3 font-mono"><?= e($entry->method . ' ' . $entry->path) ?></td>
                                <td class="py-1 pr-3 font-mono <?= $entry->statusCode >= 400 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' ?>"><?= e((string) $entry->statusCode) ?></td>
                                <td class="py-1 font-mono"><?= e($entry->ip ?? '–') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
