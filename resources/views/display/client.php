<?php
/**
 * Browser client for stage and FOH displays (migration 167). Public page
 * without login: the device token is entered once (or passed as URL anchor
 * "#token=…", which never reaches the server) and stored in localStorage.
 * Everything is rendered client-side from GET /api/display/v1/state, so this
 * page is just one implementation of docs/display-api.md – the same client
 * shows the stage or FOH layout depending on the role set in
 * Settings › Stage & FOH displays.
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#05070d">
<title>Display</title>
<style>
    :root {
        --bg: #05070d; --panel: #0f1526; --line: #222c45; --text: #f1f4fb; --muted: #8a96b3;
        --accent: #f5b43c; --go: #22c55e; --warn: #f59e0b; --stop: #ef4444; --info: #60a5fa;
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    body.light { --bg: #f6f7fb; --panel: #ffffff; --line: #d9deea; --text: #0d1324; --muted: #5a6683; --accent: #b7791f; }
    * { box-sizing: border-box; }
    html, body { margin: 0; height: 100%; background: var(--bg); color: var(--text); }
    body { display: flex; flex-direction: column; overflow: hidden; }
    button, input { font: inherit; color: inherit; }
    .num { font-variant-numeric: tabular-nums; }
    .muted { color: var(--muted); }
    header { display: flex; align-items: center; gap: 1rem; padding: .6rem 1.2rem; border-bottom: 1px solid var(--line); }
    header .title { flex: 1; min-width: 0; }
    header h1 { margin: 0; font-size: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    header p { margin: 0; font-size: .8rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .clock { font-size: 1.9rem; font-weight: 700; }
    .badge { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .9rem; font-weight: 700; border: 1px solid var(--line); }
    .badge.late { background: rgba(239,68,68,.15); color: var(--stop); border-color: rgba(239,68,68,.4); }
    .badge.early { background: rgba(34,197,94,.15); color: var(--go); border-color: rgba(34,197,94,.4); }
    .badge.offline { background: var(--stop); color: #fff; border-color: var(--stop); }
    .strobe { margin: 0; padding: .5rem 1.2rem; background: var(--stop); color: #fff; font-weight: 800; text-align: center; letter-spacing: .04em; }
    main { flex: 1; min-height: 0; display: flex; overflow: hidden; }
    .stage { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 2vh 4vw; gap: 2.5vh; text-align: center; }
    .label { text-transform: uppercase; letter-spacing: .12em; font-size: .85rem; color: var(--muted); font-weight: 700; }
    .current-name { font-size: 4.2rem; font-weight: 800; line-height: 1.1; margin: .2rem 0 0; }
    .countdown { font-size: 11rem; font-weight: 800; line-height: 1; }
    .countdown.warn { color: var(--warn); }
    .countdown.critical, .countdown.over { color: var(--stop); }
    .progress { height: .7rem; background: var(--line); border-radius: 999px; overflow: hidden; max-width: 70rem; width: 100%; margin: 0 auto; }
    .progress > div { height: 100%; background: var(--go); }
    .desc { font-size: 1.5rem; color: var(--muted); margin: 0; white-space: pre-line; }
    .notes { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; }
    .note { background: var(--panel); border: 1px solid var(--line); border-radius: .7rem; padding: .5rem .9rem; font-size: 1.35rem; text-align: left; white-space: pre-line; }
    .note b { display: block; font-size: .75rem; letter-spacing: .1em; text-transform: uppercase; color: var(--accent); }
    .next { font-size: 2rem; }
    .next .when { color: var(--muted); }
    .mics { font-size: 1.1rem; color: var(--muted); }
    .foh { flex: 1; display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); gap: 1rem; padding: 1rem; min-height: 0; }
    .foh .col { display: flex; flex-direction: column; gap: 1rem; min-height: 0; }
    .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 1rem; padding: 1rem 1.2rem; }
    .foh .current-name { font-size: 2.4rem; }
    .foh .countdown { font-size: 6rem; }
    .foh .notes { justify-content: flex-start; }
    .foh .note { font-size: 1.05rem; }
    .list { overflow: auto; flex: 1; padding: 0; }
    .list table { width: 100%; border-collapse: collapse; }
    .list td { padding: .55rem .8rem; border-bottom: 1px solid var(--line); vertical-align: top; font-size: 1.05rem; }
    .list tr.done td { color: var(--muted); }
    .list tr.current td { background: rgba(34,197,94,.12); }
    .list tr.next td { background: rgba(96,165,250,.10); }
    .list .time { white-space: nowrap; width: 1%; }
    .list .small { font-size: .85rem; color: var(--muted); white-space: pre-line; }
    .controls { display: flex; flex-wrap: wrap; gap: .5rem; }
    .btn { border: 1px solid var(--line); background: var(--bg); border-radius: .6rem; padding: .55rem .9rem; cursor: pointer; font-weight: 600; }
    .btn:hover { border-color: var(--muted); }
    .btn.go { background: var(--go); border-color: var(--go); color: #04210f; }
    .btn.small { padding: .25rem .6rem; font-size: .85rem; }
    .center { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 2rem; gap: 1rem; }
    .setup { max-width: 34rem; width: 100%; }
    .setup input { width: 100%; padding: .7rem .9rem; border-radius: .6rem; border: 1px solid var(--line); background: var(--panel); font-family: ui-monospace, monospace; }
    .error { color: var(--stop); }
    @media (max-width: 900px) { .foh { grid-template-columns: 1fr; overflow: auto; } .countdown { font-size: 6rem; } .current-name { font-size: 2.4rem; } }
</style>
</head>
<body>
<div id="app" style="display:contents"></div>
<script>
(function () {
    'use strict';
    var API = '/api/display/v1';
    var KEY = 'display_token';
    var VERSION = 'web-1';
    var app = document.getElementById('app');
    var token = null, data = null, offset = 0, receivedAt = 0, lastKey = '', offline = false, timer = null, setupError = '';
    var TRADES = { general: 'Allgemein', sound: 'Ton', light: 'Licht', video: 'Video', stage: 'Bühne' };

    function store(get, value) {
        try { if (get) { return localStorage.getItem(KEY); } if (value === null) { localStorage.removeItem(KEY); } else { localStorage.setItem(KEY, value); } } catch (e) { /* storage unavailable */ }
        return null;
    }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function now() { return Date.now() + offset; }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function dur(sec, signed) {
        var neg = sec < 0; sec = Math.abs(Math.round(sec));
        var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
        var t = (h > 0 ? h + ':' + pad(m) : m) + ':' + pad(s);
        return (neg ? '−' : (signed ? '+' : '')) + t;
    }
    function hm(iso) { if (!iso) { return '–'; } var d = new Date(iso); return pad(d.getHours()) + ':' + pad(d.getMinutes()); }

    // --- token handling ----------------------------------------------------
    var m = location.hash.match(/token=([A-Za-z0-9_]+)/);
    if (m) { store(false, m[1]); history.replaceState(null, '', location.pathname); }
    token = store(true);

    function showSetup() {
        clearTimeout(timer);
        document.body.className = '';
        app.innerHTML = '<main><div class="center"><div class="setup">' +
            '<h1>Display einrichten</h1>' +
            '<p class="muted">Display-Token aus <b>Einstellungen › Stage- &amp; FOH-Displays</b> eingeben.</p>' +
            (setupError ? '<p class="error">' + esc(setupError) + '</p>' : '') +
            '<form id="setup"><input name="token" autocomplete="off" placeholder="mvd_…" required>' +
            '<p><button class="btn go" type="submit">Verbinden</button></p></form></div></div></main>';
        document.getElementById('setup').addEventListener('submit', function (e) {
            e.preventDefault();
            token = e.target.token.value.trim();
            store(false, token); setupError = ''; poll();
        });
    }

    // --- polling ------------------------------------------------------------
    function request(method, path, body) {
        var opts = { method: method, headers: { 'Authorization': 'Bearer ' + token }, cache: 'no-store' };
        if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
        var sent = Date.now();
        return fetch(API + path, opts).then(function (r) {
            if (r.status === 401) { store(false, null); token = null; setupError = 'Der Token ist ungültig oder das Display ist deaktiviert.'; showSetup(); throw new Error('auth'); }
            return r.json().then(function (j) {
                if (j.serverTime) { offset = Date.parse(j.serverTime) - (sent + Date.now()) / 2; }
                if (!r.ok) { throw new Error(j.error || ('HTTP ' + r.status)); }
                return j;
            });
        });
    }
    function poll() {
        clearTimeout(timer);
        if (!token) { showSetup(); return; }
        request('GET', '/state?version=' + VERSION).then(function (j) {
            offline = false; accept(j);
            timer = setTimeout(poll, Math.max(1, j.pollIntervalSeconds || 3) * 1000);
        }).catch(function (e) {
            if (e.message === 'auth') { return; }
            offline = true; if (data) { render(true); } else { app.innerHTML = '<main><div class="center"><p class="error">Keine Verbindung …</p></div></main>'; }
            timer = setTimeout(poll, 5000);
        });
    }
    function accept(j) {
        data = j; receivedAt = Date.now();
        var rs = j.runSheet;
        var key = [j.display.configRevision, rs && rs.revision, rs && rs.live.state, rs && rs.current && rs.current.id, rs && rs.next && rs.next.id, rs && rs.live.overrunSeconds > 0].join('|');
        render(key !== lastKey); lastKey = key;
    }
    function act(path, body, confirmText) {
        if (confirmText && !confirm(confirmText)) { return; }
        request('POST', path, body || {}).then(function (j) { accept(j); }).catch(function (e) { if (e.message !== 'auth') { alert(e.message); } });
    }

    // --- rendering ------------------------------------------------------------
    function notesHtml(notes, options) {
        var out = '';
        (options.notes || []).forEach(function (t) { if (notes && notes[t]) { out += '<div class="note"><b>' + esc(TRADES[t] || t) + '</b>' + esc(notes[t]) + '</div>'; } });
        return out ? '<div class="notes">' + out + '</div>' : '';
    }
    function delayHtml(live) {
        return '<span class="badge num" data-delay>' + delayText(live) + '</span>';
    }
    function projectedDelay(live) {
        var d = live.projectedDelaySeconds;
        if (live.tracking && live.overrunSeconds > 0) { d += Math.floor((Date.now() - receivedAt) / 1000); }
        return d;
    }
    function delayText(live) {
        var d = projectedDelay(live);
        if (Math.abs(d) < 30) { return 'im Plan'; }
        return (d > 0 ? 'Verzug ' : 'Vorsprung ') + dur(Math.abs(d));
    }
    function delayClass(live) { var d = projectedDelay(live); return Math.abs(d) < 30 ? '' : (d > 0 ? 'late' : 'early'); }

    function header(d, rs, o) {
        var title = rs ? (rs.program.name + (rs.program.room ? ' · ' + rs.program.room : '')) : d.name;
        var sub = rs ? ((rs.event.name ? rs.event.name + ' · ' : '') + d.roleLabel + ' „' + d.name + '“') : d.roleLabel;
        return '<header><div class="title"><h1>' + esc(title) + '</h1><p>' + esc(sub) + '</p></div>' +
            (offline ? '<span class="badge offline">offline</span>' : '') +
            (rs && o.showDelay && rs.live.state !== 'finished' ? delayHtml(rs.live) : '') +
            (o.showClock ? '<span class="clock num" data-clock></span>' : '') + '</header>' +
            (rs && (rs.event.noStrobe || (rs.current && rs.current.noStrobe)) ? '<p class="strobe">⚠ KEIN STROBOSKOP</p>' : '');
    }
    function currentBlock(rs, o) {
        var c = rs.current, s = rs.live.state;
        if (s === 'finished') { return '<div class="label">Ablauf</div><p class="current-name">Programm beendet</p>'; }
        if (!c) {
            return '<div class="label">Beginn in</div><div class="countdown num" data-until="' + esc(rs.program.currentStart) + '"></div>' +
                '<p class="desc">' + esc(rs.program.name) + ' · geplant ' + hm(rs.program.currentStart) + '</p>';
        }
        var html = '<div class="label">Jetzt · ' + esc(c.position) + '/' + esc(rs.program.itemCount) + '</div><p class="current-name">' + esc(c.name) + '</p>';
        if (c.endsAt) {
            html += '<div class="countdown num" data-until="' + esc(c.endsAt) + '" data-thresholds></div>';
            html += '<div class="progress"><div data-progress-from="' + esc(c.startsAt) + '" data-progress-to="' + esc(c.endsAt) + '"></div></div>';
        } else {
            html += '<div class="countdown num" data-since="' + esc(c.startsAt) + '"></div>';
        }
        if (o.showDescription && c.description) { html += '<p class="desc">' + esc(c.description) + '</p>'; }
        return html + notesHtml(c.notes, o);
    }
    function nextBlock(rs, o) {
        var n = rs.next;
        if (!o.showNext || !n || rs.live.state === 'finished') { return ''; }
        return '<div class="next"><span class="label">Danach</span><br><b>' + esc(n.name) + '</b> <span class="when num">· ' + hm(n.startsAt) + ' (in <span data-until="' + esc(n.startsAt) + '"></span>)</span></div>';
    }
    function micsHtml(rs, o) {
        if (!o.showMics || (!rs.program.handheldMicCount && !rs.program.headsetMicCount)) { return ''; }
        return '<p class="mics">Mikrofone: ' + rs.program.handheldMicCount + ' Hand · ' + rs.program.headsetMicCount + ' Headset</p>';
    }
    function listHtml(rs, o, controls) {
        var rows = rs.items.map(function (i) {
            var notes = (o.notes || []).filter(function (t) { return i.notes && i.notes[t]; }).map(function (t) { return (TRADES[t] || t) + ': ' + i.notes[t]; }).join('\n');
            return '<tr class="' + esc(i.status) + '"><td class="time num">' + hm(i.projectedStart) + (i.durationMinutes ? '<div class="small">' + i.durationMinutes + ' min</div>' : '') + '</td>' +
                '<td><b>' + esc(i.name) + '</b>' + (i.noStrobe ? ' <span class="badge late">kein Strobo</span>' : '') +
                (o.showDescription && i.description ? '<div class="small">' + esc(i.description) + '</div>' : '') +
                (notes ? '<div class="small">' + esc(notes) + '</div>' : '') + '</td>' +
                (controls ? '<td class="time">' + (i.status !== 'current' && rs.live.state !== 'finished' ? '<button class="btn small" data-start="' + esc(i.id) + '">Start</button>' : '') + '</td>' : '') + '</tr>';
        }).join('');
        return '<div class="panel list"><table>' + rows + '</table></div>';
    }
    function controlsHtml(rs) {
        if (!rs.live.canControl) { return ''; }
        var next = rs.next && rs.live.state !== 'finished' ? '<button class="btn go" data-start="' + esc(rs.next.id) + '">▶ ' + esc(rs.next.name) + ' starten</button>' : '';
        return '<div class="panel controls">' + next +
            '<button class="btn" data-adjust="-60">−1 min</button><button class="btn" data-adjust="60">+1 min</button>' +
            (rs.live.overrunSeconds > 0 ? '<button class="btn" data-adopt>Verzug übernehmen</button>' : '') +
            '<button class="btn" data-finish>Beenden</button><button class="btn" data-reset>Zurücksetzen</button></div>';
    }

    function render(full) {
        if (!data) { return; }
        var d = data.display, o = d.options, rs = data.runSheet;
        if (full !== false || !app.firstChild) {
            document.body.className = o.theme === 'light' ? 'light' : '';
            document.documentElement.style.fontSize = (o.fontScale || 100) + '%';
            var body;
            if (!rs) {
                body = '<main><div class="center"><p class="current-name">' + esc(d.name) + '</p><p class="muted">Gerade ist kein Minutenprogramm zugeordnet.</p></div></main>';
            } else if (d.role === 'foh') {
                body = '<main><div class="foh"><div class="col"><div class="panel stage" style="padding:1.2rem">' + currentBlock(rs, o) + nextBlock(rs, o) + micsHtml(rs, o) + '</div>' + controlsHtml(rs) + '</div>' +
                    '<div class="col">' + (o.showItemList ? listHtml(rs, o, rs.live.canControl) : '') + '</div></div></main>';
            } else {
                body = '<main><div class="stage">' + currentBlock(rs, o) + nextBlock(rs, o) + micsHtml(rs, o) + '</div>' + (o.showItemList ? '<div style="width:34%;display:flex;padding:1rem">' + listHtml(rs, o, false) + '</div>' : '') + '</main>';
            }
            app.innerHTML = header(d, rs, o) + body;
        } else {
            var badge = app.querySelector('header .badge.offline');
            if (!offline && badge) { badge.remove(); }
        }
        tick();
    }

    function tick() {
        if (!data) { return; }
        var t = now(), th = data.display.thresholds;
        app.querySelectorAll('[data-clock]').forEach(function (el) { var d = new Date(t); el.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()); });
        app.querySelectorAll('[data-until]').forEach(function (el) {
            var rem = (Date.parse(el.dataset.until) - t) / 1000;
            var has = el.hasAttribute('data-thresholds');
            el.textContent = rem < 0 ? (has ? '+' + dur(-rem) : '0:00') : dur(rem);
            if (has) {
                el.classList.toggle('over', rem < 0);
                el.classList.toggle('critical', rem >= 0 && rem <= th.criticalSeconds);
                el.classList.toggle('warn', rem > th.criticalSeconds && rem <= th.warnSeconds);
            }
        });
        app.querySelectorAll('[data-since]').forEach(function (el) { el.textContent = dur((t - Date.parse(el.dataset.since)) / 1000); });
        app.querySelectorAll('[data-progress-from]').forEach(function (el) {
            var a = Date.parse(el.dataset.progressFrom), b = Date.parse(el.dataset.progressTo);
            el.style.width = Math.max(0, Math.min(100, (t - a) / Math.max(1, b - a) * 100)) + '%';
        });
        var rs = data.runSheet;
        app.querySelectorAll('[data-delay]').forEach(function (el) { el.textContent = delayText(rs.live); el.className = 'badge num ' + delayClass(rs.live); });
    }

    app.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) { return; }
        if (b.dataset.start) { act('/items/' + encodeURIComponent(b.dataset.start) + '/start'); }
        else if (b.dataset.adjust) { act('/delay', { adjust_seconds: parseInt(b.dataset.adjust, 10) }); }
        else if (b.hasAttribute('data-adopt')) { act('/delay', { adopt: true }); }
        else if (b.hasAttribute('data-finish')) { act('/finish', null, 'Programm als beendet markieren?'); }
        else if (b.hasAttribute('data-reset')) { act('/reset', null, 'Live-Stand zurücksetzen (Verzug und Starts löschen)?'); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'f' && !e.target.closest('input')) { if (document.fullscreenElement) { document.exitFullscreen(); } else if (document.documentElement.requestFullscreen) { document.documentElement.requestFullscreen(); } }
    });

    setInterval(tick, 250);
    poll();
})();
</script>
</body>
</html>
