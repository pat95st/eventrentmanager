<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class RequestPlayback implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $kind,
        public readonly ?string $audioFileId,
        public readonly ?string $text,
        public readonly ?array $recordingFile,
        public readonly bool $withGong,
        public readonly string $requestedByType,
        public readonly ?string $requestedById,
        public readonly string $requestedByName,
    ) {
    }
}
