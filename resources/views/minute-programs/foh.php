<?php
/**
 * Ablauf am FOH (Migration 158) – eigenständige Live-Seite eines
 * Minutenprogramms für die Regie, analog zur Live-Oberfläche
 * „Audio & Durchsagen“ (event-audio/live.php). Alle Inhalte werden
 * clientseitig aus dem Run-Sheet-JSON (RunSheetPayload) gerendert, damit
 * Seite und Stagedisplay-API dieselben Daten zeigen.
 *
 * @var \App\Application\MinuteProgram\Query\MinuteProgramDetail $detail
 * @var string $rentalName
 * @var string $basePath   z. B. /minutenprogramme/{id}/foh
 * @var bool $canControl
 * @var string $actorName
 * @var string $backUrl
 * @var list<array{id: string, name: string, room: ?string, startAt: \DateTimeImmutable}> $otherPrograms
 * @var list<array{url: string, mode: string, canEdit: bool}> $apiUrls
 * @var array<string, mixed> $initialPayload
 * @var string $csrfToken
 */

use App\Infrastructure\Http\CompanyBranding;

use function App\Infrastructure\Http\e;

$program = $detail->program;
$logo = CompanyBranding::darkLogoUrl() ?? CompanyBranding::logoUrl();
$isUserRoute = str_starts_with($basePath, '/minutenprogramme/');
$fullscreenUrl = $isUserRoute ? '/minutenprogramme/' . $program->id . '/vollbild' : substr($basePath, 0, -4) . '/vollbild';
$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<meta name="robots" content="noindex">
<title>FOH – <?= e($program->name) ?></title>
<style>
    :root {
        --bg: #0b1020; --panel: #121a2e; --panel-2: #18223b; --line: #24304d; --text: #e8ecf6; --muted: #8b97b5;
        --accent: #f5b43c; --accent-ink: #1a1203; --go: #22c55e; --stop: #ef4444; --info: #60a5fa; --warn: #f59e0b;
        --radius: 14px;
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); }
    body { min-height: 100vh; padding-bottom: calc(2rem + env(safe-area-inset-bottom)); -webkit-tap-highlight-color: transparent; }
    button, input, select { font: inherit; color: inherit; }
    button { cursor: pointer; }
    a { color: inherit; }
    .num { font-variant-numeric: tabular-nums; }
    .wrap { max-width: 1480px; margin: 0 auto; padding: 0 16px; }

    header.top { position: sticky; top: 0; z-index: 20; background: rgba(11,16,32,.94); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
    .top-inner { display: flex; align-items: center; gap: 14px; padding-top: 10px; padding-bottom: 10px; }
    .top-inner img { max-height: 36px; max-width: 120px; object-fit: contain; }
    .event { min-width: 0; flex: 1; }
    .event h1 { margin: 0; font-size: 1.05rem; font-weight: 700; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .event p { margin: 2px 0 0; font-size: .75rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .event select { background: var(--panel-2); border: 1px solid var(--line); border-radius: 8px; padding: 2px 6px; font-size: .75rem; color: var(--text); max-width: 100%; }
    .clock { font-size: 1.9rem; font-weight: 800; letter-spacing: .02em; }
    .who { font-size: .72rem; color: var(--muted); text-align: right; line-height: 1.5; }
    .who a { color: var(--muted); }
    @media (max-width: 640px) { .top-inner img, .who { display: none; } .clock { font-size: 1.4rem; } }

    .strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 14px; }
    .stat { background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); padding: 12px 14px; min-width: 0; }
    .stat .l { font-size: .68rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .stat .v { font-size: 1.7rem; font-weight: 800; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .stat .s { font-size: .78rem; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .stat.delay.late { border-color: rgba(239,68,68,.6); background: linear-gradient(180deg, rgba(239,68,68,.16), var(--panel)); }
    .stat.delay.slight { border-color: rgba(245,158,11,.6); background: linear-gradient(180deg, rgba(245,158,11,.14), var(--panel)); }
    .stat.delay.ok { border-color: rgba(34,197,94,.5); background: linear-gradient(180deg, rgba(34,197,94,.12), var(--panel)); }
    .late-t { color: #fca5a5; } .slight-t { color: #fcd34d; } .ok-t { color: #86efac; }

    .cols { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; }
    .cols > div { min-width: 0; }
    @media (min-width: 1100px) { .cols { grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); align-items: start; } .side { position: sticky; top: 76px; max-height: calc(100vh - 92px); overflow: auto; } }

    section.card { margin-top: 16px; background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); padding: 16px; }
    section.card h2 { margin: 0 0 12px; font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 700; display: flex; align-items: center; gap: 8px; }
    section.card h2 .sp { flex: 1; }

    .now { border-left: 5px solid var(--accent); }
    .now.over { border-left-color: var(--stop); }
    .next { border-left: 5px solid var(--info); }
    .item-title { font-size: 1.6rem; font-weight: 800; line-height: 1.15; margin: 0; overflow-wrap: anywhere; }
    .item-desc { color: var(--muted); margin: 6px 0 0; white-space: pre-wrap; overflow-wrap: anywhere; }
    .timer-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 6px 22px; margin-top: 14px; }
    .big { font-size: 3.4rem; font-weight: 800; line-height: 1; }
    @media (max-width: 640px) { .big { font-size: 2.6rem; } .item-title { font-size: 1.3rem; } }
    .meta { font-size: .82rem; color: var(--muted); line-height: 1.5; }
    .meta b { color: var(--text); font-weight: 600; }
    .bar { height: 8px; background: var(--panel-2); border-radius: 99px; overflow: hidden; margin-top: 14px; }
    .bar i { display: block; height: 100%; width: 0; background: var(--accent); transition: width .3s linear; }
    .bar.over i { background: var(--stop); }
    .empty { color: var(--muted); font-size: .95rem; }

    /* Notes: one card per trade (migration 171), colored by trade */
    .notes { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; margin-top: 14px; }
    .tcard { --c: #94a3b8; background: var(--panel-2); background: color-mix(in srgb, var(--c) 14%, var(--panel-2)); border: 1px solid var(--line); border-color: color-mix(in srgb, var(--c) 45%, var(--line)); border-top: 4px solid var(--c); border-radius: 12px; padding: 10px 12px; min-width: 0; }
    .tcard h3 { margin: 0 0 6px; font-size: .74rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--c); color: color-mix(in srgb, var(--c) 70%, #fff); display: flex; align-items: center; gap: 6px; }
    .tcard h3::before { content: ''; width: 8px; height: 8px; border-radius: 99px; background: var(--c); flex: none; }
    .tcard .t { font-size: 1rem; line-height: 1.4; white-space: pre-wrap; overflow-wrap: anywhere; }
    .tcard.general { --c: #cbd5e1; }

    .btn { border: 1px solid var(--line); background: var(--panel-2); border-radius: 10px; padding: 10px 14px; font-weight: 700; font-size: .9rem; }
    .btn:disabled { opacity: .45; cursor: default; }
    .btn.primary { background: var(--accent); color: var(--accent-ink); border-color: var(--accent); }
    .btn.go { background: var(--go); color: #052e14; border-color: var(--go); }
    .btn.danger { border-color: rgba(239,68,68,.6); color: #fca5a5; }
    .btn.sm { padding: 6px 10px; font-size: .8rem; border-radius: 8px; }
    .start-next { width: 100%; margin-top: 16px; padding: 18px; font-size: 1.15rem; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .start-next span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .row + .row { margin-top: 10px; }
    .input { background: var(--bg); border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; font-size: 1rem; color: var(--text); width: 9rem; }
    .input:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
    .hint { font-size: .75rem; color: var(--muted); }

    .chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .chip { border: 1px solid var(--line); background: var(--panel-2); color: var(--muted); border-radius: 999px; padding: 5px 11px; font-size: .78rem; font-weight: 700; }
    .chip[aria-pressed="true"] { background: var(--text); color: var(--bg); border-color: var(--text); }
    .chip[data-color] { border-left: 4px solid var(--c); }
    .chip[data-color][aria-pressed="true"] { background: var(--c); border-color: var(--c); color: #0b1020; }

    .list { display: grid; gap: 8px; }
    .it { display: grid; grid-template-columns: 76px minmax(0, 1fr) auto; gap: 4px 12px; background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; }
    .it.done { opacity: .5; }
    .it.current { border-color: var(--accent); box-shadow: inset 4px 0 0 var(--accent); }
    .it.next { border-color: rgba(96,165,250,.7); box-shadow: inset 4px 0 0 var(--info); }
    .it .tm { font-weight: 800; font-size: 1.05rem; }
    .it .tm s { display: block; font-weight: 500; font-size: .72rem; color: var(--muted); }
    .it .tm small { display: block; font-weight: 500; font-size: .72rem; color: var(--muted); }
    .it .nm { font-weight: 700; overflow-wrap: anywhere; }
    .it .nm .pos { color: var(--muted); font-weight: 600; margin-right: 4px; }
    .it .badge { display: inline-block; font-size: .62rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; border-radius: 6px; padding: 2px 6px; margin-left: 6px; vertical-align: 2px; }
    .badge.now { background: var(--accent); color: var(--accent-ink); border: 0; } .badge.nx { background: var(--info); color: #0b1020; }
    .badge.nostrobe { background: var(--stop); color: #fff; }
    .nostrobe-banner { display: inline-block; margin-top: 10px; background: var(--stop); color: #fff; font-weight: 800; font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; border-radius: 8px; padding: 5px 10px; }
    .it .ds { font-size: .82rem; color: var(--muted); white-space: pre-wrap; overflow-wrap: anywhere; }
    .it .acts { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; }
    .it .notes { grid-column: 2 / 4; margin-top: 6px; gap: 6px; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
    .it .tcard { padding: 6px 9px; border-top-width: 3px; border-radius: 9px; }
    .it .tcard h3 { font-size: .64rem; margin-bottom: 3px; }
    .it .tcard .t { font-size: .84rem; }
    .it .tform { grid-column: 1 / 4; display: none; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 6px; }
    .it .tform.open { display: flex; }
    @media (max-width: 520px) { .it { grid-template-columns: 62px 1fr; } .it .acts { grid-column: 1 / 3; flex-direction: row; justify-content: flex-end; } .it .notes { grid-column: 1 / 3; } }

    .kv { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; font-size: .88rem; }
    .kv dt { color: var(--muted); } .kv dd { margin: 0; overflow-wrap: anywhere; }
    .api { display: flex; gap: 6px; align-items: center; margin-top: 6px; }
    .api code { flex: 1; min-width: 0; font-size: .75rem; background: var(--bg); border: 1px solid var(--line); border-radius: 8px; padding: 6px 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .conn { display: inline-flex; align-items: center; gap: 6px; }
    .led { width: 9px; height: 9px; border-radius: 99px; background: var(--go); }
    .led.off { background: var(--stop); }

    /* Migration 167: messages from the organizer */
    .msgs { display: grid; gap: 10px; margin-top: 14px; }
    .msgs:empty { display: none; }
    .msg { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 6px 14px; align-items: center; background: linear-gradient(180deg, rgba(96,165,250,.16), var(--panel)); border: 1px solid rgba(96,165,250,.6); border-left: 6px solid var(--info); border-radius: var(--radius); padding: 12px 14px; }
    .msg.urgent { background: linear-gradient(180deg, rgba(239,68,68,.22), var(--panel)); border-color: rgba(239,68,68,.7); border-left-color: var(--stop); }
    .msg.seen { background: var(--panel); border-color: var(--line); border-left-color: var(--muted); }
    .msg.fresh { animation: msgflash 1s ease-in-out 4; }
    @keyframes msgflash { 50% { box-shadow: 0 0 0 6px rgba(96,165,250,.45); } }
    .msg.urgent.fresh { animation-name: msgflash-u; }
    @keyframes msgflash-u { 50% { box-shadow: 0 0 0 6px rgba(239,68,68,.5); } }
    .msg .tx { font-size: 1.25rem; font-weight: 700; line-height: 1.3; white-space: pre-wrap; overflow-wrap: anywhere; }
    .msg .mt { font-size: .75rem; color: var(--muted); margin-top: 4px; }
    .msg .mt .tag { display: inline-block; font-size: .62rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; border: 1px solid var(--line); border-radius: 6px; padding: 1px 6px; margin-right: 4px; color: var(--text); }
    .msg .ma { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    @media (max-width: 520px) { .msg { grid-template-columns: 1fr; } .msg .ma { justify-content: flex-start; } }

    .toast { position: fixed; left: 50%; bottom: calc(1.5rem + env(safe-area-inset-bottom)); transform: translateX(-50%) translateY(20px); opacity: 0; z-index: 40; max-width: calc(100% - 32px); background: var(--text); color: var(--bg); padding: 10px 16px; border-radius: 10px; font-size: .9rem; font-weight: 600; transition: all .2s; pointer-events: none; }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast.err { background: #fecaca; color: #7f1d1d; }
</style>
</head>
<body>
<header class="top">
    <div class="wrap top-inner">
        <?php if ($logo !== null): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <div class="event">
            <h1><?= e($program->name) ?><?= $program->room !== null ? ' · ' . e($program->room) : '' ?></h1>
            <p>
                <?php if ($otherPrograms !== []): ?>
                    <select aria-label="Minutenprogramm wechseln" onchange="if (this.value) location.href = '/minutenprogramme/' + this.value + '/foh'">
                        <?php foreach ($otherPrograms as $other): ?>
                            <option value="<?= e($other['id']) ?>" <?= $other['id'] === $program->id ? 'selected' : '' ?>><?= e($other['startAt']->format('d.m. H:i') . ' · ' . $other['name'] . ($other['room'] !== null ? ' (' . $other['room'] . ')' : '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?= e($rentalName) ?> · Ablauf am FOH
            </p>
        </div>
        <div class="clock num" id="clock">--:--:--</div>
        <div class="who">
            <span class="conn"><span class="led" id="led"></span><span id="conn">verbunden</span></span><br>
            <?php if ($actorName !== ''): ?><?= e($actorName) ?> · <?php endif; ?><a href="<?= e($backUrl) ?>">zurück</a>
        </div>
    </div>
</header>

<main class="wrap">
    <div class="msgs" id="msgs" aria-live="assertive"></div>
    <div class="strip">
        <div class="stat delay" id="delay-stat">
            <div class="l">Verzug</div>
            <div class="v num" id="delay-v">±0:00</div>
            <div class="s" id="delay-s">im Plan</div>
        </div>
        <div class="stat">
            <div class="l">Status</div>
            <div class="v" id="state-v">–</div>
            <div class="s num" id="state-s"></div>
        </div>
        <div class="stat">
            <div class="l">Programmende</div>
            <div class="v num" id="end-v">--:--</div>
            <div class="s num" id="end-s"></div>
        </div>
    </div>

    <div class="cols">
        <div>
            <section class="card now" id="now-card">
                <h2>Jetzt <span class="sp"></span><span id="now-pos"></span></h2>
                <div id="now-body"></div>
            </section>

            <section class="card next" id="next-card">
                <h2>Als Nächstes <span class="sp"></span><span id="next-pos"></span></h2>
                <div id="next-body"></div>
            </section>

            <?php if ($canControl): ?>
            <section class="card">
                <h2>Verzug einstellen</h2>
                <form class="row" id="delay-form">
                    <input class="input num" id="delay-input" name="delay" inputmode="text" autocomplete="off" placeholder="z. B. 5, -2, 4:30" aria-label="Verzug (Minuten oder mm:ss)">
                    <button class="btn primary" type="submit">Verzug setzen</button>
                    <span class="hint">Minuten (5, -2) oder mm:ss (4:30). Negativ = Vorsprung.</span>
                </form>
                <div class="row">
                    <button class="btn sm" type="button" data-adjust="-300">−5 min</button>
                    <button class="btn sm" type="button" data-adjust="-60">−1 min</button>
                    <button class="btn sm" type="button" data-adjust="-15">−15 s</button>
                    <button class="btn sm" type="button" data-adjust="15">+15 s</button>
                    <button class="btn sm" type="button" data-adjust="60">+1 min</button>
                    <button class="btn sm" type="button" data-adjust="300">+5 min</button>
                    <button class="btn sm" type="button" id="zero-btn">Auf 0</button>
                    <button class="btn sm primary" type="button" id="adopt-btn" hidden>Prognose übernehmen</button>
                </div>
                <div class="row">
                    <button class="btn sm" type="button" id="finish-btn">Programm beenden</button>
                    <button class="btn sm danger" type="button" id="reset-btn">Live-Stand zurücksetzen</button>
                </div>
            </section>
            <?php endif; ?>

            <section class="card">
                <h2>Programm</h2>
                <dl class="kv" id="info"></dl>
                <div class="row" style="margin-top: 12px;">
                    <a class="btn sm" href="<?= e($fullscreenUrl) ?>" target="_blank" rel="noopener">Vollbild-Countdown</a>
                </div>
                <?php if ($apiUrls !== []): ?>
                    <p class="hint" style="margin: 14px 0 0;">Stagedisplay-API (JSON, siehe Dokumentation „stagedisplay-api.md“):</p>
                    <?php foreach ($apiUrls as $api): ?>
                        <div class="api">
                            <code title="<?= e($api['mode']) ?>"><?= e($origin . $api['url']) ?></code>
                            <span class="hint"><?= e($api['canEdit'] ? 'steuern' : 'lesen') ?></span>
                            <button class="btn sm" type="button" data-copy="<?= e($origin . $api['url']) ?>">Kopieren</button>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($isUserRoute): ?>
                    <p class="hint" style="margin: 14px 0 0;">Für ein Stagedisplay zuerst beim Minutenprogramm einen Freigabe-Link anlegen – dessen Token ist der API-Zugang.</p>
                <?php endif; ?>
            </section>
        </div>

        <div class="side">
            <section class="card">
                <h2>Ablauf <span class="sp"></span><span id="count"></span></h2>
                <div class="chips" id="dept" role="group" aria-label="Hinweise anzeigen"></div>
                <div class="list" id="list" style="margin-top: 12px;"></div>
            </section>
        </div>
    </div>
</main>

<div class="toast" id="toast" role="status"></div>

<script>
(function () {
    'use strict';
    var BASE = <?= json_encode($basePath, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) ?>;
    var CSRF = <?= json_encode($csrfToken, JSON_THROW_ON_ERROR) ?>;
    var CAN_CONTROL = <?= $canControl ? 'true' : 'false' ?>;
    var TITLE = document.title;
    var state = <?= json_encode($initialPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var offset = 0; // server clock − local clock (ms)
    var listKey = '';
    var busy = false;
    var openTimeForm = null;
    var $ = function (id) { return document.getElementById(id); };

    // ---------------------------------------------------------------- trade filter (per viewer, migration 171)
    // Keys: "general" or a trade ID. Trades not stored yet are shown.
    var shown = {};
    try { var saved = JSON.parse(localStorage.getItem('foh-trades') || 'null'); if (saved && typeof saved === 'object') { shown = saved; } } catch (e) {}
    function isShown(key) { return shown[key] !== false; }
    var chipsKey = null;
    function renderChips() {
        var trades = state.trades || [];
        var key = JSON.stringify(trades);
        if (key !== chipsKey) {
            chipsKey = key;
            $('dept').innerHTML = '<button class="chip" type="button" data-dept="general">Allgemein</button>'
                + trades.map(function (tr) {
                    return '<button class="chip" type="button" data-dept="' + esc(tr.id) + '" data-color style="--c: ' + esc(tr.color) + '">' + esc(tr.name) + '</button>';
                }).join('');
        }
        document.querySelectorAll('#dept .chip').forEach(function (c) { c.setAttribute('aria-pressed', isShown(c.dataset.dept) ? 'true' : 'false'); });
    }
    $('dept').addEventListener('click', function (ev) {
        var c = ev.target.closest('.chip'); if (!c) { return; }
        shown[c.dataset.dept] = !isShown(c.dataset.dept);
        try { localStorage.setItem('foh-trades', JSON.stringify(shown)); } catch (e) {}
        renderChips(); listKey = ''; nowKey = ''; nextKey = ''; render();
    });

    // ---------------------------------------------------------------- helpers
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function now() { return Date.now() + offset; }
    function ts(iso) { return iso ? new Date(iso).getTime() : null; }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function hm(iso) { if (!iso) { return '--:--'; } var d = new Date(iso); return pad(d.getHours()) + ':' + pad(d.getMinutes()); }
    function hms(ms) { var d = new Date(ms); return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()); }
    function dur(sec) {
        sec = Math.round(Math.abs(sec));
        var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
        return h > 0 ? h + ':' + pad(m) + ':' + pad(s) : m + ':' + pad(s);
    }
    function signed(sec) { return (sec > 0 ? '+' : sec < 0 ? '−' : '±') + dur(sec); }
    // Migration 166: no strobe during an item or at the whole event.
    function noStrobe(item) { return !!(item && item.noStrobe) || !!(state.event && state.event.noStrobe); }
    function noStrobeBanner(item) { return noStrobe(item) ? '<div class="nostrobe-banner">Kein Strobo</div>' : ''; }
    function itemById(id) { for (var i = 0; i < state.items.length; i++) { if (state.items[i].id === id) { return state.items[i]; } } return null; }

    // General note plus one colored card per trade note (migration 171).
    function notesHtml(item) {
        var html = '';
        if (item && item.notes && item.notes.general && isShown('general')) {
            html += '<div class="tcard general"><h3>Allgemein</h3><div class="t">' + esc(item.notes.general) + '</div></div>';
        }
        ((item && item.tradeNotes) || []).forEach(function (n) {
            if (isShown(n.tradeId)) {
                html += '<div class="tcard" style="--c: ' + esc(n.color) + '"><h3>' + esc(n.trade) + '</h3><div class="t">' + esc(n.text) + '</div></div>';
            }
        });
        return html ? '<div class="notes">' + html + '</div>' : '';
    }

    // Live overrun while an item is held as current (tracking mode).
    function liveOverrun() {
        var live = state.live;
        if (!live.tracking || !state.current) { return 0; }
        var end;
        if (state.next) {
            var nx = itemById(state.next.id); end = nx ? ts(nx.currentStart) : null;
        } else {
            var cur = itemById(state.current.id);
            end = cur && cur.durationMinutes != null ? ts(cur.currentStart) + cur.durationMinutes * 60000 : null;
        }
        return end === null ? 0 : Math.max(0, Math.floor((now() - end) / 1000));
    }

    // ---------------------------------------------------------------- rendering
    function render() {
        var t = now();
        $('clock').textContent = hms(t);
        var live = state.live;
        var overrun = liveOverrun();
        var projected = live.delaySeconds + overrun;

        // Delay
        var stat = $('delay-stat');
        stat.classList.remove('late', 'slight', 'ok');
        stat.classList.add(projected > 120 ? 'late' : projected > 0 ? 'slight' : 'ok');
        $('delay-v').textContent = signed(projected);
        $('delay-s').textContent = overrun > 0
            ? 'davon ' + dur(overrun) + ' laufend überzogen (gesetzt ' + signed(live.delaySeconds) + ')'
            : (live.delaySeconds > 0 ? 'hinter dem Plan' : live.delaySeconds < 0 ? 'vor dem Plan' : 'im Plan');
        var adopt = $('adopt-btn'); if (adopt) { adopt.hidden = overrun <= 0; }

        // State
        if (live.state === 'finished') {
            $('state-v').textContent = 'Beendet';
            $('state-s').textContent = live.finishedAt ? 'um ' + hm(live.finishedAt) : '';
        } else if (live.state === 'running') {
            $('state-v').textContent = live.tracking ? 'Läuft (FOH)' : 'Läuft (Uhr)';
            $('state-s').textContent = state.current ? 'seit ' + dur((t - ts(state.current.startsAt)) / 1000) + ' · ' + state.current.name : '';
        } else {
            var start = state.next ? ts(state.next.startsAt) : ts(state.program.currentStart);
            $('state-v').textContent = 'Vor Beginn';
            $('state-s').textContent = start ? (start > t ? 'Beginn in ' + dur((start - t) / 1000) : 'Beginn überfällig ' + dur((t - start) / 1000)) : '';
        }

        // End
        var endMs = ts(state.program.projectedEnd);
        if (endMs !== null && live.state === 'running') {
            endMs = Math.max(endMs + (overrun - live.overrunSeconds) * 1000, t);
        }
        $('end-v').textContent = live.state === 'finished' && live.finishedAt ? hm(live.finishedAt) : (endMs ? hms(endMs).slice(0, 5) : '--:--');
        $('end-s').textContent = 'geplant ' + hm(state.program.plannedEnd);

        renderChips();
        renderNow(t);
        renderNext(t, overrun);
        renderList();
        renderMessages();
    }

    // ---------------------------------------------------------------- messages from the organizer (migration 167)
    var msgKey = null, knownMsgs = null;
    function beep() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext; if (!Ctx) { return; }
            var ctx = new Ctx(), o = ctx.createOscillator(), g = ctx.createGain();
            o.frequency.value = 880; g.gain.setValueAtTime(0.0001, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.5);
            o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + 0.55);
            o.onended = function () { ctx.close(); };
        } catch (e) {}
    }
    function renderMessages() {
        var msgs = state.messages || [];
        var key = JSON.stringify(msgs.map(function (m) { return [m.id, m.acknowledgedAt]; }));
        if (key === msgKey) { return; }
        msgKey = key;
        var fresh = {}, hasFresh = false;
        msgs.forEach(function (m) { if (knownMsgs && !knownMsgs[m.id]) { fresh[m.id] = true; hasFresh = true; } });
        knownMsgs = {}; msgs.forEach(function (m) { knownMsgs[m.id] = true; });
        $('msgs').innerHTML = msgs.slice().reverse().map(function (m) {
            var tags = (m.stage ? '<span class="tag">Stagedisplay</span>' : '') + (m.foh ? '<span class="tag">FOH</span>' : '');
            var meta = hm(m.sentAt) + ' · ' + esc(m.sentBy) + (m.acknowledgedAt ? ' · gesehen ' + hm(m.acknowledgedAt) + (m.acknowledgedBy ? ' (' + esc(m.acknowledgedBy) + ')' : '') : '');
            var acts = CAN_CONTROL
                ? '<div class="ma">' + (m.acknowledgedAt ? '' : '<button class="btn sm primary" type="button" data-msg-ack="' + esc(m.id) + '">✓ Gesehen</button>')
                  + '<button class="btn sm" type="button" data-msg-close="' + esc(m.id) + '" title="' + (m.stage ? 'Auch vom Stagedisplay entfernen' : 'Ausblenden') + '">Ausblenden</button></div>'
                : '<div></div>';
            return '<div class="msg' + (m.urgent ? ' urgent' : '') + (m.acknowledgedAt ? ' seen' : '') + (fresh[m.id] ? ' fresh' : '') + '">'
                + '<div><div class="tx">' + (m.urgent ? '⚠ ' : '✉ ') + esc(m.text) + '</div><div class="mt">' + tags + meta + '</div></div>' + acts + '</div>';
        }).join('');
        var open = msgs.filter(function (m) { return !m.acknowledgedAt; }).length;
        document.title = (open ? '(' + open + ') ' : '') + TITLE;
        if (hasFresh) { beep(); }
    }

    // Card structure is rebuilt only when its content changes; timers are
    // updated in place (rebuilding would swallow clicks on the Start button).
    var nowKey = '', nextKey = '';
    function setText(id, text, cls) { var el = $(id); if (el) { el.textContent = text; if (cls !== undefined) { el.className = cls; } } }

    function renderNow(t) {
        var c = state.current, body = $('now-body'), card = $('now-card');
        var key = (c ? c.id + '|' + c.startsAt + '|' + c.endsAt : 'none|' + state.live.state) + '|' + state.revision + '|' + JSON.stringify(shown);
        if (key !== nowKey) {
            nowKey = key;
            if (!c) {
                $('now-pos').textContent = '';
                body.innerHTML = '<p class="empty">' + (state.live.state === 'finished' ? 'Programm beendet.' : 'Noch kein Programmpunkt gestartet.') + '</p>';
            } else {
                var it = itemById(c.id);
                var hasEnd = c.endsAt !== null;
                $('now-pos').textContent = '#' + c.position + ' / ' + state.items.length;
                body.innerHTML = '<p class="item-title">' + esc(c.name) + '</p>'
                    + (c.description ? '<p class="item-desc">' + esc(c.description) + '</p>' : '')
                    + noStrobeBanner(c)
                    + '<div class="timer-row"><div><div id="now-big" class="big num"></div><div class="meta" id="now-big-l"></div></div>'
                    + (hasEnd ? '<div class="meta">Läuft seit <b class="num" id="now-el"></b><br>Dauer <b>' + dur(c.durationSeconds) + '</b></div>' : '')
                    + '<div class="meta">' + (c.actualStart ? 'gestartet <b class="num">' + hms(ts(c.actualStart)) + '</b>' : 'Beginn lt. Uhr <b class="num">' + hm(c.startsAt) + '</b>')
                    + (it ? '<br>geplant <b class="num">' + hm(it.plannedStart) + '</b>' : '') + '</div></div>'
                    + (hasEnd ? '<div class="bar" id="now-bar"><i></i></div>' : '')
                    + notesHtml(c);
            }
        }
        if (!c) { card.classList.remove('over'); return; }
        var elapsed = (t - ts(c.startsAt)) / 1000;
        if (c.endsAt === null) {
            card.classList.remove('over');
            setText('now-big', dur(elapsed)); setText('now-big-l', 'läuft (ohne Dauer)');
            return;
        }
        var remaining = (ts(c.endsAt) - t) / 1000, over = remaining < 0;
        card.classList.toggle('over', over);
        setText('now-big', (over ? '+' : '') + dur(remaining), 'big num ' + (over ? 'late-t' : remaining < 60 ? 'slight-t' : ''));
        setText('now-big-l', over ? 'überzogen' : 'verbleibend');
        setText('now-el', dur(elapsed));
        var bar = $('now-bar');
        if (bar) { bar.classList.toggle('over', over); bar.firstChild.style.width = Math.min(100, Math.max(0, elapsed / c.durationSeconds * 100)).toFixed(1) + '%'; }
    }

    function renderNext(t, overrun) {
        var n = state.next, body = $('next-body');
        var key = (n ? n.id : 'none|' + state.live.state) + '|' + state.revision + '|' + JSON.stringify(shown);
        if (key !== nextKey) {
            nextKey = key;
            if (!n) {
                $('next-pos').textContent = '';
                body.innerHTML = '<p class="empty">' + (state.live.state === 'finished' ? '–' : 'Kein weiterer Programmpunkt.') + '</p>'
                    + (CAN_CONTROL && state.live.state === 'running' ? '<button class="btn start-next" type="button" data-action="finish">■ Programm beenden</button>' : '');
            } else {
                var it0 = itemById(n.id);
                $('next-pos').textContent = '#' + n.position;
                body.innerHTML = '<p class="item-title">' + esc(n.name) + '</p>'
                    + (n.description ? '<p class="item-desc">' + esc(n.description) + '</p>' : '')
                    + noStrobeBanner(n)
                    + '<div class="timer-row"><div><div id="next-big" class="big num"></div><div class="meta" id="next-big-l"></div></div>'
                    + '<div class="meta">Start voraussichtlich <b class="num" id="next-at"></b>' + (it0 ? '<br>geplant <b class="num">' + hm(it0.plannedStart) + '</b>' : '')
                    + (n.durationSeconds ? '<br>Dauer <b>' + dur(n.durationSeconds) + '</b>' : '') + '</div></div>'
                    + notesHtml(n)
                    + (CAN_CONTROL ? '<button class="btn go start-next" type="button" data-start="' + esc(n.id) + '">▶ <span>Start: ' + esc(n.name) + '</span></button>' : '');
            }
        }
        if (!n) { return; }
        var it = itemById(n.id);
        var at = (it ? ts(it.currentStart) : ts(n.startsAt)) + overrun * 1000;
        var diff = (at - t) / 1000;
        setText('next-big', diff < 0 ? '+' + dur(diff) : dur(diff), 'big num ' + (diff < 0 ? 'late-t' : ''));
        setText('next-big-l', diff < 0 ? 'überfällig' : 'bis Start');
        setText('next-at', hms(Math.max(at, state.live.tracking ? t : at)).slice(0, 5));
    }

    function renderList() {
        // Rebuilt on structural changes and once per overrun minute, never
        // while a start time is being typed in.
        var key = state.revision + '|' + (state.current ? state.current.id : '') + '|' + (state.next ? state.next.id : '') + '|' + state.live.state + '|' + Math.floor(state.live.overrunSeconds / 60);
        if (key === listKey || (openTimeForm && listKey !== '')) { return; }
        listKey = key;
        $('count').textContent = state.items.length + ' Punkte';
        if (!state.items.length) { $('list').innerHTML = '<p class="empty">Dieses Minutenprogramm hat noch keine Programmpunkte.</p>'; renderInfo(); return; }
        $('list').innerHTML = state.items.map(function (it) {
            var shifted = hm(it.projectedStart) !== hm(it.plannedStart);
            var badge = (it.status === 'current' ? '<span class="badge now">Jetzt</span>' : it.status === 'next' ? '<span class="badge nx">Next</span>' : '')
                + (noStrobe(it) ? '<span class="badge nostrobe">Kein Strobo</span>' : '');
            var acts = CAN_CONTROL
                ? '<div class="acts"><button class="btn sm ' + (it.status === 'next' ? 'go' : '') + '" type="button" data-start="' + esc(it.id) + '">▶ Start</button>'
                  + '<button class="btn sm" type="button" data-timeform="' + esc(it.id) + '" title="Tatsächliche Startzeit eintragen">Zeit…</button></div>'
                : '<div></div>';
            return '<div class="it ' + it.status + '" id="it-' + esc(it.id) + '">'
                + '<div class="tm num">' + hm(it.projectedStart) + (shifted ? '<s>' + hm(it.plannedStart) + '</s>' : '') + (it.durationMinutes != null ? '<small>' + it.durationMinutes + ' min</small>' : '') + '</div>'
                + '<div><div class="nm"><span class="pos">' + it.position + '.</span>' + esc(it.name) + badge + '</div>'
                + (it.description ? '<div class="ds">' + esc(it.description) + '</div>' : '')
                + (it.actualStart ? '<div class="ds">gestartet ' + hms(ts(it.actualStart)) + '</div>' : '') + '</div>'
                + acts + notesHtml(it)
                + (CAN_CONTROL ? '<form class="tform" data-tform="' + esc(it.id) + '"><span class="hint">Tatsächlich gestartet um</span><input class="input num" name="started_at" placeholder="HH:MM:SS" value="' + hms(now()) + '"><button class="btn sm primary" type="submit">Übernehmen</button></form>' : '')
                + '</div>';
        }).join('');
        if (openTimeForm) { var f = document.querySelector('[data-tform="' + openTimeForm + '"]'); if (f) { f.classList.add('open'); } }
        renderInfo();
    }

    function renderInfo() {
        var p = state.program, rows = [
            ['Veranstaltung', state.event.name],
            ['Kein Strobo', state.event.noStrobe ? 'bei der gesamten Veranstaltung' : null],
            ['Beginn', hm(p.plannedStart) + (hm(p.currentStart) !== hm(p.plannedStart) ? ' (aktuell ' + hm(p.currentStart) + ')' : '')],
            ['Ende geplant', hm(p.plannedEnd)],
            ['Raum', p.room],
            ['Jingle vorher', p.jingleBefore ? (p.jingleTitle || 'ja') : null],
            ['Handmikrofone', p.handheldMicCount || null],
            ['Headsets', p.headsetMicCount || null]
        ];
        $('info').innerHTML = rows.filter(function (r) { return r[1] !== null && r[1] !== '' && r[1] !== undefined; })
            .map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join('');
    }

    // ---------------------------------------------------------------- data
    function apply(data, sentAt) {
        if (!data || !data.items) { return; }
        var receivedAt = Date.now();
        offset = new Date(data.serverTime).getTime() - (sentAt + receivedAt) / 2;
        state = data;
        render();
    }

    function setConn(ok) { $('led').classList.toggle('off', !ok); $('conn').textContent = ok ? 'verbunden' : 'keine Verbindung'; }

    function poll() {
        var sentAt = Date.now();
        fetch(BASE + '/status', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { if (r.status === 401 || r.status === 403) { location.reload(); } return r.json(); })
            .then(function (d) { setConn(true); apply(d, sentAt); })
            .catch(function () { setConn(false); });
    }

    var toastTimer = null;
    function toast(msg, err) {
        var el = $('toast'); el.textContent = msg; el.className = 'toast show' + (err ? ' err' : '');
        clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.className = 'toast'; }, 2600);
    }

    function act(path, fields, okMsg) {
        if (busy) { return; }
        busy = true;
        var fd = new FormData();
        Object.keys(fields || {}).forEach(function (k) { fd.append(k, fields[k]); });
        fd.append('csrf_token', CSRF);
        var sentAt = Date.now();
        fetch(BASE + '/' + path, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF } })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok || res.d.ok === false) { toast(res.d.error || 'Aktion fehlgeschlagen.', true); return; }
                openTimeForm = null; listKey = '';
                apply(res.d, sentAt);
                if (okMsg) { toast(okMsg); }
            })
            .catch(function () { toast('Keine Verbindung zum Server.', true); })
            .finally(function () { busy = false; });
    }

    // ---------------------------------------------------------------- controls
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('button');
        if (!b) { return; }
        if (b.dataset.copy) {
            (navigator.clipboard ? navigator.clipboard.writeText(b.dataset.copy) : Promise.reject())
                .then(function () { toast('Kopiert.'); }, function () { toast('Kopieren nicht möglich.', true); });
            return;
        }
        if (!CAN_CONTROL) { return; }
        if (b.dataset.msgAck) {
            act('nachricht-gesehen/' + encodeURIComponent(b.dataset.msgAck), {}, 'Als gesehen markiert.');
        } else if (b.dataset.msgClose) {
            var msg = (state.messages || []).filter(function (m) { return m.id === b.dataset.msgClose; })[0];
            if (msg && msg.stage && !confirm('Nachricht ausblenden? Sie verschwindet auch vom Stagedisplay.')) { return; }
            act('nachricht-ausblenden/' + encodeURIComponent(b.dataset.msgClose), {}, 'Nachricht ausgeblendet.');
        } else if (b.dataset.start) {
            var it = itemById(b.dataset.start);
            var isNext = state.next && state.next.id === b.dataset.start;
            if (!isNext && !confirm('„' + (it ? it.name : '') + '“ jetzt starten? Der Verzug wird danach neu berechnet.')) { return; }
            act('start/' + encodeURIComponent(b.dataset.start), {}, 'Gestartet: ' + (it ? it.name : ''));
        } else if (b.dataset.timeform) {
            openTimeForm = openTimeForm === b.dataset.timeform ? null : b.dataset.timeform;
            document.querySelectorAll('.tform').forEach(function (f) {
                var open = f.dataset.tform === openTimeForm;
                f.classList.toggle('open', open);
                if (open) { var inp = f.querySelector('input'); inp.value = hms(now()); inp.focus(); inp.select(); }
            });
        } else if (b.dataset.adjust) {
            act('verzug', { adjust_seconds: b.dataset.adjust });
        } else if (b.id === 'zero-btn') {
            act('verzug', { delay_seconds: '0' }, 'Verzug auf 0 gesetzt.');
        } else if (b.id === 'adopt-btn') {
            act('verzug', { adopt: '1' }, 'Prognose als Verzug übernommen.');
        } else if (b.id === 'finish-btn' || b.dataset.action === 'finish') {
            if (confirm('Programm als beendet markieren?')) { act('ende', {}, 'Programm beendet.'); }
        } else if (b.id === 'reset-btn') {
            if (confirm('Verzug, gestarteten Programmpunkt und Ende zurücksetzen? Danach gilt wieder der ursprüngliche Plan.')) { act('zuruecksetzen', {}, 'Zurückgesetzt.'); }
        }
    });

    document.addEventListener('submit', function (ev) {
        var f = ev.target;
        if (f.id === 'delay-form') {
            ev.preventDefault();
            var v = $('delay-input').value.trim();
            if (v === '') { return; }
            act('verzug', { delay: v }, 'Verzug gesetzt.');
            $('delay-input').value = '';
        } else if (f.dataset.tform) {
            ev.preventDefault();
            act('start/' + encodeURIComponent(f.dataset.tform), { started_at: f.querySelector('input').value }, 'Startzeit übernommen.');
        }
    });

    // Keep the screen awake at FOH (where supported).
    function wake() { try { if (navigator.wakeLock && document.visibilityState === 'visible') { navigator.wakeLock.request('screen').catch(function () {}); } } catch (e) {} }
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { wake(); poll(); } });
    wake();

    offset = new Date(state.serverTime).getTime() - Date.now();
    render();
    setInterval(render, 250);
    setInterval(function () { if (document.visibilityState === 'visible') { poll(); } }, 3000);
})();
</script>
</body>
</html>
