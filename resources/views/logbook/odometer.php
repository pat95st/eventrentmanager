<?php
/**
 * Odometer check before recording a trip (migration 185), for logged-in and
 * external drivers. Standalone mobile page.
 *
 * @var \App\Domain\Vehicle\Vehicle $vehicle
 * @var string $action
 * @var string|null $gapAction only for logged-in drivers
 * @var string|null $error
 * @var array{expected: int, reading: int}|null $gap reading above the last recorded one (logged-in drivers only)
 * @var \DateTimeImmutable|null $lockedUntil
 * @var string $who name of the logged-in user, '' for external drivers
 * @var string|null $backUrl
 */

use App\Infrastructure\Http\CompanyBranding;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$logo = CompanyBranding::darkLogoUrl() ?? CompanyBranding::logoUrl();
$pageTitle = 'Kilometerstand – ' . $vehicle->name;
$km = static fn (int $v): string => number_format($v, 0, ',', '.');
require __DIR__ . '/_mobile-head.php';
?>
<body>
<header class="top">
    <div class="wrap top-inner">
        <?php if ($logo !== null): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <div class="title">
            <h1>Fahrt erfassen</h1>
            <p><?= e($vehicle->displayName()) ?></p>
        </div>
        <?php if ($who !== ''): ?><div class="who"><?= e($who) ?><?php if ($backUrl !== null): ?><br><a href="<?= e($backUrl) ?>">Zum Fahrtenbuch</a><?php endif; ?></div><?php endif; ?>
    </div>
</header>
<main class="wrap">
    <?php if ($lockedUntil !== null): ?>
        <div class="alert err" role="alert">Zu viele falsche Eingaben. Bitte versuche es ab <?= e($lockedUntil->format('H:i')) ?> Uhr erneut oder wende dich an das Büro.</div>
    <?php elseif ($error !== null): ?>
        <div class="alert err" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Aktueller Kilometerstand</h2>
        <p class="hint">Bitte lies den Kilometerstand am Tacho ab, bevor du losfährst. Erst wenn er zum zuletzt erfassten Stand passt, kannst du die Fahrt erfassen.</p>
        <form method="post" action="<?= e($action) ?>" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <label class="field">Kilometerstand jetzt (km)
                <input type="text" name="odometer" inputmode="numeric" pattern="[0-9.]*" required autofocus <?= $lockedUntil !== null ? 'disabled' : '' ?> placeholder="z. B. 48250">
            </label>
            <div class="row"><button type="submit" class="btn primary big" <?= $lockedUntil !== null ? 'disabled' : '' ?>>Weiter</button></div>
        </form>
    </section>

    <?php if ($gap !== null && $gapAction !== null): ?>
        <section class="card">
            <h2>Fehlen Fahrten?</h2>
            <p class="hint">Zuletzt erfasst sind <?= e($km($gap['expected'])) ?> km, du hast <?= e($km($gap['reading'])) ?> km abgelesen. Wenn dazwischen Fahrten nicht eingetragen wurden, kannst du die Lücke melden (<?= e($km($gap['reading'] - $gap['expected'])) ?> km). Ein Fahrtenbuch-Verwalter klärt sie anschließend; danach kannst du deine Fahrt erfassen.</p>
            <form method="post" action="<?= e($gapAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="odometer" value="<?= e((string) $gap['reading']) ?>">
                <div class="row"><button type="submit" class="btn big">Lücke melden und weiter</button></div>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
