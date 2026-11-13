<?php
/** @var array<string, list<\App\Application\Dashboard\DashboardTile>> $zones Kacheln je Bereich (main/side/bottom) in Anzeigereihenfolge – nur solche, die der Benutzer sehen darf */
/** @var array<string, array{count: int, items: array<int, array{label: string, sub: string, href: ?string}>, href: string}> $sections Daten der Zähler-Kacheln nach Kachel-Schlüssel */
/** @var array<string, \App\Application\Statistics\Query\StatisticChart> $charts Daten der Diagramm-Kacheln nach Kachel-Schlüssel */
/** @var list<array{date: \DateTimeImmutable, isToday: bool, events: list<array{kind: string, time: ?string, name: string, customer: string, location: string, href: string}>}> $schedule */

use App\Application\Dashboard\DashboardTile;
use App\Domain\Dashboard\DashboardLayout;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$main = $zones[DashboardLayout::ZONE_MAIN] ?? [];
$side = $zones[DashboardLayout::ZONE_SIDE] ?? [];
$bottom = $zones[DashboardLayout::ZONE_BOTTOM] ?? [];

// Eine Kachel bzw. – in den Spalten – mehrere aufeinanderfolgende
// Zähler-Kacheln als gemeinsame Karte.
$renderTiles = static function (array $tiles, bool $groupCompact) use ($sections, $charts, $schedule): array {
    $blocks = [];
    $compact = [];
    $flush = static function () use (&$blocks, &$compact, $sections): void {
        if ($compact !== []) {
            $blocks[] = ['wide' => false, 'html' => View::render('dashboard/_compact', ['tiles' => $compact, 'sections' => $sections])];
            $compact = [];
        }
    };
    foreach ($tiles as $tile) {
        if ($tile->kind === DashboardTile::KIND_COMPACT) {
            $compact[] = $tile;
            if (!$groupCompact) {
                $flush();
            }
            continue;
        }
        $flush();
        $blocks[] = $tile->kind === DashboardTile::KIND_SCHEDULE
            ? ['wide' => true, 'html' => View::render('dashboard/_schedule', ['schedule' => $schedule])]
            : ['wide' => false, 'html' => View::render('dashboard/_chart', ['chart' => $charts[$tile->key]])];
    }
    $flush();

    return $blocks;
};

// Als Raster (untere Reihe, oder die Seitenleiste, wenn der Hauptbereich leer ist).
$renderGrid = static function (array $tiles) use ($renderTiles): void {
    echo '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:items-start">';
    foreach ($renderTiles($tiles, false) as $block) {
        echo '<div class="min-w-0 ' . ($block['wide'] ? 'sm:col-span-2' : '') . '">' . $block['html'] . '</div>';
    }
    echo '</div>';
};
$renderStack = static function (array $tiles) use ($renderTiles): void {
    foreach ($renderTiles($tiles, true) as $block) {
        echo $block['html'];
    }
};
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-2">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('dashboard.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('dashboard.subtitle')) ?></p>
    </div>
    <a href="/profil/dashboard" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
        <?= e(t('dashboard.configure')) ?>
    </a>
</div>

<?php if ($main === [] && $side === [] && $bottom === []): ?>
    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('dashboard.empty')) ?></p>
        <a href="/profil/dashboard" class="mt-3 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('dashboard.configure')) ?>
        </a>
    </div>
<?php else: ?>
    <div class="space-y-6">
        <?php if ($main !== [] && $side !== []): ?>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
                <div class="min-w-0 space-y-6 lg:col-span-2"><?php $renderStack($main); ?></div>
                <div class="min-w-0 space-y-4"><?php $renderStack($side); ?></div>
            </div>
        <?php elseif ($main !== []): ?>
            <div class="space-y-6"><?php $renderStack($main); ?></div>
        <?php elseif ($side !== []): ?>
            <?php $renderGrid($side); ?>
        <?php endif; ?>

        <?php if ($bottom !== []): ?>
            <?php $renderGrid($bottom); ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($charts !== []): ?>
    <style>
        .stat-card:hover, .stat-card:focus-visible { border-color: rgb(var(--color-brand-300, 148 163 184)); box-shadow: 0 4px 12px -4px rgba(15, 23, 42, .15); outline: none; }
        .stat-card:hover .stat-card-more, .stat-card:focus-visible .stat-card-more { text-decoration: underline; }
    </style>
    <script src="/assets/js/dashboard-charts.js" defer></script>
<?php endif; ?>
