<?php
/**
 * Live-Oberfläche „Audio & Durchsagen“ (Migration 135) – eigenständige,
 * für Smartphones gebaute Seite für Veranstalter, Techniker und Admins.
 *
 * @var \App\Domain\Rental\Rental $rental
 * @var string $basePath
 * @var string $actorName
 * @var bool $organizer
 * @var \App\Domain\EventAudio\RentalAudioFile[] $jingles
 * @var \App\Domain\EventAudio\RentalAudioFile[] $audioFiles
 * @var list<array{name: string, startAt: \DateTimeImmutable, jingleId: string, jingleTitle: string}> $programs
 * @var bool $gongByDefault
 * @var bool $hasPlayer
 * @var string $ttsLanguage
 * @var array<string, mixed> $initialStatus
 * @var string $csrfToken
 * @var bool $canSchedule
 * @var bool $canUpload
 * @var ?string $rentalLanguage feste Sprache der Veranstaltung (null = wählbar)
 * @var array<string, string> $languages
 * @var ?string $customLogoUrl eigenes Veranstaltungs-Logo (nur Veranstalter)
 * @var int $maxUploadMb
 * @var array{0: string, 1: string} $rentalWindow
 */

use App\Domain\EventAudio\AudioFormat;
use App\Infrastructure\Http\CompanyBranding;

use function App\Infrastructure\Http\e;

$logo = ($customLogoUrl ?? null) ?? CompanyBranding::darkLogoUrl() ?? CompanyBranding::logoUrl();
$canSchedule ??= false;
$canUpload ??= false;
$rentalLanguage ??= null;
$languages ??= [];
$defaultPlayAt = (new DateTimeImmutable('+10 minutes'))->format('Y-m-d\TH:i');
$period = $rental->startDate->format('d.m.Y') . ($rental->endDate->format('Y-m-d') !== $rental->startDate->format('Y-m-d') ? ' – ' . $rental->endDate->format('d.m.Y') : '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<meta name="robots" content="noindex">
<title>Live – <?= e($rental->eventName) ?></title>
<style>
    :root {
        --bg: #0b1020; --panel: #121a2e; --panel-2: #18223b; --line: #24304d; --text: #e8ecf6; --muted: #8b97b5;
        --accent: #f5b43c; --accent-ink: #1a1203; --go: #22c55e; --stop: #ef4444; --info: #60a5fa;
        --radius: 14px;
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); }
    body { min-height: 100vh; padding-bottom: calc(5.5rem + env(safe-area-inset-bottom)); -webkit-tap-highlight-color: transparent; }
    button, textarea, input { font: inherit; color: inherit; }
    button { cursor: pointer; }
    a { color: inherit; }
    .wrap { max-width: 960px; margin: 0 auto; padding: 0 16px; }

    header.top { position: sticky; top: 0; z-index: 20; background: rgba(11,16,32,.92); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
    .top-inner { display: flex; align-items: center; gap: 12px; padding-top: 10px; padding-bottom: 10px; }
    .top-inner img { max-height: 40px; max-width: 140px; object-fit: contain; }
    .event { min-width: 0; flex: 1; }
    .event h1 { margin: 0; font-size: 1rem; font-weight: 700; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .event p { margin: 2px 0 0; font-size: .75rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .who { font-size: .75rem; color: var(--muted); text-align: right; }
    .who a, .who button { background: none; border: 0; padding: 0; color: var(--muted); text-decoration: underline; font-size: .75rem; }

    .status { display: flex; align-items: center; gap: 10px; padding: 10px 14px; margin: 14px 0 0; border-radius: var(--radius); background: var(--panel); border: 1px solid var(--line); font-size: .85rem; }
    .led { width: 10px; height: 10px; border-radius: 99px; background: #64748b; flex: none; }
    .led.on { background: var(--go); box-shadow: 0 0 0 4px rgba(34,197,94,.18); }
    .led.warn { background: var(--accent); }
    .status .np { margin-left: auto; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 50%; }
    .eq { display: inline-flex; gap: 2px; align-items: flex-end; height: 12px; margin-right: 6px; vertical-align: -1px; }
    .eq i { width: 3px; background: var(--go); animation: eq 0.9s ease-in-out infinite; }
    .eq i:nth-child(2) { animation-delay: .2s; } .eq i:nth-child(3) { animation-delay: .4s; }
    @keyframes eq { 0%,100% { height: 3px; } 50% { height: 12px; } }

    section.card { margin-top: 16px; background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); padding: 16px; }
    section.card h2 { margin: 0 0 12px; font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 700; display: flex; align-items: center; gap: 8px; }
    section.card h2 .count { color: var(--line); }

    .seg { display: inline-flex; background: var(--panel-2); border: 1px solid var(--line); border-radius: 999px; padding: 3px; margin-bottom: 12px; }
    .seg button { border: 0; background: none; padding: 7px 16px; border-radius: 999px; font-size: .85rem; font-weight: 600; color: var(--muted); }
    .seg button[aria-selected="true"] { background: var(--text); color: var(--bg); }

    textarea { width: 100%; min-height: 110px; resize: vertical; background: var(--bg); border: 1px solid var(--line); border-radius: 10px; padding: 12px; font-size: 1rem; line-height: 1.45; }
    textarea:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
    .row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 12px; }
    .spacer { flex: 1; }
    .hint { font-size: .75rem; color: var(--muted); }

    .toggle { display: inline-flex; align-items: center; gap: 8px; font-size: .9rem; user-select: none; }
    .toggle input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .toggle .sw { width: 42px; height: 24px; border-radius: 99px; background: var(--line); position: relative; transition: background .15s; }
    .toggle .sw::after { content: ""; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 99px; background: #fff; transition: transform .15s; }
    .toggle input:checked + .sw { background: var(--accent); }
    .toggle input:checked + .sw::after { transform: translateX(18px); }
    .toggle input:focus-visible + .sw { outline: 2px solid var(--info); outline-offset: 2px; }

    .btn { border: 1px solid var(--line); background: var(--panel-2); border-radius: 10px; padding: 10px 14px; font-weight: 600; font-size: .9rem; }
    .btn:disabled { opacity: .45; cursor: default; }
    .btn.primary { background: var(--accent); color: var(--accent-ink); border-color: var(--accent); }
    .btn.big { padding: 14px 20px; font-size: 1rem; }

    .rec { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 8px 0 4px; }
    .rec-btn { width: 112px; height: 112px; border-radius: 999px; border: 4px solid var(--line); background: radial-gradient(circle at 50% 40%, #ff6b6b, #c81e1e); box-shadow: 0 10px 30px rgba(239,68,68,.25); display: grid; place-items: center; color: #fff; font-weight: 800; letter-spacing: .06em; font-size: .8rem; }
    .rec-btn.recording { animation: pulse 1.2s ease-in-out infinite; border-color: #fecaca; }
    @keyframes pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,.55); } 50% { box-shadow: 0 0 0 18px rgba(239,68,68,0); } }
    .timer { font-variant-numeric: tabular-nums; font-size: 1.5rem; font-weight: 700; }
    .rec audio { width: 100%; max-width: 420px; }

    .tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
    .tile { position: relative; text-align: left; border: 1px solid var(--line); background: var(--panel-2); border-radius: 12px; padding: 14px 12px 12px; min-height: 84px; display: flex; flex-direction: column; gap: 4px; transition: transform .08s, border-color .15s, background .15s; }
    .tile:active { transform: scale(.98); }
    .tile .t { font-weight: 700; font-size: .95rem; line-height: 1.2; }
    .tile .d { font-size: .75rem; color: var(--muted); line-height: 1.3; }
    .tile .k { position: absolute; top: 10px; right: 10px; font-size: .65rem; color: var(--muted); }
    .tile.jingle { border-left: 4px solid var(--accent); }
    .tile.audio { border-left: 4px solid var(--info); }
    .field { display: block; font-size: .75rem; color: var(--muted); margin-top: 10px; }
    .field input, .field select { display: block; width: 100%; margin-top: 4px; background: var(--bg); border: 1px solid var(--line); border-radius: 10px; padding: 11px 12px; font-size: 1rem; color: var(--text); }
    .field input[type=file] { padding: 9px; font-size: .85rem; }
    .field input:focus, .field select:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
    .grid2 { display: grid; grid-template-columns: 1fr; gap: 0 12px; }
    @media (min-width: 520px) { .grid2 { grid-template-columns: 1fr 1fr; } }
    .time-row { display: flex; gap: 6px; align-items: center; margin-top: 4px; }
    .time-row input { flex: 1; min-width: 0; margin-top: 0 !important; }
    .time-rm { flex: none; width: 40px; height: 44px; border: 1px solid var(--line); background: var(--panel-2); border-radius: 10px; color: var(--muted); }
    .sync { display: block; font-size: .72rem; color: var(--muted); margin-top: 2px; }
    .sync.stale { color: #fca5a5; }
    .x.replay { color: var(--accent); }
    .or { text-align: center; font-size: .72rem; color: var(--muted); margin: 10px 0 -2px; letter-spacing: .1em; text-transform: uppercase; }
    .sched { list-style: none; margin: 0 0 4px; padding: 0; }
    .sched li { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-top: 1px solid var(--line); font-size: .85rem; }
    .sched li:first-child { border-top: 0; }
    .sched .when { width: 4.2rem; flex: none; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--accent); line-height: 1.1; }
    .sched .lb { flex: 1; min-width: 0; }
    .sched .lb b { display: block; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sched .lb span { font-size: .72rem; color: var(--muted); }
    .sched .when small { display: block; font-weight: 500; font-size: .68rem; color: var(--muted); }
    .pill.pending { background: rgba(245,180,60,.15); color: var(--accent); }
    .pill.triggered { background: rgba(34,197,94,.15); color: var(--go); }
    .pill.missed { background: rgba(239,68,68,.18); color: #fca5a5; }
    .modal { position: fixed; inset: 0; z-index: 50; display: grid; place-items: end center; padding: 16px 16px calc(16px + env(safe-area-inset-bottom)); background: rgba(3,6,15,.6); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); opacity: 0; transition: opacity .15s; }
    .modal.show { opacity: 1; }
    @media (min-width: 640px) { .modal { place-items: center; } }
    .modal-box { width: 100%; max-width: 420px; background: var(--panel); border: 1px solid var(--line); border-radius: 18px; padding: 20px; box-shadow: 0 20px 60px rgba(0,0,0,.5); transform: translateY(12px); transition: transform .15s; }
    .modal.show .modal-box { transform: none; }
    .modal-box .q { margin: 0; font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .modal-box .what { margin: 8px 0 4px; font-size: 1.25rem; font-weight: 700; line-height: 1.25; }
    .modal-box .sub { margin: 0; font-size: .85rem; color: var(--muted); }
    .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
    .modal-actions .btn { flex: 1; padding: 14px; font-size: 1rem; }

    .prog { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid var(--line); }
    .prog:first-of-type { border-top: 0; }
    .prog time { font-variant-numeric: tabular-nums; color: var(--accent); font-weight: 700; width: 3.2rem; flex: none; }
    .prog .n { flex: 1; min-width: 0; }
    .prog .n b { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .prog .n span { font-size: .75rem; color: var(--muted); }

    .log { list-style: none; margin: 0; padding: 0; }
    .log li { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-top: 1px solid var(--line); font-size: .85rem; }
    .log li:first-child { border-top: 0; }
    .log .tm { color: var(--muted); font-variant-numeric: tabular-nums; width: 4.2rem; flex: none; font-size: .75rem; }
    .log .lb { flex: 1; min-width: 0; }
    .log .lb b { display: block; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .log .lb span { font-size: .72rem; color: var(--muted); }
    .pill { font-size: .68rem; font-weight: 700; border-radius: 99px; padding: 2px 8px; white-space: nowrap; }
    .pill.queued { background: rgba(245,180,60,.15); color: var(--accent); }
    .pill.playing { background: rgba(96,165,250,.18); color: var(--info); }
    .pill.done { background: rgba(34,197,94,.15); color: var(--go); }
    .pill.failed { background: rgba(239,68,68,.18); color: #fca5a5; }
    .pill.cancelled { background: rgba(139,151,181,.15); color: var(--muted); }
    .x { background: none; border: 0; color: var(--muted); font-size: .75rem; text-decoration: underline; padding: 0; }

    .stopbar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 30; background: linear-gradient(to top, var(--bg) 60%, rgba(11,16,32,0)); padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); }
    .stopbar button { display: block; width: 100%; max-width: 928px; margin: 0 auto; border: 0; border-radius: var(--radius); background: var(--stop); color: #fff; font-weight: 800; letter-spacing: .14em; font-size: 1rem; padding: 16px; box-shadow: 0 8px 24px rgba(239,68,68,.3); }

    .toast { position: fixed; left: 50%; bottom: calc(6rem + env(safe-area-inset-bottom)); transform: translateX(-50%) translateY(20px); opacity: 0; z-index: 40; max-width: calc(100% - 32px); background: var(--text); color: var(--bg); padding: 10px 16px; border-radius: 10px; font-size: .9rem; font-weight: 600; transition: all .2s; pointer-events: none; }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast.err { background: #fecaca; color: #7f1d1d; }
    .empty { color: var(--muted); font-size: .9rem; margin: 0; }
    .warnbox { margin-top: 14px; padding: 10px 14px; border-radius: var(--radius); background: rgba(245,180,60,.12); border: 1px solid rgba(245,180,60,.35); color: #fcd9a0; font-size: .85rem; }
    @media (min-width: 760px) { .cols { display: grid; grid-template-columns: 1.1fr .9fr; gap: 16px; align-items: start; } .cols > div > section.card:first-child { margin-top: 16px; } }
    [hidden] { display: none !important; }
</style>
</head>
<body>
<header class="top">
    <div class="wrap top-inner">
        <?php if ($logo !== null): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <div class="event">
            <h1><?= e($rental->eventName) ?></h1>
            <p><?= e($period) ?><?= $rental->eventLocation ? ' · ' . e($rental->eventLocation) : '' ?></p>
        </div>
        <div class="who">
            <?= e($actorName) ?><br>
            <?php if ($organizer): ?>
                <form method="post" action="/live/abmelden" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><button type="submit">Abmelden</button></form>
            <?php else: ?>
                <a href="/veranstaltungen/<?= e($rental->id) ?>">Zur Veranstaltung</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="wrap">
    <div class="status" id="status" aria-live="polite">
        <span class="led" id="led"></span><span><span id="status-text">Verbinde …</span><span class="sync" id="sync" hidden></span></span><span class="np" id="now-playing"></span>
    </div>
    <?php if (!$hasPlayer): ?>
        <div class="warnbox">Dieser Veranstaltung ist noch kein Abspielgerät zugeordnet. Aufträge werden gesammelt, aber erst gespielt, wenn ein Gerät zugeordnet und verbunden ist.</div>
    <?php endif; ?>

    <div class="cols">
        <div>
            <section class="card" aria-labelledby="h-ann">
                <h2 id="h-ann">Durchsage</h2>
                <div class="seg" role="tablist">
                    <button type="button" role="tab" aria-selected="true" data-mode="text">Text</button>
                    <button type="button" role="tab" aria-selected="false" data-mode="mic">Mikrofon</button>
                </div>

                <div id="mode-text">
                    <textarea id="tts-text" maxlength="1000" placeholder="Text der Durchsage, z. B. „Die Siegerehrung beginnt in fünf Minuten auf der Hauptbühne.“"></textarea>
                    <div class="row">
                        <span class="hint"><span id="tts-count">0</span>/1000</span>
                        <span class="spacer"></span>
                        <?php if ($rentalLanguage === null): ?>
                            <select id="tts-lang" class="btn" aria-label="Sprache der Durchsage" style="padding: 9px 10px">
                                <?php foreach ($languages as $code => $name): ?>
                                    <option value="<?= e($code) ?>" <?= $code === $ttsLanguage ? 'selected' : '' ?>><?= e($name) ?></option>
                                <?php endforeach; ?>
                                <?php if (!isset($languages[$ttsLanguage])): ?><option value="<?= e($ttsLanguage) ?>" selected><?= e($ttsLanguage) ?></option><?php endif; ?>
                            </select>
                        <?php else: ?>
                            <span class="hint">Sprache: <?= e($languages[$rentalLanguage] ?? $rentalLanguage) ?></span>
                        <?php endif; ?>
                        <button type="button" class="btn" id="tts-preview">Vorhören</button>
                    </div>
                </div>

                <div id="mode-mic" hidden>
                    <div class="rec">
                        <button type="button" class="rec-btn" id="rec-btn" aria-label="Aufnahme starten">AUFNEHMEN</button>
                        <div class="timer" id="rec-timer">0:00</div>
                        <p class="hint" id="rec-hint">Tippen zum Starten, nochmal tippen zum Beenden (max. 2 Minuten).</p>
                        <audio id="rec-audio" controls hidden></audio>
                    </div>
                </div>

                <div class="row">
                    <label class="toggle"><input type="checkbox" id="with-gong" <?= $gongByDefault ? 'checked' : '' ?>><span class="sw"></span>Gong vorher</label>
                    <span class="spacer"></span>
                    <button type="button" class="btn primary big" id="ann-send">Durchsage abspielen</button>
                </div>
            </section>

            <?php if ($programs !== []): ?>
                <section class="card" aria-labelledby="h-prog">
                    <h2 id="h-prog">Jingle zum Programmpunkt</h2>
                    <?php foreach ($programs as $p): ?>
                        <div class="prog">
                            <time><?= e($p['startAt']->format('H:i')) ?></time>
                            <div class="n"><b><?= e($p['name']) ?></b><span>♪ <?= e($p['jingleTitle']) ?></span></div>
                            <button type="button" class="btn" data-play="<?= e($p['jingleId']) ?>" data-title="<?= e($p['jingleTitle']) ?>" data-kind="jingle" data-sub="Vor „<?= e($p['name']) ?>“ (<?= e($p['startAt']->format('H:i')) ?> Uhr)" aria-label="Jingle für <?= e($p['name']) ?> abspielen">▶</button>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <section class="card" aria-labelledby="h-jingles">
                <h2 id="h-jingles">Jingles <span class="count"><?= count($jingles) ?></span></h2>
                <?php if ($jingles === []): ?>
                    <p class="empty">Keine Jingles hinterlegt.</p>
                <?php else: ?>
                    <div class="tiles">
                        <?php foreach ($jingles as $f): ?>
                            <button type="button" class="tile jingle" data-play="<?= e($f->id) ?>" data-title="<?= e($f->title) ?>" data-kind="jingle" data-sub="<?= e($f->description ?? '') ?>">
                                <span class="k">♪</span>
                                <span class="t"><?= e($f->title) ?></span>
                                <?php if ($f->description !== null): ?><span class="d"><?= e($f->description) ?></span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($audioFiles !== []): ?>
                <section class="card" aria-labelledby="h-audio">
                    <h2 id="h-audio">Weitere Audio-Dateien <span class="count"><?= count($audioFiles) ?></span></h2>
                    <div class="tiles">
                        <?php foreach ($audioFiles as $f): ?>
                            <button type="button" class="tile audio" data-play="<?= e($f->id) ?>" data-title="<?= e($f->title) ?>" data-kind="audio" data-sub="<?= e($f->description ?? '') ?>">
                                <span class="k">◉</span>
                                <span class="t"><?= e($f->title) ?></span>
                                <?php if ($f->description !== null): ?><span class="d"><?= e($f->description) ?></span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($canUpload): ?>
                <section class="card" aria-labelledby="h-upload">
                    <h2 id="h-upload">Eigene Sounddatei hochladen</h2>
                    <form id="upload-form">
                        <label class="field">Datei (WAV, FLAC, AAC, MP3 · max. <?= (int) $maxUploadMb ?> MB)<input type="file" name="file" required accept="<?= e(AudioFormat::acceptAttribute()) ?>"></label>
                        <div class="grid2">
                            <label class="field">Titel (optional)<input type="text" name="title" maxlength="160" placeholder="sonst Dateiname"></label>
                            <label class="field">Kurze Beschreibung (optional)<input type="text" name="description" maxlength="500"></label>
                        </div>
                        <div class="row"><span class="hint">Erscheint danach bei „Weitere Audio-Dateien“.</span><span class="spacer"></span><button type="submit" class="btn primary" id="upload-send">Hochladen</button></div>
                    </form>
                </section>
            <?php endif; ?>
        </div>

        <div>
            <section class="card" aria-labelledby="h-sched" id="sched-card">
                <h2 id="h-sched">Geplante Einspieler</h2>
                <ul class="sched" id="sched"></ul>
                <p class="empty" id="sched-empty">Nichts geplant.</p>
                <?php if ($canSchedule): ?>
                    <form id="sched-form" style="margin-top: 8px; border-top: 1px solid var(--line); padding-top: 6px;">
                        <div class="grid2">
                            <div class="field">Zeitpunkt(e)
                                <div id="sched-times">
                                    <div class="time-row"><input type="datetime-local" name="play_at[]" required value="<?= e($defaultPlayAt) ?>" min="<?= e($rentalWindow[0]) ?>" max="<?= e($rentalWindow[1]) ?>"><button type="button" class="time-rm" aria-label="Zeitpunkt entfernen" hidden>✕</button></div>
                                </div>
                                <button type="button" class="x" id="sched-add-time" style="margin-top:8px">+ weiterer Zeitpunkt</button>
                            </div>
                            <label class="field">Notiz (optional)<input type="text" name="note" maxlength="255" placeholder="z. B. Einlass"></label>
                        </div>
                        <label class="field">Jingle / Audio-Datei
                            <select name="audio_file_id" id="sched-file">
                                <option value="">– bitte wählen –</option>
                                <?php if ($jingles !== []): ?><optgroup label="Jingles"><?php foreach ($jingles as $f): ?><option value="<?= e($f->id) ?>"><?= e($f->title) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                                <?php if ($audioFiles !== []): ?><optgroup label="Weitere Audio-Dateien"><?php foreach ($audioFiles as $f): ?><option value="<?= e($f->id) ?>"><?= e($f->title) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                            </select>
                        </label>
                        <?php if ($canUpload): ?>
                            <p class="or">oder eigene Datei</p>
                            <label class="field">Audio-Datei hochladen (WAV, FLAC, AAC, MP3 · max. <?= (int) $maxUploadMb ?> MB)<input type="file" name="file" id="sched-upload" accept="<?= e(AudioFormat::acceptAttribute()) ?>"></label>
                        <?php endif; ?>
                        <div class="row"><span class="spacer"></span><button type="submit" class="btn primary big" id="sched-send">Einplanen</button></div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="card" aria-labelledby="h-log">
                <h2 id="h-log">Verlauf</h2>
                <ul class="log" id="log"></ul>
                <p class="empty" id="log-empty">Noch nichts abgespielt.</p>
            </section>
        </div>
    </div>
</main>

<div class="stopbar"><button type="button" id="stop-btn">■ STOPP</button></div>
<div class="modal" id="confirm" role="dialog" aria-modal="true" aria-labelledby="confirm-what" hidden>
    <div class="modal-box">
        <p class="q" id="confirm-kind">Jingle abspielen?</p>
        <p class="what" id="confirm-what"></p>
        <p class="sub" id="confirm-sub"></p>
        <div class="modal-actions">
            <button type="button" class="btn" id="confirm-no">Abbrechen</button>
            <button type="button" class="btn primary" id="confirm-yes">▶ Abspielen</button>
        </div>
    </div>
</div>
<div class="toast" id="toast" role="status"></div>

<script>
(function () {
    'use strict';
    var BASE = <?= json_encode($basePath, JSON_THROW_ON_ERROR) ?>;
    var CSRF = <?= json_encode($csrfToken, JSON_THROW_ON_ERROR) ?>;
    var CAN_SCHEDULE = <?= $canSchedule ? 'true' : 'false' ?>;
    var TTS_LANG = <?= json_encode($rentalLanguage ?? $ttsLanguage, JSON_THROW_ON_ERROR) ?>;
    var MAX_REC_MS = 120000;
    var $ = function (id) { return document.getElementById(id); };

    // ---------- Hilfsfunktionen
    var toastTimer = null;
    function toast(msg, isError) {
        var t = $('toast');
        t.textContent = msg;
        t.classList.toggle('err', !!isError);
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { t.classList.remove('show'); }, isError ? 5000 : 2600);
    }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    function post(path, data) {
        var fd = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData) && data) { Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); }
        fd.append('csrf_token', CSRF);
        return fetch(BASE + path, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(handle);
    }
    function handle(res) {
        return res.json().catch(function () { return { ok: false, error: 'Unerwartete Antwort vom Server (' + res.status + ').' }; }).then(function (json) {
            if (res.status === 401 && json.relogin) { location.href = '/live/login'; }
            if (!res.ok || json.ok === false) { throw new Error(json.error || 'Fehler ' + res.status); }
            return json;
        });
    }

    // ---------- Status / Verlauf
    function render(status) {
        var p = status.player;
        var led = $('led'), txt = $('status-text'), np = $('now-playing');
        led.className = 'led' + (p && p.online ? ' on' : (p ? '' : ' warn'));
        if (!p) { txt.textContent = 'Kein Abspielgerät zugeordnet'; np.textContent = ''; }
        else if (p.online) {
            txt.textContent = p.name + ' verbunden';
            np.innerHTML = p.nowPlaying ? '<span class="eq"><i></i><i></i><i></i></span>' + esc(p.nowPlaying) : '';
        } else { txt.textContent = p.name + ' offline' + (p.lastSeen && !status.detailed ? ' (zuletzt ' + p.lastSeen + ')' : ''); np.textContent = ''; }

        // Letzter Sync des Abspielgeräts – nur für Admins/Techniker
        var sync = $('sync');
        if (status.detailed && p) {
            sync.hidden = false;
            if (p.lastSync) {
                var ago = p.lastSyncAgo, agoText = ago < 60 ? 'vor ' + ago + ' s' : (ago < 3600 ? 'vor ' + Math.floor(ago / 60) + ' min' : 'am ' + p.lastSyncDate);
                sync.textContent = 'Letzter Sync: ' + p.lastSync + ' (' + agoText + ')';
                sync.classList.toggle('stale', ago > 45);
            } else { sync.textContent = 'Noch kein Sync – Gerät hat sich nie verbunden'; sync.classList.add('stale'); }
        } else { sync.hidden = true; }

        var list = $('log');
        list.innerHTML = (status.requests || []).map(function (r) {
            return '<li><span class="tm">' + esc(r.time) + '</span><span class="lb"><b>' + esc(r.label) + '</b><span>' + esc(r.kindLabel) + (r.gong ? ' · mit Gong' : '') + ' · ' + esc(r.by) + (r.error ? ' · ' + esc(r.error) : '') + '</span></span>'
                + (r.status === 'queued' ? '<button type="button" class="x" data-cancel="' + esc(r.id) + '">Abbrechen</button>' : '')
                + (r.replayable ? '<button type="button" class="x replay" data-replay="/erneut/' + esc(r.id) + '" data-label="' + esc(r.label) + '">Erneut</button>' : '')
                + '<span class="pill ' + esc(r.status) + '">' + esc(r.statusLabel) + '</span></li>';
        }).join('');
        $('log-empty').hidden = (status.requests || []).length > 0;

        var sched = status.schedules || [];
        $('sched').innerHTML = sched.map(function (s) {
            return '<li><span class="when">' + esc(s.time) + (s.today ? '' : '<small>' + esc(s.date) + '</small>') + '</span><span class="lb"><b>' + esc(s.title) + '</b><span>' + (s.note ? esc(s.note) + ' · ' : '') + esc(s.by) + '</span></span>'
                + (s.status === 'pending' && CAN_SCHEDULE ? '<button type="button" class="x" data-unschedule="' + esc(s.id) + '" data-label="' + esc(s.time + ' – ' + s.title) + '">Abbrechen</button>' : '')
                + (s.replayable ? '<button type="button" class="x replay" data-replay="/planung/' + esc(s.id) + '/erneut" data-label="' + esc(s.title) + '">Erneut</button>' : '')
                + '<span class="pill ' + esc(s.status) + '">' + esc(s.statusLabel) + '</span></li>';
        }).join('');
        $('sched-empty').hidden = sched.length > 0;
    }
    var initial = <?= json_encode($initialStatus, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
    render(initial);

    var polling = false;
    function poll() {
        if (polling || document.hidden) { return; }
        polling = true;
        fetch(BASE + '/status', { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(handle).then(render).catch(function () {
                $('led').className = 'led warn'; $('status-text').textContent = 'Keine Verbindung zum Server …';
            }).then(function () { polling = false; });
    }
    setInterval(poll, 3000);
    document.addEventListener('visibilitychange', poll);

    $('log').addEventListener('click', function (e) {
        var b = e.target.closest('[data-cancel]');
        if (!b) { return; }
        b.disabled = true;
        post('/abbrechen/' + encodeURIComponent(b.dataset.cancel)).then(function (s) { render(s); toast('Abgebrochen.'); }).catch(function (err) { toast(err.message, true); });
    });

    function afterRequest(s, okMsg) {
        render(s);
        toast(s.warning || okMsg, !!s.warning);
    }

    // ---------- Rückfrage-Dialog (Abspielen, Einspieler abbrechen)
    var confirmBox = $('confirm'), onYes = null, lastFocus = null;
    function askConfirm(kind, what, sub, yesLabel, cb) {
        onYes = cb;
        lastFocus = document.activeElement;
        $('confirm-kind').textContent = kind;
        $('confirm-what').textContent = what;
        $('confirm-sub').textContent = sub || '';
        $('confirm-yes').textContent = yesLabel;
        confirmBox.hidden = false;
        requestAnimationFrame(function () { confirmBox.classList.add('show'); });
        $('confirm-yes').focus();
    }
    function closeConfirm() {
        confirmBox.classList.remove('show');
        setTimeout(function () { confirmBox.hidden = true; }, 150);
        onYes = null;
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }
    $('confirm-no').addEventListener('click', closeConfirm);
    confirmBox.addEventListener('click', function (e) { if (e.target === confirmBox) { closeConfirm(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !confirmBox.hidden) { closeConfirm(); } });
    $('confirm-yes').addEventListener('click', function () {
        var cb = onYes;
        closeConfirm();
        if (cb) { cb(); }
    });

    // ---------- Jingles / Audio abspielen (mit Rückfrage)
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-play]');
        if (!b) { return; }
        askConfirm((b.dataset.kind === 'audio' ? 'Audio-Datei' : 'Jingle') + ' abspielen?', b.dataset.title, b.dataset.sub, '▶ Abspielen', function () {
            post('/abspielen', { kind: 'audio', audio_file_id: b.dataset.play })
                .then(function (s) { afterRequest(s, '„' + b.dataset.title + '“ wird gespielt.'); })
                .catch(function (err) { toast(err.message, true); });
        });
    });

    // ---------- Geplante Einspieler
    $('sched').addEventListener('click', function (e) {
        var b = e.target.closest('[data-unschedule]');
        if (!b) { return; }
        askConfirm('Geplanten Einspieler abbrechen?', b.dataset.label, '', 'Ja, abbrechen', function () {
            post('/planung/' + encodeURIComponent(b.dataset.unschedule) + '/loeschen').then(function (s) { render(s); toast('Einspieler abgebrochen.'); }).catch(function (err) { toast(err.message, true); });
        });
    });
    var schedForm = $('sched-form');
    if (schedForm) {
        var schedUpload = $('sched-upload');
        if (schedUpload) {
            schedUpload.addEventListener('change', function () { if (this.files.length) { $('sched-file').value = ''; } });
            $('sched-file').addEventListener('change', function () { if (this.value) { schedUpload.value = ''; } });
        }
        schedForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var hasFile = schedUpload && schedUpload.files.length;
            if (!$('sched-file').value && !hasFile) { toast(schedUpload ? 'Bitte einen Jingle bzw. eine Audio-Datei wählen oder eine Datei hochladen.' : 'Bitte einen Jingle bzw. eine Audio-Datei wählen.', true); return; }
            var btn = $('sched-send');
            btn.disabled = true; btn.textContent = hasFile ? 'Wird hochgeladen …' : 'Wird geplant …';
            post('/planen', new FormData(schedForm)).then(function (s) {
                render(s); toast(s.count > 1 ? s.count + ' Zeitpunkte eingeplant.' : 'Einspieler eingeplant.');
                schedForm.reset();
                timesBox.querySelectorAll('.time-row').forEach(function (r, i) { if (i > 0) { r.remove(); } });
                timesBox.querySelector('.time-rm').hidden = true;
            }).catch(function (err) { toast(err.message, true); }).then(function () { btn.disabled = false; btn.textContent = 'Einplanen'; });
        });
    }

    // ---------- Erneut einspielen (Verlauf und geplante Einspieler)
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-replay]');
        if (!b) { return; }
        askConfirm('Erneut einspielen?', b.dataset.label, '', '▶ Erneut abspielen', function () {
            post(b.dataset.replay).then(function (s) { afterRequest(s, '„' + b.dataset.label + '“ wird erneut gespielt.'); }).catch(function (err) { toast(err.message, true); });
        });
    });

    // ---------- Mehrere Zeitpunkte
    var timesBox = $('sched-times');
    if (timesBox) {
        var syncTimes = function () {
            var rows = timesBox.querySelectorAll('.time-row');
            rows.forEach(function (r) { r.querySelector('.time-rm').hidden = rows.length < 2; });
        };
        $('sched-add-time').addEventListener('click', function () {
            var rows = timesBox.querySelectorAll('.time-row');
            var last = rows[rows.length - 1], clone = last.cloneNode(true), input = clone.querySelector('input');
            var v = last.querySelector('input').value;
            if (v) { var d = new Date(v); d.setHours(d.getHours() + 1); var p2 = function (n) { return ('0' + n).slice(-2); }; input.value = d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate()) + 'T' + p2(d.getHours()) + ':' + p2(d.getMinutes()); }
            timesBox.appendChild(clone); syncTimes(); input.focus();
        });
        timesBox.addEventListener('click', function (e) { var rm = e.target.closest('.time-rm'); if (rm) { rm.closest('.time-row').remove(); syncTimes(); } });
    }

    // ---------- Eigene Sounddatei hochladen
    var uploadForm = $('upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = $('upload-send');
            btn.disabled = true; btn.textContent = 'Wird hochgeladen …';
            post('/hochladen', new FormData(uploadForm)).then(function () {
                toast('Datei hochgeladen.');
                setTimeout(function () { location.reload(); }, 600);
            }).catch(function (err) { toast(err.message, true); btn.disabled = false; btn.textContent = 'Hochladen'; });
        });
    }

    // ---------- Stopp
    $('stop-btn').addEventListener('click', function () {
        if (window.speechSynthesis) { speechSynthesis.cancel(); }
        post('/abspielen', { kind: 'stop' }).then(function (s) { afterRequest(s, 'Stopp gesendet.'); }).catch(function (err) { toast(err.message, true); });
    });

    // ---------- Durchsage: Modus
    var mode = 'text';
    document.querySelectorAll('.seg [data-mode]').forEach(function (b) {
        b.addEventListener('click', function () {
            mode = b.dataset.mode;
            document.querySelectorAll('.seg [data-mode]').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
            $('mode-text').hidden = mode !== 'text';
            $('mode-mic').hidden = mode !== 'mic';
            updateSend();
        });
    });

    var ttsText = $('tts-text');
    // zuletzt gewählte Sprache merken (pro Browser)
    try { var savedLang = localStorage.getItem('ttsLang'); if (savedLang && $('tts-lang') && $('tts-lang').querySelector('option[value="' + savedLang + '"]')) { $('tts-lang').value = savedLang; } } catch (e3) {}
    ttsText.addEventListener('input', function () { $('tts-count').textContent = ttsText.value.length; updateSend(); });
    $('tts-preview').addEventListener('click', function () {
        if (!window.speechSynthesis) { toast('Dein Browser kann nicht vorlesen – auf der Veranstaltung spricht das Abspielgerät.', true); return; }
        speechSynthesis.cancel();
        var u = new SpeechSynthesisUtterance(ttsText.value.trim() || 'Bitte zuerst einen Text eingeben.');
        u.lang = $('tts-lang') ? $('tts-lang').value : TTS_LANG;
        speechSynthesis.speak(u);
    });

    // ---------- Durchsage: Mikrofon
    var recorder = null, chunks = [], recBlob = null, recStart = 0, recTick = null, recStopTimer = null, stream = null;
    var micSupported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
    if (!micSupported) {
        $('rec-btn').disabled = true;
        $('rec-hint').textContent = window.isSecureContext === false
            ? 'Mikrofon-Aufnahmen gehen nur über eine sichere Verbindung (https).'
            : 'Dieser Browser unterstützt keine Mikrofon-Aufnahme.';
    }
    function pickMime() {
        var types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg'];
        for (var i = 0; i < types.length; i++) { if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(types[i])) { return types[i]; } }
        return '';
    }
    function fmt(ms) { var s = Math.floor(ms / 1000); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
    function stopRecording() {
        if (recorder && recorder.state === 'recording') { recorder.stop(); }
    }
    $('rec-btn').addEventListener('click', function () {
        if (recorder && recorder.state === 'recording') { stopRecording(); return; }
        navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } }).then(function (s) {
            stream = s;
            chunks = []; recBlob = null; $('rec-audio').hidden = true; updateSend();
            var mime = pickMime();
            recorder = mime ? new MediaRecorder(s, { mimeType: mime }) : new MediaRecorder(s);
            recorder.ondataavailable = function (ev) { if (ev.data && ev.data.size) { chunks.push(ev.data); } };
            recorder.onstop = function () {
                clearInterval(recTick); clearTimeout(recStopTimer);
                stream.getTracks().forEach(function (t) { t.stop(); });
                $('rec-btn').classList.remove('recording'); $('rec-btn').textContent = 'NEU'; $('rec-btn').setAttribute('aria-label', 'Neu aufnehmen');
                recBlob = new Blob(chunks, { type: (recorder.mimeType || 'audio/webm').split(';')[0] });
                var a = $('rec-audio'); a.src = URL.createObjectURL(recBlob); a.hidden = false;
                $('rec-hint').textContent = 'Anhören und dann „Durchsage abspielen“ – oder neu aufnehmen.';
                updateSend();
            };
            recorder.start(250);
            recStart = Date.now();
            $('rec-btn').classList.add('recording'); $('rec-btn').textContent = 'STOPP'; $('rec-btn').setAttribute('aria-label', 'Aufnahme beenden');
            $('rec-hint').textContent = 'Aufnahme läuft …';
            recTick = setInterval(function () { $('rec-timer').textContent = fmt(Date.now() - recStart); }, 200);
            recStopTimer = setTimeout(stopRecording, MAX_REC_MS);
        }).catch(function () { toast('Kein Zugriff auf das Mikrofon. Bitte in den Browser-Einstellungen erlauben.', true); });
    });

    // ---------- Durchsage senden
    function updateSend() {
        $('ann-send').disabled = mode === 'text' ? ttsText.value.trim() === '' : !recBlob;
    }
    updateSend();
    $('ann-send').addEventListener('click', function () {
        var btn = $('ann-send');
        var gong = $('with-gong').checked ? '1' : '0';
        var fd = new FormData();
        fd.append('with_gong', gong);
        if (mode === 'text') {
            fd.append('kind', 'tts'); fd.append('text', ttsText.value.trim());
            if ($('tts-lang')) { fd.append('language', $('tts-lang').value); try { localStorage.setItem('ttsLang', $('tts-lang').value); } catch (e2) {} }
        } else {
            if (!recBlob) { return; }
            var ext = recBlob.type.indexOf('mp4') >= 0 ? 'm4a' : (recBlob.type.indexOf('ogg') >= 0 ? 'ogg' : 'webm');
            fd.append('kind', 'recording'); fd.append('recording', recBlob, 'durchsage.' + ext);
        }
        btn.disabled = true; btn.textContent = 'Wird gesendet …';
        post('/abspielen', fd).then(function (s) {
            afterRequest(s, 'Durchsage wird abgespielt.');
            if (mode === 'text') { ttsText.value = ''; $('tts-count').textContent = '0'; }
            else { recBlob = null; $('rec-audio').hidden = true; $('rec-timer').textContent = '0:00'; $('rec-btn').textContent = 'AUFNEHMEN'; $('rec-hint').textContent = 'Tippen zum Starten, nochmal tippen zum Beenden (max. 2 Minuten).'; }
        }).catch(function (err) { toast(err.message, true); }).then(function () {
            btn.textContent = 'Durchsage abspielen'; updateSend();
        });
    });
})();
</script>
</body>
</html>
