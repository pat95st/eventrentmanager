<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/** Entfernt ein hochgeladenes Zertifikat wieder — künftige .mobileconfig-Downloads fallen dann auf ein selbstsigniertes Zertifikat zurück (siehe CalDavSigningCertificateProvider). */
final class DeleteCalDavSigningCertificate implements CommandInterface
{
}
