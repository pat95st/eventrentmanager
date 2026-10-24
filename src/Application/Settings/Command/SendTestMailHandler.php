<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\MailCategory;
use App\Infrastructure\Mail\MailerException;
use App\Infrastructure\Mail\MailerInterface;

final class SendTestMailHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SendTestMail);

        $toAddress = trim($command->toAddress);
        if ($toAddress === '' || filter_var($toAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['testAddress' => 'Bitte eine gültige E-Mail-Adresse für den Test angeben.']);
        }

        try {
            $this->mailer->send(
                $toAddress,
                'Test-E-Mail von Materialverleih',
                'Diese E-Mail bestätigt, dass die SMTP-Einstellungen korrekt konfiguriert sind.',
                [],
                null,
                MailCategory::TestMail,
            );
        } catch (MailerException $e) {
            throw new ValidationException(['testAddress' => 'Versand fehlgeschlagen: ' . $e->getMessage()]);
        }

        return null;
    }
}
