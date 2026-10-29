<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\BankAccountRepositoryInterface;

final class ListBankAccountsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly BankAccountRepositoryInterface $bankAccounts)
    {
    }

    /** @return \App\Domain\Settings\BankAccount[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListBankAccounts);

        return $this->bankAccounts->findAll();
    }
}
