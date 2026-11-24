<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use App\Domain\Api\ApiScope as S;
use App\Domain\Technician\TechnicianAccess;
use App\Domain\User\Permission;
use App\Domain\User\User;

/**
 * Which API scopes a user may grant to an OAuth client: derived from the
 * user's permissions, mirroring which areas the web interface opens to them.
 * The special scopes invoices:finalize and email:send are never granted via
 * OAuth (the MCP server does not use them anyway).
 *
 * Users who only have the "Techniker" permission are excluded: they see only
 * the events they are assigned to, which API scopes cannot express.
 *
 * Checked when consenting and again on every request (ApiKernel token
 * guard), so withdrawn permissions take effect immediately.
 */
final class OAuthScopePolicy
{
    /** @return array<string, list<S>> Permission value => scopes */
    public static function map(): array
    {
        return [
            Permission::Kundenverwaltung->value => [S::CustomersRead, S::CustomersWrite],
            Permission::Artikelverwaltung->value => [
                S::ArticlesRead, S::ArticlesWrite, S::MasterDataRead, S::MasterDataWrite,
                S::InventoryRead, S::InventoryWrite, S::FinanceRead, S::FinanceWrite,
            ],
            Permission::AusgabeRuecknahme->value => [
                S::RentalsRead, S::RentalsWrite, S::SalesRead, S::SalesWrite, S::InvoicesRead, S::InvoicesWrite,
                S::CustomersRead, S::ArticlesRead, S::MasterDataRead,
            ],
            Permission::Minutenprogramme->value => [S::MinuteProgramsRead],
            Permission::Elektropruefung->value => [S::InventoryRead, S::InventoryWrite, S::ArticlesRead],
        ];
    }

    /** @return list<S> in the order of ApiScope::cases() */
    public static function allowedScopes(User $user): array
    {
        if (!$user->isActive || TechnicianAccess::isRestricted($user)) {
            return [];
        }
        if ($user->isAdministrator()) {
            return array_values(array_filter(S::cases(), static fn (S $s) => !in_array($s, [S::InvoicesFinalize, S::EmailSend], true)));
        }
        $scopes = [];
        foreach (self::map() as $permission => $granted) {
            if ($user->has(Permission::from($permission))) {
                $scopes = array_merge($scopes, $granted);
            }
        }

        return S::parseList(array_map(static fn (S $s) => $s->value, $scopes));
    }

    public static function mayConnect(User $user): bool
    {
        return self::allowedScopes($user) !== [];
    }
}
