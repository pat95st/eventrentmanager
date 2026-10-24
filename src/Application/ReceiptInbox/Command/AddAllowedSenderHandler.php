<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\ReceiptInbox\ReceiptInboxAllowedSender;
use App\Domain\ReceiptInbox\ReceiptInboxAllowedSenderRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddAllowedSenderHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ReceiptInboxAllowedSenderRepositoryInterface $senders)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddAllowedSender);

        $email = strtolower(trim($command->email));
        $errors = [];

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        } elseif ($this->senders->findByEmail($email) !== null) {
            $errors['email'] = 'Diese Absenderadresse ist bereits auf der Liste.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $note = trim($command->note);

        $sender = new ReceiptInboxAllowedSender(
            id: Uuid::v4(),
            email: $email,
            note: $note !== '' ? $note : null,
            createdAt: new DateTimeImmutable(),
        );

        $this->senders->save($sender);

        return $sender->id;
    }
}
