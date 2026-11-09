<?php
/**
 * Einstellungen › Veranstaltungs-Audio (Migration 135).
 *
 * @var \App\Domain\EventAudio\AudioSettings $settings
 * @var \App\Domain\EventAudio\AudioPlayer[] $players
 * @var ?array{player: string, token: string} $newToken
 * @var string $apiBaseUrl
 */

use App\Domain\EventAudio\AudioFormat;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$input = 'rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$btn = 'inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$primary = 'inline-flex items-center rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700';
$card = 'rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900';
$label = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$now = new DateTimeImmutable();
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Veranstaltungs-Audio</h1>
    <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
        Abspielgeräte (Raspberry Pi am Mischpult), Gong vor Durchsagen und Sprachausgabe. Jingles, Audio-Dateien und Veranstalter-Zugänge pflegst du je Veranstaltung unter „Audio &amp; Durchsagen“.
    </p>
</div>

<div class="max-w-3xl space-y-6">
    <section id="geraete" class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Abspielgeräte</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Jedes Gerät meldet sich mit seinem Token an der Schnittstelle <code class="font-mono"><?= e($apiBaseUrl) ?></code> an. Welches Gerät bei welcher Veranstaltung spielt, wählst du in der Veranstaltung.
        </p>

        <?php if ($newToken !== null): ?>
            <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                <p class="font-semibold">Geräte-Token – nur jetzt sichtbar, bitte auf dem Raspberry Pi eintragen:</p>
                <p class="mt-2 break-all font-mono" id="player-token"><?= e($newToken['token']) ?></p>
                <button type="button" class="<?= $btn ?> mt-3" data-copy-target="player-token">Kopieren</button>
            </div>
        <?php endif; ?>

        <?php if ($players === []): ?>
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">Noch kein Abspielgerät angelegt.</p>
        <?php endif; ?>
        <div class="space-y-3">
            <?php foreach ($players as $player): ?>
                <?php $online = $player->isOnline($now); ?>
                <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                    <form method="post" action="/einstellungen/veranstaltungs-audio/geraete/<?= e($player->id) ?>" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <span class="inline-block rounded-full" style="width:.625rem;height:.625rem;background: <?= $online ? '#10b981' : '#94a3b8' ?>" title="<?= $online ? 'online' : 'offline' ?>"></span>
                        <input type="text" name="name" value="<?= e($player->name) ?>" required maxlength="120" class="<?= $input ?>" style="min-width: 14rem" aria-label="Name">
                        <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300"><input type="checkbox" name="enabled" value="1" <?= $player->enabled ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300"> aktiv</label>
                        <button type="submit" class="<?= $btn ?>">Speichern</button>
                    </form>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Token <span class="font-mono"><?= e($player->tokenHint) ?></span>
                        · <?= $player->lastSeenAt !== null ? ($online ? 'online' : 'zuletzt gesehen ' . e($player->lastSeenAt->format('d.m.Y H:i'))) : 'noch nie verbunden' ?>
                        <?= $player->lastIp !== null ? ' · IP ' . e($player->lastIp) : '' ?>
                        <?= $player->playerVersion !== null ? ' · Version ' . e($player->playerVersion) : '' ?>
                        <?= $online && $player->nowPlaying !== null ? ' · spielt: ' . e($player->nowPlaying) : '' ?>
                    </p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <form method="post" action="/einstellungen/veranstaltungs-audio/geraete/<?= e($player->id) ?>/token" data-confirm="Neuen Token für „<?= e($player->name) ?>“ erzeugen? Das Gerät verliert sofort die Verbindung, bis der neue Token eingetragen ist.">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Neuen Token erzeugen</button>
                        </form>
                        <form method="post" action="/einstellungen/veranstaltungs-audio/geraete/<?= e($player->id) ?>/loeschen" data-confirm="Abspielgerät „<?= e($player->name) ?>“ löschen? Veranstaltungen verlieren die Zuordnung." data-confirm-style="danger">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="post" action="/einstellungen/veranstaltungs-audio/geraete" class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="text" name="name" required maxlength="120" placeholder="z. B. Pi Mischpult 1" class="<?= $input ?>" style="min-width: 14rem">
            <button type="submit" class="<?= $primary ?>">Gerät anlegen</button>
        </form>
    </section>

    <section class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Gong vor Durchsagen</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Ohne eigene Datei nutzt das Abspielgerät seinen eingebauten Gong.</p>
        <?php if ($settings->gongStoredFilename !== null): ?>
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <span class="text-sm text-slate-700 dark:text-slate-300"><?= e((string) $settings->gongOriginalFilename) ?></span>
                <audio controls preload="none" src="/einstellungen/veranstaltungs-audio/gong" style="height:2rem"></audio>
                <form method="post" action="/einstellungen/veranstaltungs-audio/gong/entfernen" data-confirm="Eigenen Gong entfernen?">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                </form>
            </div>
        <?php endif; ?>
        <form method="post" action="/einstellungen/veranstaltungs-audio/gong" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="file" name="gong" required accept="<?= e(AudioFormat::acceptAttribute()) ?>" class="text-sm text-slate-600 dark:text-slate-300">
            <button type="submit" class="<?= $btn ?>"><?= $settings->gongStoredFilename !== null ? 'Ersetzen' : 'Hochladen' ?></button>
        </form>
    </section>

    <section class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Sprachausgabe &amp; Grenzen</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Getippte Durchsagen spricht das Abspielgerät mit seiner Sprachausgabe; Sprache, Stimme und Tempo werden ihm übermittelt.</p>
        <form method="post" action="/einstellungen/veranstaltungs-audio" class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <label class="<?= $label ?>">Sprache<input type="text" name="tts_language" value="<?= e($settings->ttsLanguage) ?>" required pattern="[a-z]{2}(-[A-Z]{2})?" class="mt-1 w-full <?= $input ?>"></label>
            <label class="<?= $label ?>">Stimme <span class="font-normal text-slate-400">(optional, gerätespezifisch)</span><input type="text" name="tts_voice" value="<?= e($settings->ttsVoice ?? '') ?>" maxlength="100" placeholder="z. B. de_DE-thorsten-high" class="mt-1 w-full <?= $input ?>"></label>
            <label class="<?= $label ?>">Tempo (0,5–2,0)<input type="number" name="tts_rate" value="<?= e(number_format($settings->ttsRate, 2, '.', '')) ?>" min="0.5" max="2" step="0.05" class="mt-1 w-full <?= $input ?>"></label>
            <label class="<?= $label ?>">Max. Dateigröße (MB)<input type="number" name="max_upload_mb" value="<?= (int) $settings->maxUploadMb ?>" min="1" max="500" class="mt-1 w-full <?= $input ?>"></label>
            <label class="<?= $label ?>">Aufträge verfallen nach (Min.)<input type="number" name="queue_expiry_minutes" value="<?= (int) $settings->queueExpiryMinutes ?>" min="1" max="240" class="mt-1 w-full <?= $input ?>"></label>
            <div style="align-self:end"><button type="submit" class="<?= $btn ?>">Speichern</button></div>
        </form>
        <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">Hinweis: Für größere Dateien müssen auch <code>upload_max_filesize</code> und <code>post_max_size</code> in der PHP-Konfiguration passen.</p>
    </section>
</div>
<script>
    document.querySelectorAll('[data-copy-target]').forEach(function (b) {
        b.addEventListener('click', function () {
            var el = document.getElementById(b.dataset.copyTarget);
            if (!el || !navigator.clipboard) { return; }
            navigator.clipboard.writeText(el.innerText.trim()).then(function () { b.textContent = 'Kopiert ✓'; });
        });
    });
</script>
