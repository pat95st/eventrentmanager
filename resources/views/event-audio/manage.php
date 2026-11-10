<?php
/**
 * Veranstaltung › Audio & Durchsagen (Migration 135).
 *
 * @var \App\Domain\Rental\Rental $rental
 * @var \App\Domain\EventAudio\RentalAudioFile[] $jingles
 * @var \App\Domain\EventAudio\RentalAudioFile[] $audioFiles
 * @var \App\Domain\EventAudio\RentalAudioSettings $rentalSettings
 * @var \App\Domain\EventAudio\AudioPlayer[] $players
 * @var ?\App\Domain\EventAudio\AudioPlayer $player
 * @var \App\Domain\EventAudio\AudioSettings $settings
 * @var \App\Domain\EventAudio\OrganizerLogin[] $logins
 * @var \App\Domain\EventAudio\PlaybackRequest[] $recent
 * @var ?array{username: string, password: string} $newCredentials
 * @var ?\App\Domain\EventAudio\ScheduledPlayback[] $schedules
 */

use App\Domain\EventAudio\AudioFileKind;
use App\Domain\EventAudio\AudioFormat;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$base = '/veranstaltungen/' . $rental->id . '/audio';
$input = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$btn = 'inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$primary = 'inline-flex items-center rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700';
$card = 'rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900';
$label = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$now = new DateTimeImmutable();
$scheme = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
$loginUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/live/login';
$fmtSize = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format(max(1, (int) round($b / 1024)), 0, ',', '.') . ' KB';
$dtLocal = static fn (?DateTimeImmutable $d): string => $d?->format('Y-m-d\TH:i') ?? '';
$defaultFrom = $rental->startDate->modify('-1 day')->setTime(0, 0);
$defaultUntil = $rental->endDate->modify('+1 day')->setTime(23, 59);
$slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $rental->eventName) ?: 'veranstaltung')), '-');
$suggestedUsername = substr($slug !== '' ? $slug : 'veranstaltung', 0, 30) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
$playerOnline = $player?->isOnline($now) ?? false;
$fileTitles = [];
foreach (array_merge($jingles, $audioFiles) as $f) {
    $fileTitles[$f->id] = $f->title;
}
$schedules ??= null;
$defaultPlayAt = max($now->modify('+15 minutes'), $rental->startDate->setTime((int) $now->format('H'), 0));
?>
<style>
    .ea-row { display:grid; grid-template-columns: minmax(0,1fr) auto; gap:.5rem 1rem; align-items:center; padding:.75rem 0; border-top:1px solid rgb(241 245 249); }
    .dark .ea-row { border-color: rgb(30 41 59); }
    .ea-row:first-child { border-top:0; }
    .ea-row audio { width:100%; max-width:22rem; height:2rem; }
    @media (min-width: 900px) { .ea-row { grid-template-columns: minmax(0,1fr) 22rem auto; } }
    .ea-dot { display:inline-block; width:.5rem; height:.5rem; border-radius:9999px; margin-right:.375rem; vertical-align:middle; }
    .ea-pill { display:inline-flex; align-items:center; border-radius:9999px; padding:.0625rem .5rem; font-size:.6875rem; font-weight:600; white-space:nowrap; }
    .ea-grid2 { display:grid; gap:.75rem; grid-template-columns: 1fr; }
    @media (min-width: 640px) { .ea-grid2 { grid-template-columns: 1fr 1fr; } }
</style>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← <?= e($rental->eventName) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Audio &amp; Durchsagen</h1>
        <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
            Jingles und weitere Audio-Dateien für die Veranstaltung. Über die Live-Oberfläche starten Veranstalter, Techniker und Admins Jingles, Audio-Dateien und Durchsagen
            (getippt per Sprachausgabe oder per Mikrofon aufgenommen, wahlweise mit Gong). Abgespielt wird vor Ort vom zugeordneten Abspielgerät am Mischpult.
        </p>
    </div>
    <a href="/veranstaltungen/<?= e($rental->id) ?>/live" class="<?= $primary ?>">Live-Oberfläche öffnen</a>
</div>

<div class="max-w-5xl space-y-6">
    <!-- Dateien -->
    <section id="dateien" class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Dateien hochladen</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">WAV, FLAC, AAC (.aac/.m4a) oder MP3, je Datei bis <?= (int) $settings->maxUploadMb ?> MB. Mehrere Dateien auf einmal möglich – der Titel ist dann der Dateiname und lässt sich danach ändern.</p>
        <form method="post" action="<?= e($base) ?>/dateien" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="ea-grid2">
                <div>
                    <label class="<?= $label ?>" for="ea-kind">Art</label>
                    <select id="ea-kind" name="kind" class="mt-1 <?= $input ?>">
                        <option value="jingle">Jingle (für Minutenprogramme)</option>
                        <option value="audio">Weitere Audio-Datei</option>
                    </select>
                </div>
                <div>
                    <label class="<?= $label ?>" for="ea-files">Datei(en)</label>
                    <input id="ea-files" type="file" name="files[]" multiple required accept="<?= e(AudioFormat::acceptAttribute()) ?>" class="mt-1 block w-full text-sm text-slate-600 dark:text-slate-300">
                </div>
                <div>
                    <label class="<?= $label ?>" for="ea-title">Titel <span class="font-normal text-slate-400">(optional, nur bei einer Datei)</span></label>
                    <input id="ea-title" type="text" name="title" maxlength="160" class="mt-1 <?= $input ?>" placeholder="z. B. Opener, Einlaufmusik">
                </div>
                <div>
                    <label class="<?= $label ?>" for="ea-desc">Kurze Beschreibung <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="ea-desc" type="text" name="description" maxlength="500" class="mt-1 <?= $input ?>" placeholder="z. B. Vor der Siegerehrung">
                </div>
            </div>
            <button type="submit" class="<?= $primary ?>">Hochladen</button>
        </form>
    </section>

    <?php foreach ([['Jingles', $jingles, 'Noch keine Jingles. Im Minutenprogramm wählst du, welcher Jingle vorher gespielt wird.'], ['Weitere Audio-Dateien', $audioFiles, 'Noch keine weiteren Audio-Dateien.']] as [$heading, $list, $empty]): ?>
        <section class="<?= $card ?>">
            <h2 class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e($heading) ?> <span class="font-normal text-slate-400">(<?= count($list) ?>)</span></h2>
            <?php if ($list === []): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($empty) ?></p>
            <?php endif; ?>
            <div>
                <?php foreach ($list as $file): ?>
                    <div class="ea-row">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-slate-900 dark:text-slate-100"><?= e($file->title) ?></div>
                            <?php if ($file->description !== null): ?>
                                <div class="text-xs text-slate-500 dark:text-slate-400"><?= e($file->description) ?></div>
                            <?php endif; ?>
                            <div class="truncate text-xs text-slate-400 dark:text-slate-500"><?= e($file->originalFilename) ?> · <?= e($fmtSize($file->sizeBytes)) ?></div>
                        </div>
                        <audio controls preload="none" src="<?= e($base) ?>/dateien/<?= e($file->id) ?>"></audio>
                        <details class="relative" style="justify-self:end">
                            <summary class="cursor-pointer list-none text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Bearbeiten</summary>
                            <div class="absolute right-0 z-20 mt-2 w-80 rounded-lg border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                <form method="post" action="<?= e($base) ?>/dateien/<?= e($file->id) ?>" class="space-y-2">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <label class="<?= $label ?>">Titel<input type="text" name="title" value="<?= e($file->title) ?>" required maxlength="160" class="mt-1 <?= $input ?>"></label>
                                    <label class="<?= $label ?>">Beschreibung<input type="text" name="description" value="<?= e($file->description ?? '') ?>" maxlength="500" class="mt-1 <?= $input ?>"></label>
                                    <label class="<?= $label ?>">Art
                                        <select name="kind" class="mt-1 <?= $input ?>">
                                            <?php foreach (AudioFileKind::cases() as $kind): ?>
                                                <option value="<?= e($kind->value) ?>" <?= $kind === $file->kind ? 'selected' : '' ?>><?= e($kind->label()) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button type="submit" class="<?= $btn ?>">Speichern</button>
                                </form>
                                <form method="post" action="<?= e($base) ?>/dateien/<?= e($file->id) ?>/loeschen" class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800"
                                      data-confirm="„<?= e($file->title) ?>“ löschen? Minutenprogramme, die diesen Jingle nutzen, haben danach keinen Jingle mehr." data-confirm-style="danger">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Datei löschen</button>
                                </form>
                            </div>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if ($schedules !== null): ?>
    <!-- Geplante Einspieler -->
    <section id="planung" class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Geplante Einspieler</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Jingles oder Audio-Dateien zu einer festen Uhrzeit abspielen. Zur Startzeit wird der Einspieler automatisch an das Abspielgerät geschickt.
            Ist das Gerät zu dem Zeitpunkt länger als <?= (int) $settings->queueExpiryMinutes ?> Minuten nicht erreichbar, wird er als „verpasst“ markiert statt verspätet gespielt.
        </p>
        <?php if ($schedules === []): ?>
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">Noch nichts geplant.</p>
        <?php else: ?>
            <div class="mb-4 overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width: 560px">
                    <thead class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr><th class="py-2 pr-3">Zeitpunkt</th><th class="py-2 pr-3">Einspieler</th><th class="py-2 pr-3">Geplant von</th><th class="py-2 pr-3">Status</th><th class="py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($schedules as $s): ?>
                            <tr>
                                <td class="whitespace-nowrap py-2 pr-3 font-medium text-slate-900 dark:text-slate-100"><?= e($s->playAt->format('d.m.Y H:i')) ?></td>
                                <td class="py-2 pr-3 text-slate-700 dark:text-slate-300"><?= e($fileTitles[$s->audioFileId] ?? 'Audio-Datei') ?><?php if ($s->note !== null): ?><div class="text-xs text-slate-500 dark:text-slate-400"><?= e($s->note) ?></div><?php endif; ?></td>
                                <td class="py-2 pr-3 text-slate-500 dark:text-slate-400"><?= e($s->createdByName) ?></td>
                                <td class="py-2 pr-3"><span class="ea-pill" style="<?= e($s->status->badgeStyle()) ?>"><?= e($s->status->label()) ?></span></td>
                                <td class="py-2 text-right">
                                    <?php if ($s->status === \App\Domain\EventAudio\ScheduledPlaybackStatus::Triggered || $s->status === \App\Domain\EventAudio\ScheduledPlaybackStatus::Missed): ?>
                                        <form method="post" action="<?= e($base) ?>/planung/<?= e($s->id) ?>/erneut" data-confirm="„<?= e($fileTitles[$s->audioFileId] ?? 'Audio-Datei') ?>“ jetzt erneut einspielen?">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <button type="submit" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Erneut einspielen</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($s->status === \App\Domain\EventAudio\ScheduledPlaybackStatus::Pending): ?>
                                        <form method="post" action="<?= e($base) ?>/planung/<?= e($s->id) ?>/loeschen" data-confirm="Geplanten Einspieler um <?= e($s->playAt->format('H:i')) ?> Uhr abbrechen?">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Abbrechen</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e($base) ?>/planung" enctype="multipart/form-data" class="border-t border-slate-100 pt-4 dark:border-slate-800">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Neuer Einspieler</h3>
            <div class="ea-grid2" style="grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));">
                <div>
                    <span class="<?= $label ?>">Zeitpunkt(e)</span>
                    <div data-times class="space-y-2">
                        <div class="flex items-center gap-2" data-time-row>
                            <input type="datetime-local" name="play_at[]" required value="<?= e($defaultPlayAt->format('Y-m-d\TH:i')) ?>" min="<?= e($rental->startDate->modify('-1 day')->format('Y-m-d')) ?>T00:00" max="<?= e($rental->endDate->modify('+1 day')->format('Y-m-d')) ?>T23:59" class="mt-1 <?= $input ?>">
                            <button type="button" data-time-remove class="mt-1 text-sm text-slate-400 hover:text-red-600" aria-label="Zeitpunkt entfernen" hidden>✕</button>
                        </div>
                    </div>
                    <button type="button" data-time-add class="mt-2 text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">+ weiterer Zeitpunkt</button>
                </div>
                <label class="<?= $label ?>">Jingle / Audio-Datei
                    <select name="audio_file_id" class="mt-1 <?= $input ?>">
                        <option value="">– eigene Datei hochladen –</option>
                        <?php if ($jingles !== []): ?><optgroup label="Jingles"><?php foreach ($jingles as $f): ?><option value="<?= e($f->id) ?>"><?= e($f->title) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                        <?php if ($audioFiles !== []): ?><optgroup label="Weitere Audio-Dateien"><?php foreach ($audioFiles as $f): ?><option value="<?= e($f->id) ?>"><?= e($f->title) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                    </select>
                </label>
                <label class="<?= $label ?>">oder Datei hochladen<input type="file" name="file" accept="<?= e(AudioFormat::acceptAttribute()) ?>" class="mt-2 block w-full text-sm text-slate-600 dark:text-slate-300"></label>
                <label class="<?= $label ?>">Notiz <span class="font-normal text-slate-400">(optional)</span><input type="text" name="note" maxlength="255" placeholder="z. B. Einlass" class="mt-1 <?= $input ?>"></label>
            </div>
            <button type="submit" class="<?= $primary ?> mt-3">Einplanen</button>
        </form>
    </section>
    <?php endif; ?>

    <!-- Abspielgerät -->
    <section id="geraet" class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Abspielgerät &amp; Gong</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Das Abspielgerät (Raspberry Pi am Mischpult) holt die Aufträge dieser Veranstaltung ab dem Vortag bis zum Tag nach dem Ende ab.
            Geräte, Gong und Sprachausgabe verwaltest du unter <a href="/einstellungen/veranstaltungs-audio" class="underline">Einstellungen › Veranstaltungs-Audio</a>.
        </p>
        <?php if ($player !== null): ?>
            <p class="mb-3 text-sm text-slate-700 dark:text-slate-300">
                <span class="ea-dot" style="background: <?= $playerOnline ? '#10b981' : '#94a3b8' ?>"></span>
                <?= e($player->name) ?>: <?= $playerOnline ? 'online' : 'offline' ?><?= $player->lastSeenAt !== null ? ' · zuletzt gesehen ' . e($player->lastSeenAt->format('d.m.Y H:i')) : ' · noch nie verbunden' ?>
            </p>
        <?php endif; ?>
        <form method="post" action="<?= e($base) ?>/einstellungen" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div style="min-width: 16rem">
                <label class="<?= $label ?>" for="ea-player">Abspielgerät</label>
                <select id="ea-player" name="player_id" class="mt-1 <?= $input ?>">
                    <option value="">– keines –</option>
                    <?php foreach ($players as $p): ?>
                        <option value="<?= e($p->id) ?>" <?= $p->id === $rentalSettings->playerId ? 'selected' : '' ?> <?= $p->enabled ? '' : 'disabled' ?>><?= e($p->name) ?><?= $p->enabled ? '' : ' (deaktiviert)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width: 14rem">
                <label class="<?= $label ?>" for="ea-lang">Sprache der Durchsagen</label>
                <select id="ea-lang" name="tts_language" class="mt-1 <?= $input ?>">
                    <option value="">– in der Live-Oberfläche wählen –</option>
                    <?php foreach (\App\Domain\EventAudio\TtsLanguage::OPTIONS as $code => $name): ?>
                        <option value="<?= e($code) ?>" <?= $code === $rentalSettings->ttsLanguage ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="gong_by_default" value="1" <?= $rentalSettings->gongByDefault ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300">
                Gong vor Durchsagen standardmäßig an
            </label>
            <button type="submit" class="<?= $btn ?>">Speichern</button>
        </form>
        <?php if ($players === []): ?>
            <p class="mt-3 text-xs text-amber-700 dark:text-amber-400">Es ist noch kein Abspielgerät angelegt.</p>
        <?php endif; ?>
    </section>

    <!-- Logo für Veranstalter -->
    <section id="logo" class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Logo für Veranstalter</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Eigenes Logo (z. B. des Veranstalters) für die Live-Oberfläche. Es erscheint nur bei den Veranstalter-Zugängen; Techniker und Admins sehen immer das Firmenlogo.
            Ohne eigenes Logo wird überall das Firmenlogo angezeigt. PNG, JPEG, WebP oder GIF bis 2 MB – am besten mit transparentem Hintergrund und hell auf dunkel lesbar.
        </p>
        <div class="flex flex-wrap items-center gap-4">
            <?php if ($rentalSettings->logoFilename !== null): ?>
                <div class="flex h-16 items-center rounded-md px-4" style="background:#0b1020; min-width: 8rem">
                    <img src="<?= e($base) ?>/logo?v=<?= e(substr(md5($rentalSettings->logoFilename), 0, 10)) ?>" alt="Logo der Veranstaltung" style="max-height: 40px; max-width: 160px; object-fit: contain">
                </div>
            <?php endif; ?>
            <form method="post" action="<?= e($base) ?>/logo" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="file" name="logo" required accept="image/png,image/jpeg,image/webp,image/gif" class="text-sm text-slate-600 dark:text-slate-300">
                <button type="submit" class="<?= $btn ?>"><?= $rentalSettings->logoFilename !== null ? 'Ersetzen' : 'Hochladen' ?></button>
            </form>
            <?php if ($rentalSettings->logoFilename !== null): ?>
                <form method="post" action="<?= e($base) ?>/logo/loeschen" data-confirm="Logo entfernen? Veranstalter sehen dann wieder das Firmenlogo.">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <!-- Zugänge -->
    <section id="zugaenge" class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Veranstalter-Zugänge</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Eigene Logins für die Live-Oberfläche – unabhängig von den Benutzerkonten und nur für diese Veranstaltung. Anmeldung unter
            <a href="/live/login" class="font-mono underline" target="_blank" rel="noopener"><?= e($loginUrl) ?></a>.
            Techniker und Admins nutzen stattdessen ihren normalen Login („Live-Oberfläche öffnen“).
        </p>

        <?php if ($newCredentials !== null): ?>
            <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                <p class="font-semibold">Zugangsdaten – jetzt notieren oder weitergeben, das Passwort wird nicht noch einmal angezeigt:</p>
                <p class="mt-2 font-mono" id="ea-cred">Adresse: <?= e($loginUrl) ?><br>Benutzername: <?= e($newCredentials['username']) ?><br>Passwort: <?= e($newCredentials['password']) ?></p>
                <button type="button" class="<?= $btn ?> mt-3" data-copy-target="ea-cred">Kopieren</button>
            </div>
        <?php endif; ?>

        <?php if ($logins === []): ?>
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">Noch keine Zugänge angelegt.</p>
        <?php endif; ?>
        <div class="space-y-3">
            <?php foreach ($logins as $login): ?>
                <?php $status = $login->statusLabel($now); ?>
                <form method="post" action="<?= e($base) ?>/zugaenge/<?= e($login->id) ?>" class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e($login->username) ?></span>
                        <span class="ea-pill" style="<?= $status === 'aktiv' ? 'background:#d1fae5;color:#065f46' : 'background:#e2e8f0;color:#475569' ?>"><?= e($status) ?></span>
                        <?php if ($login->canSchedule): ?><span class="ea-pill" style="background:#dbeafe;color:#1e40af">plant Einspieler</span><?php endif; ?>
                        <?php if ($login->canUpload): ?><span class="ea-pill" style="background:#ede9fe;color:#5b21b6">lädt Dateien hoch</span><?php endif; ?>
                        <?php if ($login->lastLoginAt !== null): ?>
                            <span class="text-xs text-slate-400">zuletzt angemeldet <?= e($login->lastLoginAt->format('d.m.Y H:i')) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ea-grid2" style="grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));">
                        <label class="<?= $label ?>">Bezeichnung<input type="text" name="label" value="<?= e($login->label) ?>" maxlength="120" class="mt-1 <?= $input ?>"></label>
                        <label class="<?= $label ?>">Gültig ab<input type="datetime-local" name="valid_from" value="<?= e($dtLocal($login->validFrom)) ?>" class="mt-1 <?= $input ?>"></label>
                        <label class="<?= $label ?>">Gültig bis<input type="datetime-local" name="valid_until" value="<?= e($dtLocal($login->validUntil)) ?>" class="mt-1 <?= $input ?>"></label>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input type="checkbox" name="enabled" value="1" <?= $login->enabled ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300"> aktiv</label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input type="checkbox" name="can_schedule" value="1" <?= $login->canSchedule ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300"> darf Einspieler planen</label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input type="checkbox" name="can_upload" value="1" <?= $login->canUpload ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300"> darf Sounddateien hochladen</label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input type="checkbox" name="reset_password" value="1" class="h-4 w-4 rounded border-slate-300"> neues Passwort erzeugen</label>
                        <button type="submit" class="<?= $btn ?>">Speichern</button>
                        <button type="submit" form="ea-del-<?= e($login->id) ?>" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                    </div>
                </form>
                <form method="post" id="ea-del-<?= e($login->id) ?>" action="<?= e($base) ?>/zugaenge/<?= e($login->id) ?>/loeschen" hidden
                      data-confirm="Zugang „<?= e($login->username) ?>“ löschen?" data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                </form>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?= e($base) ?>/zugaenge" class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Neuer Zugang</h3>
            <div class="ea-grid2" style="grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));">
                <label class="<?= $label ?>">Bezeichnung<input type="text" name="label" maxlength="120" placeholder="z. B. Veranstalter, Moderation" class="mt-1 <?= $input ?>"></label>
                <label class="<?= $label ?>">Benutzername<input type="text" name="username" value="<?= e($suggestedUsername) ?>" required maxlength="80" pattern="[a-z0-9._\-]{3,80}" class="mt-1 font-mono <?= $input ?>"></label>
                <label class="<?= $label ?>">Passwort <span class="font-normal text-slate-400">(leer = erzeugen)</span><input type="text" name="password" minlength="8" autocomplete="new-password" class="mt-1 <?= $input ?>"></label>
                <label class="<?= $label ?>">Gültig ab<input type="datetime-local" name="valid_from" value="<?= e($dtLocal($defaultFrom)) ?>" class="mt-1 <?= $input ?>"></label>
                <label class="<?= $label ?>">Gültig bis<input type="datetime-local" name="valid_until" value="<?= e($dtLocal($defaultUntil)) ?>" class="mt-1 <?= $input ?>"></label>
            </div>
            <label class="mt-3 flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="can_schedule" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                <span>Darf Einspieler zeitlich planen <span class="block text-xs text-slate-500 dark:text-slate-400">vorhandene Jingles und Audio-Dateien zu festen Zeiten abspielen</span></span>
            </label>
            <label class="mt-2 flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="can_upload" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                <span>Darf eigene Sounddateien hochladen <span class="block text-xs text-slate-500 dark:text-slate-400">in der Live-Oberfläche (WAV, FLAC, AAC, MP3); Techniker und Admins dürfen das immer</span></span>
            </label>
            <button type="submit" class="<?= $primary ?> mt-3">Zugang anlegen</button>
        </form>
    </section>

    <!-- Protokoll -->
    <section id="auftraege" class="<?= $card ?>">
        <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200">Letzte Aufträge</h2>
        <?php if ($recent === []): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">Noch nichts abgespielt.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width: 560px">
                    <thead class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr><th class="py-2 pr-3">Zeit</th><th class="py-2 pr-3">Art</th><th class="py-2 pr-3">Inhalt</th><th class="py-2 pr-3">Von</th><th class="py-2 pr-3">Status</th><th class="py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td class="whitespace-nowrap py-2 pr-3 text-slate-500 dark:text-slate-400"><?= e($r->createdAt->format('d.m. H:i:s')) ?></td>
                                <td class="whitespace-nowrap py-2 pr-3 text-slate-700 dark:text-slate-300"><?= e($r->kind->label()) ?><?= $r->withGong ? ' + Gong' : '' ?></td>
                                <td class="py-2 pr-3 text-slate-700 dark:text-slate-300"><?= e($r->text ?? $r->label ?? '') ?></td>
                                <td class="py-2 pr-3 text-slate-500 dark:text-slate-400"><?= e($r->requestedByName) ?></td>
                                <td class="py-2 pr-3"><span class="ea-pill" style="<?= e($r->status->badgeStyle()) ?>" title="<?= e($r->error ?? '') ?>"><?= e($r->status->label()) ?></span></td>
                                <td class="whitespace-nowrap py-2 text-right">
                                    <?php if (!$r->status->isOpen() && $r->kind !== \App\Domain\EventAudio\PlaybackKind::Stop): ?>
                                        <form method="post" action="<?= e($base) ?>/erneut/<?= e($r->id) ?>" data-confirm="„<?= e($r->text ?? $r->label ?? $r->kind->label()) ?>“ erneut einspielen?">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <button type="submit" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Erneut einspielen</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
    // Mehrere Zeitpunkte für einen geplanten Einspieler
    document.querySelectorAll('[data-time-add]').forEach(function (add) {
        var box = add.parentElement.querySelector('[data-times]');
        function sync() {
            var rows = box.querySelectorAll('[data-time-row]');
            rows.forEach(function (r) { r.querySelector('[data-time-remove]').hidden = rows.length < 2; });
        }
        add.addEventListener('click', function () {
            var rows = box.querySelectorAll('[data-time-row]');
            var last = rows[rows.length - 1];
            var clone = last.cloneNode(true);
            var input = clone.querySelector('input');
            // Vorschlag: eine Stunde nach dem letzten Zeitpunkt
            var v = last.querySelector('input').value;
            if (v) { var d = new Date(v); d.setHours(d.getHours() + 1); var p = function (n) { return ('0' + n).slice(-2); }; input.value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes()); }
            box.appendChild(clone);
            sync();
            input.focus();
        });
        box.addEventListener('click', function (e) {
            var rm = e.target.closest('[data-time-remove]');
            if (rm) { rm.closest('[data-time-row]').remove(); sync(); }
        });
    });
    document.querySelectorAll('[data-copy-target]').forEach(function (b) {
        b.addEventListener('click', function () {
            var el = document.getElementById(b.dataset.copyTarget);
            if (!el || !navigator.clipboard) { return; }
            navigator.clipboard.writeText(el.innerText).then(function () { b.textContent = 'Kopiert ✓'; });
        });
    });
</script>
