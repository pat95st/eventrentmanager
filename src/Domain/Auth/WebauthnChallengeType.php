<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum WebauthnChallengeType: string
{
    case Registration = 'registration';
    case Authentication = 'authentication';
}
