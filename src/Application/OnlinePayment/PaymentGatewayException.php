<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

use RuntimeException;

/** The provider rejected a request or could not be reached. The message is meant for the log / the administrator. */
final class PaymentGatewayException extends RuntimeException
{
}
