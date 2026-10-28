<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerContactRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Numbering\NumberedDocumentType;
use App\Domain\OrderConfirmation\OrderConfirmation;
use App\Domain\OrderConfirmation\OrderConfirmationFreeTextItem;
use App\Domain\OrderConfirmation\OrderConfirmationItem;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationSectionText;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Infrastructure\RichText\HtmlSanitizer;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;
use Throwable;

final class CreateOrderConfirmationHandler implements CommandHandlerInterface
{
    use ValidatesOrderConfirmationInput;

    public function __construct(
        private readonly OrderConfirmationRepositoryInterface $orderConfirmations,
        private readonly CustomerRepositoryInterface $customers,
        private readonly VenueRepositoryInterface $venues,
        private readonly ArticleRepositoryInterface $articles,
        private readonly PricingSettingsRepositoryInterface $pricingSettings,
        private readonly DocumentNumberSequence $numberSequence,
        private readonly PDO $pdo,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerContactRepositoryInterface $customerContacts,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateOrderConfirmation);

        [$errors, $data] = $this->validateOrderConfirmationInput(
            $this->customers,
            $this->venues,
            $this->articles,
            $command->customerId,
            $command->startDate,
            $command->endDate,
            $command->eventLocation,
            $command->note,
            $command->items,
            $command->freeTextItems,
            $command->sectionTexts,
            $command->discountType,
            $command->discountValue,
            $this->rentals,
            $command->rentalId,
            $this->customerContacts,
            $command->contactId,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $accessoryFlatRatePercent = $command->accessoryFlatRateEnabled
            ? $this->pricingSettings->get()->accessoryFlatRatePercent
            : null;

        $now = new DateTimeImmutable();

        $this->pdo->beginTransaction();

        try {
            $number = $this->numberSequence->allocateNext(NumberedDocumentType::OrderConfirmation);

            $orderConfirmation = new OrderConfirmation(
                id: Uuid::v4(),
                orderConfirmationNumber: $number,
                customerId: $data['customerId'],
                eventLocation: $data['eventLocation'],
                venueId: $data['venueId'],
                startDate: $data['startDate'],
                endDate: $data['endDate'],
                status: OrderConfirmationStatus::Draft,
                note: $data['note'],
                sourceOfferId: null,
                createdAt: $now,
                updatedAt: $now,
                accessoryFlatRateEnabled: $command->accessoryFlatRateEnabled,
                accessoryFlatRatePercent: $accessoryFlatRatePercent,
                discountType: $data['discountType'],
                discountValue: $data['discountValue'],
                closingText: $this->htmlSanitizer->sanitize($command->closingText),
                sourceRentalId: $data['rentalId'],
                contactId: $data['contactId'],
            );

            $this->orderConfirmations->save($orderConfirmation);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        $this->saveRows($orderConfirmation->id, $data);

        return $orderConfirmation->id;
    }

    /** @param array{items: array<int, array{articleId: string, quantity: int, unitPrice: string, lineNote: ?string, sortOrder: int}>, freeTextItems: array<int, array{description: string, quantity: string, unitPrice: string, sortOrder: int}>, sectionTexts: array<int, array{heading: string, sortOrder: int}>} $data */
    private function saveRows(string $orderConfirmationId, array $data): void
    {
        $items = array_map(
            fn (array $item) => new OrderConfirmationItem(
                id: Uuid::v4(),
                orderConfirmationId: $orderConfirmationId,
                articleId: $item['articleId'],
                quantity: $item['quantity'],
                unitPricePerDay: $item['unitPrice'],
                lineNote: $item['lineNote'],
                sortOrder: $item['sortOrder'],
            ),
            $data['items']
        );
        $this->orderConfirmations->replaceItems($orderConfirmationId, $items);

        $freeTextItems = array_map(
            fn (array $item) => new OrderConfirmationFreeTextItem(
                id: Uuid::v4(),
                orderConfirmationId: $orderConfirmationId,
                description: $item['description'],
                quantity: $item['quantity'],
                unitPrice: $item['unitPrice'],
                sortOrder: $item['sortOrder'],
            ),
            $data['freeTextItems']
        );
        $this->orderConfirmations->replaceFreeTextItems($orderConfirmationId, $freeTextItems);

        $sectionTexts = array_map(
            fn (array $sectionText) => new OrderConfirmationSectionText(
                id: Uuid::v4(),
                orderConfirmationId: $orderConfirmationId,
                heading: $sectionText['heading'],
                sortOrder: $sectionText['sortOrder'],
            ),
            $data['sectionTexts']
        );
        $this->orderConfirmations->replaceSectionTexts($orderConfirmationId, $sectionTexts);
    }
}
