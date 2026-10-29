<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Settings\PaymentMethodRepositoryInterface;

/**
 * Löscht eine Zahlungsart. Dokumente, die sie gewählt hatten, fallen per FK
 * (ON DELETE SET NULL) auf die Standard-Zahlungsart zurück; bereits
 * versendete Rechnungen behalten ihr Fälligkeitsdatum. Wird die
 * Standard-Zahlungsart gelöscht, übernimmt die nächste verbleibende.
 */
final class DeletePaymentMethodHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PaymentMethodRepositoryInterface $paymentMethods)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeletePaymentMethod);

        $method = $this->paymentMethods->findById($command->id);
        if ($method === null) {
            throw new NotFoundException("Zahlungsart \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->paymentMethods->delete($command->id);

        if ($method->isDefault) {
            $next = $this->paymentMethods->findAll()[0] ?? null;
            if ($next !== null) {
                $this->paymentMethods->markAsDefault($next->id);
            }
        }

        return null;
    }
}
