<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/** Schriftfamilie von Google Fonts laden und für PDFs bereitstellen (Migration 116). */
final class AddGoogleFont implements CommandInterface
{
    public function __construct(public readonly string $family)
    {
    }
}
