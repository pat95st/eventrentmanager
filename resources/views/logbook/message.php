<?php
/**
 * Short message of the public logbook pages (migration 185): saved, invalid
 * QR code, no permission, rate limit.
 *
 * @var \App\Domain\Vehicle\Vehicle|null $vehicle
 * @var string $title
 * @var string $text
 * @var bool $ok
 * @var string|null $againUrl
 */

use App\Infrastructure\Http\CompanyBranding;

use function App\Infrastructure\Http\e;

$logo = CompanyBranding::darkLogoUrl() ?? CompanyBranding::logoUrl();
$pageTitle = $title . ' – Fahrtenbuch';
$againUrl ??= null;
require __DIR__ . '/_mobile-head.php';
?>
<body style="padding-bottom: 2rem">
<header class="top">
    <div class="wrap top-inner">
        <?php if ($logo !== null): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <div class="title">
            <h1>Fahrtenbuch</h1>
            <?php if ($vehicle !== null): ?><p><?= e($vehicle->displayName()) ?></p><?php endif; ?>
        </div>
    </div>
</header>
<main class="wrap">
    <section class="card" style="text-align:center; padding: 32px 20px">
        <div style="font-size: 3rem; line-height: 1"><?= $ok ? '✅' : 'ℹ️' ?></div>
        <h1 style="margin: 12px 0 6px; font-size: 1.35rem"><?= e($title) ?></h1>
        <p class="hint" style="font-size: .95rem"><?= e($text) ?></p>
        <?php if ($againUrl !== null): ?>
            <div class="row"><a class="btn big" href="<?= e($againUrl) ?>" style="text-decoration:none">Weitere Fahrt erfassen</a></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
