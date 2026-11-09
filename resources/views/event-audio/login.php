<?php
/**
 * Anmeldung für Veranstalter-Zugänge zur Live-Oberfläche (Migration 135).
 *
 * @var ?string $error
 * @var string $username
 */

use App\Infrastructure\Http\CompanyBranding;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$logo = CompanyBranding::darkLogoUrl() ?? CompanyBranding::logoUrl();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1020">
<meta name="robots" content="noindex">
<title>Anmeldung – Audio &amp; Durchsagen</title>
<style>
    :root { --bg:#0b1020; --panel:#121a2e; --line:#24304d; --text:#e8ecf6; --muted:#8b97b5; --accent:#f5b43c; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; display:grid; place-items:center; background: radial-gradient(1200px 600px at 50% -10%, #1b2748, var(--bg)); color: var(--text); padding: 24px 16px; }
    .box { width:100%; max-width: 380px; background: var(--panel); border:1px solid var(--line); border-radius: 18px; padding: 28px 22px; box-shadow: 0 20px 60px rgba(0,0,0,.35); }
    img { display:block; max-height:48px; max-width:180px; margin: 0 auto 18px; object-fit: contain; }
    h1 { margin:0; font-size:1.25rem; text-align:center; }
    p.sub { margin:6px 0 22px; text-align:center; color: var(--muted); font-size:.9rem; }
    label { display:block; font-size:.8rem; color: var(--muted); margin: 14px 0 6px; }
    input { width:100%; font: inherit; font-size:1rem; color: var(--text); background: var(--bg); border:1px solid var(--line); border-radius: 10px; padding: 12px; }
    input:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
    button { width:100%; margin-top: 22px; border:0; border-radius: 10px; padding: 14px; font: inherit; font-weight: 700; font-size: 1rem; background: var(--accent); color:#1a1203; cursor:pointer; }
    .err { margin-top: 14px; padding: 10px 12px; border-radius: 10px; background: rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.4); color:#fecaca; font-size:.85rem; }
    .foot { margin-top: 18px; text-align:center; font-size:.75rem; color: var(--muted); }
    .foot a { color: var(--muted); }
</style>
</head>
<body>
<form class="box" method="post" action="/live/login" autocomplete="on">
    <?php if ($logo !== null): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <h1>Audio &amp; Durchsagen</h1>
    <p class="sub">Anmeldung für Veranstalter</p>
    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
    <label for="username">Benutzername</label>
    <input id="username" name="username" value="<?= e($username) ?>" required autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="username" <?= $username === '' ? 'autofocus' : '' ?>>
    <label for="password">Passwort</label>
    <input id="password" name="password" type="password" required autocomplete="current-password" <?= $username !== '' ? 'autofocus' : '' ?>>
    <?php if ($error !== null): ?><div class="err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <button type="submit">Anmelden</button>
    <p class="foot">Mitarbeiter? <a href="/login">Zur normalen Anmeldung</a></p>
</form>
</body>
</html>
