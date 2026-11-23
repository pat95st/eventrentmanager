<?php
/**
 * Onboarding tour (migration 161): builds the steps for the signed-in user
 * and hands them to public/assets/js/onboarding-tour.js.
 *
 * Starts automatically while users.onboarding_tour_completed_at is NULL, or
 * on demand via "?tour=start" (button under Profil › Darstellung). Each step
 * points at an element marked with data-tour="..." in layout.php; steps for
 * areas the user has no permission for are left out here, so the tour only
 * ever explains what this user can actually see.
 *
 * @var \App\Domain\User\User|null $currentUser
 * @var bool $canArticles
 * @var bool $canContacts
 * @var bool $canProcurement
 * @var bool $canTechnicianRentals
 * @var bool $canInspections
 * @var bool $canDocuments
 * @var bool $canRentals
 * @var bool $isAdmin
 */

use App\Domain\User\TwoFactorMethod;
use App\Infrastructure\Http\Auth\Session;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

if ($currentUser === null) {
    return;
}

$tourRequested = ($_GET['tour'] ?? '') === 'start';
// Never start on its own while the user still has to finish a mandatory
// account step, or while an administrator is impersonating them.
$tourBlocked = $currentUser->mustChangePassword
    || ($currentUser->mustSetupTwoFactor && $currentUser->twoFactorMethod === TwoFactorMethod::None)
    || Session::isImpersonating();
$tourAuto = $currentUser->onboardingTourCompletedAt === null && !$tourBlocked;

if (!$tourRequested && !$tourAuto) {
    return;
}

$step = static fn (string $id, string $target, string $key, array $extra = []): array => [
    'id' => $id,
    'target' => $target,
    'title' => t('tour.' . $key . '.title'),
    'body' => t('tour.' . $key . '.body'),
] + $extra;

$firstName = trim((string) ($currentUser->firstName ?? ''));
$steps = [[
    'id' => 'welcome',
    'target' => null,
    'title' => $firstName !== '' ? t('tour.welcome.title', ['name' => $firstName]) : t('tour.welcome.title_anonymous'),
    'body' => t('tour.welcome.body'),
]];

$steps[] = $step('home', '[data-tour="home"]', 'home');
// Only on the home page itself; skipped elsewhere (optional).
$steps[] = $step('dashboard', '[data-tour="dashboard-configure"]', 'dashboard', ['optional' => true]);

// Main navigation, in the order of the menu. On narrow screens these are
// folded into one step that points at the menu button (see the JS).
$navSteps = [
    ['nav-articles', 'articles', $canArticles],
    ['nav-contacts', 'contacts', $canContacts],
    ['nav-procurement', 'procurement', $canProcurement],
    ['nav-sales', 'sales', $canArticles],
    ['nav-technician-rentals', 'technician_rentals', $canTechnicianRentals],
    ['nav-inspections', 'inspections', $canInspections],
    ['nav-documents', 'documents', $canDocuments],
    ['nav-rentals', 'rentals', $canRentals],
    ['nav-vehicles', 'vehicles', $canRentals || \App\Domain\Logbook\LogbookAccess::canCapture($currentUser ?? null) || (($currentUser ?? null)?->has(\App\Domain\User\Permission::FahrtenbuchVerwalten) ?? false)],
    ['nav-vouchers', 'vouchers', $isAdmin],
];
foreach ($navSteps as [$tourKey, $textKey, $allowed]) {
    if ($allowed) {
        $steps[] = $step($tourKey, '[data-tour="' . $tourKey . '"]', $textKey, ['group' => 'nav']);
    }
}

$steps[] = $step('settings', '[data-tour="settings"]', 'settings', [
    'mobileTarget' => '[data-tour="settings-mobile"]',
    'mobileBody' => t('tour.settings_mobile.body'),
]);
$steps[] = [
    'id' => 'user-menu',
    'target' => '[data-tour="user-menu"]',
    'title' => t('tour.user_menu.title'),
    'body' => $isAdmin ? t('tour.user_menu.body_admin') : t('tour.user_menu.body'),
    // Same menu as the settings button on narrow screens, already covered there.
    'desktopOnly' => true,
];
$steps[] = [
    'id' => 'done',
    'target' => null,
    'title' => t('tour.done.title'),
    'body' => t('tour.done.body'),
];

$tourConfig = [
    'steps' => $steps,
    'menu' => [
        'target' => '#mobile-menu-toggle',
        'title' => t('tour.menu.title'),
        'body' => t('tour.menu.body'),
    ],
    'completeUrl' => '/profil/tour/abschliessen',
    // A replay from the settings does not need to be recorded again.
    'record' => $currentUser->onboardingTourCompletedAt === null,
    'labels' => [
        'dialog' => t('tour.dialog_label'),
        'stepOf' => t('tour.step_of'),
        'next' => t('tour.next'),
        'back' => t('tour.back'),
        'skip' => t('tour.skip'),
        'start' => t('tour.start'),
        'finish' => t('tour.finish'),
    ],
];
?>
<script>window.ONBOARDING_TOUR = <?= json_encode($tourConfig, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/assets/js/onboarding-tour.js?v=<?= e((string) (@filemtime(dirname(__DIR__, 3) . '/public/assets/js/onboarding-tour.js') ?: 1)) ?>" defer></script>
