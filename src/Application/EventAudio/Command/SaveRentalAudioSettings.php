<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class SaveRentalAudioSettings implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly ?string $playerId,
        public readonly bool $gongByDefault,
        /** @var list<string> */
        public readonly array $ttsLanguages = [],
    ) {
    }
}
