<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

/**
 * Records that the signed-in user finished or skipped the onboarding tour,
 * so it no longer starts automatically. As with UpdateLocale, the controller
 * only ever passes the user's own ID (from CurrentUser::get()).
 */
final class CompleteOnboardingTour implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
    ) {
    }
}
