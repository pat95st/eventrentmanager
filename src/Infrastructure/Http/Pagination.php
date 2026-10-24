<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\User\User;

/**
 * Kleiner Helfer, der die Paginierungsparameter ("page"/"pageSize") aus dem
 * Request liest, mit dem gespeicherten Standard des angemeldeten Benutzers
 * (User::$defaultPageSize) verrechnet und die zulässigen Auswahlwerte für
 * das <select> auf der Profil-Seite bereitstellt (siehe
 * resources/views/profile/display.php).
 *
 * Wird von jedem Listen-Controller (Artikel, Veranstaltungen, Kunden, ...)
 * einheitlich verwendet, damit "?pageSize=" überall gleich funktioniert und
 * nur der gespeicherte Profil-Wert dauerhaft übernommen wird — ein reiner
 * Query-Parameter verändert nie das gespeicherte Benutzer-Setting.
 */
final class Pagination
{
    public const DEFAULT_PAGE_SIZE = 25;

    /** @var int[] Auswahlwerte für den "Einträge pro Seite"-Selector auf der Profilseite. */
    public const ALLOWED_PAGE_SIZES = [10, 25, 50, 100];

    /** Obergrenze für eine per "?pageSize=" angeforderte, nicht gespeicherte Seitengröße. */
    private const MAX_PAGE_SIZE = 500;

    public static function page(Request $request): int
    {
        $raw = $request->query('page');
        if ($raw === null || $raw === '') {
            return 1;
        }

        $page = (int) $raw;

        return $page < 1 ? 1 : $page;
    }

    /**
     * Ermittelt die Seitengröße: ein expliziter "?pageSize="-Parameter hat
     * Vorrang (temporäre Ansicht, wird nicht gespeichert), sonst der
     * gespeicherte Benutzer-Standard, sonst der feste Fallback.
     */
    public static function pageSize(Request $request, ?User $user): int
    {
        $raw = $request->query('pageSize');
        if ($raw !== null && $raw !== '') {
            $size = (int) $raw;
            if ($size > 0) {
                return min($size, self::MAX_PAGE_SIZE);
            }
        }

        return $user?->defaultPageSize ?? self::DEFAULT_PAGE_SIZE;
    }

    /**
     * Erkennt eine per fetch() ausgelöste Anfrage: der "X-Requested-With"-
     * Header (bereits Konvention der Passkey-Funktionalität, siehe
     * resources/views/profile/security.php) oder alternativ "?ajax=1", für
     * Fälle in denen ein Link statt fetch() genutzt würde.
     */
    public static function isAjax(Request $request): bool
    {
        return $request->ajax || $request->query('ajax') === '1';
    }

    public static function isValidPageSize(int $pageSize): bool
    {
        return in_array($pageSize, self::ALLOWED_PAGE_SIZES, true);
    }
}
