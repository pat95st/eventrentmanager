<?php
/**
 * Head and styles of the standalone mobile logbook pages (capture, QR),
 * in the style of the audio live page (event-audio/live.php).
 *
 * @var string $pageTitle
 */

use function App\Infrastructure\Http\e;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<meta name="robots" content="noindex">
<title><?= e($pageTitle) ?></title>
<style>
    :root {
        --bg: #0b1020; --panel: #121a2e; --panel-2: #18223b; --line: #24304d; --text: #e8ecf6; --muted: #8b97b5;
        --accent: #f5b43c; --accent-ink: #1a1203; --go: #22c55e; --stop: #ef4444; --info: #60a5fa;
        --radius: 14px;
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        color-scheme: dark;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); }
    body { min-height: 100vh; padding-bottom: calc(6rem + env(safe-area-inset-bottom)); -webkit-tap-highlight-color: transparent; }
    button, textarea, input, select { font: inherit; color: inherit; }
    button { cursor: pointer; }
    a { color: inherit; }
    [hidden] { display: none !important; }
    .wrap { max-width: 720px; margin: 0 auto; padding: 0 16px; }

    header.top { position: sticky; top: 0; z-index: 20; background: rgba(11,16,32,.92); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
    .top-inner { display: flex; align-items: center; gap: 12px; padding-top: 10px; padding-bottom: 10px; }
    .top-inner img { max-height: 40px; max-width: 140px; object-fit: contain; }
    .title { min-width: 0; flex: 1; }
    .title h1 { margin: 0; font-size: 1rem; font-weight: 700; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .title p { margin: 2px 0 0; font-size: .75rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .who { font-size: .75rem; color: var(--muted); text-align: right; }
    .who a { color: var(--muted); }

    section.card { margin-top: 16px; background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); padding: 16px; }
    section.card h2 { margin: 0 0 4px; font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 700; }

    .field { display: block; font-size: .75rem; color: var(--muted); margin-top: 12px; }
    .field input, .field select, .field textarea { display: block; width: 100%; min-width: 0; -webkit-appearance: none; appearance: none; margin-top: 4px; background: var(--bg); border: 1px solid var(--line); border-radius: 10px; padding: 12px; font-size: 1rem; color: var(--text); min-height: 48px; }
    .field textarea { min-height: 72px; resize: vertical; }
    .field input:focus, .field select:focus, .field textarea:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
    .field.err input, .field.err select, .field.err textarea { border-color: var(--stop); }
    .error { display: block; margin-top: 4px; color: #fca5a5; font-size: .78rem; }
    .field select { background-image: linear-gradient(45deg, transparent 50%, var(--muted) 50%), linear-gradient(135deg, var(--muted) 50%, transparent 50%); background-position: calc(100% - 18px) 50%, calc(100% - 13px) 50%; background-size: 5px 5px; background-repeat: no-repeat; padding-right: 32px; }
    .grid2, .grid3 { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 0 12px; }
    .grid2 > *, .grid3 > * { min-width: 0; }
    .grid3 > :first-child { grid-column: 1 / -1; }
    @media (min-width: 520px) { .grid3 { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr); } .grid3 > :first-child { grid-column: auto; } }
    .hint { font-size: .75rem; color: var(--muted); margin: 6px 0 0; }

    .seg { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .seg label { flex: 1 1 0; min-width: 0; }
    .seg input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .seg span { display: block; text-align: center; padding: 12px 4px; border-radius: 12px; border: 1px solid var(--line); background: var(--panel-2); font-weight: 600; font-size: .85rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .seg input:checked + span { background: var(--text); color: var(--bg); border-color: var(--text); }
    .seg input:focus-visible + span { outline: 2px solid var(--info); outline-offset: 2px; }

    .toggle { display: flex; align-items: center; gap: 10px; font-size: .95rem; user-select: none; margin-top: 14px; }
    .toggle input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .toggle .sw { width: 42px; height: 24px; flex: none; border-radius: 99px; background: var(--line); position: relative; transition: background .15s; }
    .toggle .sw::after { content: ""; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 99px; background: #fff; transition: transform .15s; }
    .toggle input:checked + .sw { background: var(--accent); }
    .toggle input:checked + .sw::after { transform: translateX(18px); }
    .toggle input:focus-visible + .sw { outline: 2px solid var(--info); outline-offset: 2px; }

    .btn { border: 1px solid var(--line); background: var(--panel-2); border-radius: 10px; padding: 11px 14px; font-weight: 600; font-size: .9rem; min-height: 44px; }
    .btn:disabled { opacity: .45; cursor: default; }
    .btn.primary { background: var(--accent); color: var(--accent-ink); border-color: var(--accent); }
    .btn.danger { color: #fca5a5; }
    .btn.big { padding: 14px 20px; font-size: 1.05rem; width: 100%; }
    .row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; }
    .row .btn { flex: 1 1 auto; }
    .inline { display: flex; gap: 8px; align-items: flex-start; }
    .inline .field { flex: 1; min-width: 0; }
    .inline .btn { margin-top: 30px; flex: none; }
    .swap { display: flex; justify-content: center; margin: 6px 0 -4px; }
    .swap button { background: none; border: 1px solid var(--line); border-radius: 99px; width: 40px; height: 40px; color: var(--muted); font-size: 1.1rem; }
    .result { margin-top: 10px; padding: 12px; border-radius: 12px; background: var(--panel-2); border: 1px solid var(--line); font-size: .95rem; }
    .result b { color: var(--accent); font-size: 1.15rem; }

    .alert { margin-top: 14px; padding: 12px 14px; border-radius: 12px; font-size: .9rem; border: 1px solid; }
    .alert.err { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.4); color: #fecaca; }
    .alert.ok { background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.4); color: #bbf7d0; }
    .alert.info { background: rgba(96,165,250,.1); border-color: rgba(96,165,250,.35); color: #dbeafe; }

    .bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 30; padding: 12px 0 calc(12px + env(safe-area-inset-bottom)); background: rgba(11,16,32,.95); border-top: 1px solid var(--line); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }

    .modal { position: fixed; inset: 0; z-index: 50; display: grid; place-items: end center; padding: 16px 16px calc(16px + env(safe-area-inset-bottom)); background: rgba(3,6,15,.6); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); opacity: 0; transition: opacity .15s; }
    .modal.show { opacity: 1; }
    @media (min-width: 640px) { .modal { place-items: center; } }
    .modal-box { width: 100%; max-width: 420px; background: var(--panel); border: 1px solid var(--line); border-radius: 18px; padding: 20px; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
    .modal-box .q { margin: 0; font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .modal-box .what { margin: 8px 0 4px; font-size: 1.25rem; font-weight: 700; line-height: 1.25; }
    .modal-box .sub { margin: 0; font-size: .85rem; color: var(--muted); }
    .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
    .modal-actions .btn { flex: 1; padding: 14px; font-size: 1rem; }

    .history { list-style: none; margin: 8px 0 0; padding: 0; font-size: .82rem; }
    .history li { padding: 8px 0; border-top: 1px solid var(--line); }
    .history li:first-child { border-top: 0; }
    .history small { display: block; color: var(--muted); }
</style>
</head>
