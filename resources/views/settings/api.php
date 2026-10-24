<?php
/**
 * @var \App\Domain\Settings\ApiSettings $apiSettings
 * @var \App\Domain\Api\ApiToken[] $tokens
 * @var array<string, string> $errors
 * @var string|null $newRawToken
 * @var string|null $editTokenId
 */

use function App\Infrastructure\Http\e;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">REST-API</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Optionale, token-gesicherte Schnittstelle zum Auslesen von Artikeln.</p>
</div>

<?php if ($newRawToken !== null): ?>
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
        <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Neues Token erstellt — jetzt kopieren, es wird aus Sicherheitsgründen nie wieder angezeigt:</p>
        <p class="mt-2 select-all break-all rounded-md border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-slate-800 dark:border-amber-800 dark:bg-slate-900 dark:text-slate-200"><?= e($newRawToken) ?></p>
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">Im Anfragen-Header mitschicken: <code>Authorization: Bearer &lt;Token&gt;</code></p>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <!-- Ein-/Ausschalter + Kurzdoku -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">API aktivieren</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Solange deaktiviert, antworten alle Endpunkte mit "nicht gefunden" — unabhängig von gültigen Tokens.</p>
        <form method="post" action="/einstellungen/api" class="flex items-center gap-3">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="enabled" value="1" <?= $apiSettings->enabled ? 'checked' : '' ?> class="rounded border-slate-300">
                API ist aktiviert
            </label>
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
        </form>

        <div class="mt-4 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
            <p><span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono dark:bg-slate-800">GET /api/v1/articles</span> — Liste (Parameter: <code>q</code>, <code>active=1</code>, <code>page</code>, <code>page_size</code>)</p>
            <p><span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono dark:bg-slate-800">GET /api/v1/articles/{id}</span> — Einzelner Artikel inkl. Inventurnummern</p>
            <p><span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono dark:bg-slate-800">GET /api/v1/articles/{id}/images/{imageId}</span> — Artikelbild</p>
        </div>
    </div>

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
                            <div>
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                                <input type="text" name="name" value="<?= e($token->name) ?>"
                                       class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">IP-Filter (eine IP oder ein CIDR-Netz pro Zeile, leer = von überall erlaubt)</label>
                                <textarea name="ip_allowlist" rows="3" placeholder="203.0.113.5&#10;198.51.100.0/24"
                                          class="mt-1 w-full rounded-md border <?= isset($errors['ipAllowlist']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"><?= e($token->ipAllowlist ?? '') ?></textarea>
                                <?php if (isset($errors['ipAllowlist'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['ipAllowlist']) ?></p><?php endif; ?>
                            </div>
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
                                </div>
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    <?php if ($token->ipAllowlist !== null): ?>
                                        IP-Filter: <?= count(\App\Domain\Api\IpAllowlist::entries($token->ipAllowlist)) ?> Eintrag/-Einträge
                                    <?php else: ?>
                                        Kein IP-Filter (von überall erreichbar)
                                    <?php endif; ?>
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
                                <form method="post" action="/einstellungen/api/tokens/<?= e($token->id) ?>/loeschen" data-confirm="Token wirklich löschen? Bestehende Integrationen können sich danach nicht mehr anmelden." data-confirm-style="danger">
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
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                    <input type="text" name="name" placeholder="z. B. Website-Integration" <?= $editTokenId === null ? '' : 'value="' . e($errors === [] ? '' : ($_POST['name'] ?? '')) . '"' ?>
                           class="mt-1 w-full rounded-md border <?= $editTokenId === null && isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if ($editTokenId === null && isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">IP-Filter (optional, eine IP oder ein CIDR-Netz pro Zeile)</label>
                    <textarea name="ip_allowlist" rows="2" placeholder="203.0.113.5&#10;198.51.100.0/24"
                              class="mt-1 w-full rounded-md border <?= $editTokenId === null && isset($errors['ipAllowlist']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"></textarea>
                    <?php if ($editTokenId === null && isset($errors['ipAllowlist'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['ipAllowlist']) ?></p><?php endif; ?>
                </div>
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">Token erstellen</button>
            </form>
        </div>
    </div>
</div>
