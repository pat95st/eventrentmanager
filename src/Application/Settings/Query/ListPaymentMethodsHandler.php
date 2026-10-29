<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\PaymentMethodRepositoryInterface;

final class ListPaymentMethodsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PaymentMethodRepositoryInterface $paymentMethods)
    {
    }

    /** @return \App\Domain\Settings\PaymentMethod[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListPaymentMethods);

        return $this->paymentMethods->findAll();
    }
}
