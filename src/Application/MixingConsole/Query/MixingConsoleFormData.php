<?php

declare(strict_types=1);

namespace App\Application\MixingConsole\Query;

use App\Domain\DigitalStagebox\DigitalProtocol;
use App\Domain\MixingConsole\StageboxOption;

final class MixingConsoleFormData
{
    /**
     * @param DigitalProtocol[] $protocols
     * @param StageboxOption[] $stageboxes
     */
    public function __construct(
        public readonly array $protocols,
        public readonly array $stageboxes,
    ) {
    }

    public function protocolName(string $id): string
    {
        foreach ($this->protocols as $protocol) {
            if ($protocol->id === $id) {
                return $protocol->name;
            }
        }

        return '–';
    }
}
