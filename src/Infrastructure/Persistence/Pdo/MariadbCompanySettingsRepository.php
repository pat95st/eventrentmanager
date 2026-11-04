<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\BackgroundStyle;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbCompanySettingsRepository implements CompanySettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): CompanySettings
    {
        $stmt = $this->pdo->query('SELECT * FROM company_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration/das Schema nie vorkommen (die
            // Singleton-Zeile wird dort direkt mit angelegt), aber ein
            // klarer Fehler ist hier besser als eine stille Falscheinstellung.
            throw new RuntimeException('Firmeneinstellungen wurden nicht gefunden — fehlt die entsprechende Migration?');
        }

        return new CompanySettings(
            name: $row['name'],
            street: $row['street'],
            postalCode: $row['postal_code'],
            city: $row['city'],
            country: $row['country'],
            logoFilename: $row['logo_filename'],
            logoMaxHeightPx: $row['logo_max_height_px'] !== null ? (int) $row['logo_max_height_px'] : null,
            darkLogoFilename: $row['dark_logo_filename'],
            brandName: $row['brand_name'],
            backgroundImageFilename: $row['background_image_filename'],
            phone: $row['phone'] ?? null,
            email: $row['email'] ?? null,
            website: $row['website'] ?? null,
            faviconFilename: $row['favicon_filename'] ?? null,
            backgroundStyle: $this->style($row, 'background_'),
            loginBackgroundFilename: $row['login_background_filename'] ?? null,
            loginBackgroundStyle: $this->style($row, 'login_background_'),
        );
    }

    /** Darstellung eines Hintergrunds aus den Spalten mit $prefix (Migration 126). */
    private function style(array $row, string $prefix): BackgroundStyle
    {
        return new BackgroundStyle(
            opacity: (int) ($row[$prefix . 'opacity'] ?? 100),
            fit: (string) ($row[$prefix . 'fit'] ?? BackgroundStyle::FIT_COVER),
            positionX: (int) ($row[$prefix . 'position_x'] ?? 50),
            positionY: (int) ($row[$prefix . 'position_y'] ?? 50),
            zoom: (int) ($row[$prefix . 'zoom'] ?? 100),
            blur: (int) ($row[$prefix . 'blur'] ?? 0),
        );
    }

    public function save(CompanySettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE company_settings SET
                name = :name, brand_name = :brand_name, street = :street, postal_code = :postal_code, city = :city,
                country = :country, logo_filename = :logo_filename,
                logo_max_height_px = :logo_max_height_px, dark_logo_filename = :dark_logo_filename,
                background_image_filename = :background_image_filename,
                background_opacity = :bg_opacity, background_fit = :bg_fit, background_position_x = :bg_x,
                background_position_y = :bg_y, background_zoom = :bg_zoom, background_blur = :bg_blur,
                login_background_filename = :login_background_filename,
                login_background_opacity = :lbg_opacity, login_background_fit = :lbg_fit, login_background_position_x = :lbg_x,
                login_background_position_y = :lbg_y, login_background_zoom = :lbg_zoom, login_background_blur = :lbg_blur,
                phone = :phone, email = :email, website = :website, favicon_filename = :favicon_filename
             WHERE id = 1'
        );

        $stmt->execute([
            'name' => $settings->name,
            'brand_name' => $settings->brandName,
            'street' => $settings->street,
            'postal_code' => $settings->postalCode,
            'city' => $settings->city,
            'country' => $settings->country,
            'logo_filename' => $settings->logoFilename,
            'logo_max_height_px' => $settings->logoMaxHeightPx,
            'dark_logo_filename' => $settings->darkLogoFilename,
            'background_image_filename' => $settings->backgroundImageFilename,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'website' => $settings->website,
            'favicon_filename' => $settings->faviconFilename,
            'bg_opacity' => $settings->backgroundStyle->opacity,
            'bg_fit' => $settings->backgroundStyle->fit,
            'bg_x' => $settings->backgroundStyle->positionX,
            'bg_y' => $settings->backgroundStyle->positionY,
            'bg_zoom' => $settings->backgroundStyle->zoom,
            'bg_blur' => $settings->backgroundStyle->blur,
            'login_background_filename' => $settings->loginBackgroundFilename,
            'lbg_opacity' => $settings->loginBackgroundStyle->opacity,
            'lbg_fit' => $settings->loginBackgroundStyle->fit,
            'lbg_x' => $settings->loginBackgroundStyle->positionX,
            'lbg_y' => $settings->loginBackgroundStyle->positionY,
            'lbg_zoom' => $settings->loginBackgroundStyle->zoom,
            'lbg_blur' => $settings->loginBackgroundStyle->blur,
        ]);
    }
}
