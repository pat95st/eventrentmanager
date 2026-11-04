<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Settings\BackgroundSlot;

/** Hintergrund (Bild oder kurzes Video) für die Oberfläche oder die Anmeldeseite hochladen. */
final class UploadCompanyBackgroundImage implements CommandInterface
{
    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $uploadedFile */
    public function __construct(
        public readonly array $uploadedFile,
        public readonly BackgroundSlot $slot = BackgroundSlot::App,
    ) {
    }
}
