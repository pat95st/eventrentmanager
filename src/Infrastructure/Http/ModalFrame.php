<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Create/edit pages ("…/neu", "…/bearbeiten") open in a modal on large
 * screens: public/assets/js/form-modal.js loads the regular page into an
 * <iframe> inside a <dialog>, so every form keeps its own markup and scripts.
 *
 * Requests from that iframe carry the marker parameter "_modal" (in the URL
 * of the first request, as a hidden field in every POST, see
 * form-modal-frame.js). For those requests
 *
 *  - View::page() renders the page without navigation/footer (layout.php,
 *    $modalFrame), and
 *  - Response::redirect() does not let the iframe follow the redirect:
 *    after a successful save it answers with a tiny page that sends the
 *    top-level window to the target instead (flash messages are shown
 *    there, as usual). Only a redirect to another form page stays inside
 *    the modal.
 */
final class ModalFrame
{
    /** Query/form parameter that marks a request as coming from the modal. */
    public const PARAM = '_modal';

    /** Path suffixes of the create/edit pages; keep in sync with form-modal.js. */
    private const FORM_PATH_PATTERN = '~/(neu|neues-bundle|bearbeiten)/?$~';

    public static function isFormPath(string $path): bool
    {
        return preg_match(self::FORM_PATH_PATTERN, $path) === 1;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public static function isFrameRequest(array $query, array $post): bool
    {
        return isset($query[self::PARAM]) || isset($post[self::PARAM]);
    }

    public static function isCurrentRequest(): bool
    {
        return self::isFrameRequest($_GET, $_POST);
    }

    /**
     * True if the current request is a page navigation of the modal's iframe
     * (not a fetch()/XHR call that merely submitted the form fields).
     */
    public static function isCurrentNavigation(): bool
    {
        return self::isCurrentRequest()
            && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === ''
            && str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html');
    }

    /**
     * Where a redirect issued for a modal request has to go: the same
     * location with the marker if it is a form page (stays in the modal),
     * otherwise null (the top-level window has to leave, see breakoutPage()).
     */
    public static function redirectInsideFrame(string $location): ?string
    {
        $path = (string) (parse_url($location, PHP_URL_PATH) ?: '');
        if (!self::isFormPath($path)) {
            return null;
        }

        $fragment = '';
        $hashPos = strpos($location, '#');
        if ($hashPos !== false) {
            $fragment = substr($location, $hashPos);
            $location = substr($location, 0, $hashPos);
        }

        return $location . (str_contains($location, '?') ? '&' : '?') . self::PARAM . '=1' . $fragment;
    }

    /** Minimal page that moves the top-level window to $location. */
    public static function breakoutPage(string $location): string
    {
        $target = json_encode($location, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<!DOCTYPE html><html data-modal-breakout><head><meta charset="utf-8"><title></title></head><body>'
            . '<script>(window.top || window).location.replace(' . $target . ');</script>'
            . '</body></html>';
    }
}
