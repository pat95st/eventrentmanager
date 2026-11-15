<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;

final class RemoveOnlinePaymentSecretHandler implements CommandHandlerInterface
{
    public function __construct(private readonly OnlinePaymentSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RemoveOnlinePaymentSecret);

        $settings = $this->repository->get();
        if ($command->provider === OnlinePaymentProvider::PayPal) {
            $settings->paypalClientSecret = null;
            $settings->paypalEnabled = false;
        } else {
            $settings->sumupApiKey = null;
            $settings->sumupEnabled = false;
        }
        $this->repository->save($settings);

        return null;
    }
}
