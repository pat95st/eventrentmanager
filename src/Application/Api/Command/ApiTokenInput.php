<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

use App\Domain\Api\ApiScope;
use App\Domain\Api\IpAllowlist;
use App\Domain\Exception\ValidationException;

/** Shared validation of the token form fields (create/update). */
final class ApiTokenInput
{
    /**
     * @param list<string> $scopes
     * @return array{name: string, ipAllowlist: ?string, scopes: list<ApiScope>, rateLimit: ?int}
     */
    public static function validate(?string $name, ?string $ipAllowlist, array $scopes, ?string $rateLimit): array
    {
        $errors = [];
        $name = trim((string) $name);
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen für das Token angeben (z. B. "Website-Integration").';
        }

        $ipAllowlist = trim((string) $ipAllowlist);
        if ($ipAllowlist !== '') {
            $error = IpAllowlist::validationError($ipAllowlist);
            if ($error !== null) {
                $errors['ipAllowlist'] = $error;
            }
        }

        $parsedScopes = ApiScope::parseList($scopes);
        if ($parsedScopes === []) {
            $errors['scopes'] = 'Bitte mindestens eine Berechtigung (Scope) auswählen.';
        }

        $rateLimit = trim((string) $rateLimit);
        $limit = null;
        if ($rateLimit !== '') {
            if (preg_match('/^\d{1,7}$/', $rateLimit) !== 1) {
                $errors['rateLimitPerMinute'] = 'Bitte eine ganze Zahl ≥ 0 angeben (leer = globaler Standard, 0 = unbegrenzt).';
            } else {
                $limit = (int) $rateLimit;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return ['name' => $name, 'ipAllowlist' => $ipAllowlist !== '' ? $ipAllowlist : null, 'scopes' => $parsedScopes, 'rateLimit' => $limit];
    }
}
