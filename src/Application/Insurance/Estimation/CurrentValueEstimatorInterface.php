<?php

declare(strict_types=1);

namespace App\Application\Insurance\Estimation;

interface CurrentValueEstimatorInterface
{
    /** Ist ein KI-Dienst eingerichtet (API-Schlüssel vorhanden)? */
    public function isAvailable(): bool;

    /** @throws CurrentValueEstimationException */
    public function estimate(CurrentValueEstimationRequest $request): CurrentValueEstimate;
}
