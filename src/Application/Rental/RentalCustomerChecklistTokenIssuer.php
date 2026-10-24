<?php

declare(strict_types=1);

namespace App\Application\Rental;

use App\Domain\Rental\RentalCustomerChecklistToken;
use App\Domain\Rental\RentalCustomerChecklistTokenRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Gemeinsame Logik zum Ausstellen (bzw. Wiederverwenden) des
 * Kunden-Checklisten-Links einer Vermietung — genutzt sowohl vom manuellen
 * "Link erzeugen/erneuern" auf der Veranstaltungs-Detailseite
 * (IssueRentalCustomerChecklistTokenHandler) als auch vom automatischen
 * Mailversand (EmailRentalCustomerChecklistHandler, inkl.
 * bin/send-rental-reminders.php) — analog App\Application\Auth\
 * PasswordSetupTokenIssuer als schlanker, nicht über den CommandBus
 * geführter Application-Service.
 */
final class RentalCustomerChecklistTokenIssuer
{
    private const TOKEN_BYTES = 32;

    public function __construct(private readonly RentalCustomerChecklistTokenRepositoryInterface $tokens)
    {
    }

    /**
     * @param bool $forceNew true = immer einen neuen Token erzeugen (macht einen
     *        bereits an den Kunden herausgegebenen Link ungültig, siehe
     *        "Neuen Link erzeugen" in der Oberfläche). false (Standard) =
     *        einen bereits bestehenden Token unverändert zurückgeben, damit
     *        ein bereits verschickter/geöffneter Link gültig bleibt.
     */
    public function issue(string $rentalId, bool $forceNew = false): string
    {
        if (!$forceNew) {
            $existing = $this->tokens->findByRentalId($rentalId);
            if ($existing !== null) {
                return $existing->token;
            }
        }

        $token = new RentalCustomerChecklistToken(
            id: Uuid::v4(),
            rentalId: $rentalId,
            token: bin2hex(random_bytes(self::TOKEN_BYTES)),
            createdAt: new DateTimeImmutable(),
        );

        $this->tokens->save($token);

        return $token->token;
    }
}
