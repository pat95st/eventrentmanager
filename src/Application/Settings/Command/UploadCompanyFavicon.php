<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/** Eigenes Favicon hochladen (Migration 112), $uploadedFile wie aus $_FILES. */
final class UploadCompanyFavicon implements CommandInterface
{
    public function __construct(public readonly array $uploadedFile)
    {
    }
}
