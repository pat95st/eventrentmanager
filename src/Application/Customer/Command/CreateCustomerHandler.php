<?php

declare(strict_types=1);

namespace App\Application\Customer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Numbering\NumberedDocumentType;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;
use Throwable;

final class CreateCustomerHandler implements CommandHandlerInterface
{
    use ValidatesCustomerInput;

    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly DocumentNumberSequence $numberSequence,
        private readonly PDO $pdo,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateCustomer);

        [$errors, $data] = $this->validateCustomerInput(
            $command->type,
            $command->companyName,
            $command->firstName,
            $command->lastName,
            $command->street,
            $command->addressLine2,
            $command->postalCode,
            $command->city,
            $command->phone,
            $command->email,
            $command->telegramChatId,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();

        // Nummernvergabe UND das Speichern des Kunden laufen in derselben
        // Transaktion (siehe DocumentNumberSequence-Klassen-Doc): schlägt
        // das Speichern fehl, wird auch die Nummernvergabe zurückgerollt —
        // identisches Muster wie bei CreateOfferHandler (Angebotsnummer,
        // ebenfalls sofort bei Anlage vergeben statt wie bei Invoice erst
        // beim Versenden).
        $this->pdo->beginTransaction();

        try {
            $customerNumber = $this->numberSequence->allocateNext(NumberedDocumentType::Customer);

            $customer = new Customer(
                id: Uuid::v4(),
                customerNumber: $customerNumber,
                type: $data['type'],
                companyName: $data['companyName'],
                firstName: $data['firstName'],
                lastName: $data['lastName'],
                street: $data['street'],
                addressLine2: $data['addressLine2'],
                postalCode: $data['postalCode'],
                city: $data['city'],
                phone: $data['phone'],
                email: $data['email'],
                telegramChatId: $data['telegramChatId'],
                createdAt: $now,
                updatedAt: $now,
            );

            $this->customers->save($customer);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        return $customer->id;
    }
}
