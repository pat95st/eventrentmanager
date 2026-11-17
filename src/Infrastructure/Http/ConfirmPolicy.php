<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Settings\SecuritySettings;
use App\Domain\User\User;

/**
 * What the views need to know about skipping confirmations with Shift
 * (migration 174). Set in bootstrap.php like StorageFolderForm; without
 * configuration (e.g. tests) the defaults of SecuritySettings apply.
 */
final class ConfirmPolicy
{
    private static ?SecuritySettings $settings = null;

    public static function configure(SecuritySettings $settings): void
    {
        self::$settings = $settings;
    }

    /** The company-wide switch; the only one the standalone live views follow. */
    public static function companyBypassEnabled(): bool
    {
        return (self::$settings ?? new SecuritySettings())->confirmBypassEnabled;
    }

    /**
     * Configuration for the script in layout.php.
     *
     * @return array{bypass: bool, required: list<string>}
     */
    public static function clientConfig(?User $user): array
    {
        $settings = self::$settings ?? new SecuritySettings();

        return [
            'bypass' => $settings->confirmBypassEnabled && ($user === null || $user->confirmBypassEnabled),
            'required' => $settings->requiredConfirmKeys(),
        ];
    }
}
