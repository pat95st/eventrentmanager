<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerContactRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\OrderConfirmation\OrderConfirmationFreeTextItem;
use App\Domain\OrderConfirmation\OrderConfirmationItem;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationSectionText;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\RichText\HtmlSanitizer;
use App\Shared\Uuid;
use DateTimeImmutable;

final class UpdateOrderConfirmationHandler implements CommandHandlerInterface
{
    use ValidatesOrderConfirmationInput;

    public function __construct(
        private readonly OrderConfirmationRepositoryInterface $orderConfirmations,
        private readonly CustomerRepositoryInterface $customers,
        private readonly VenueRepositoryInterface $venues,
        private readonly ArticleRepositoryInterface $articles,
        private readonly PricingSettingsRepositoryInterface $pricingSettings,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerContactRepositoryInterface $customerContacts,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateOrderConfirmation);

        $orderConfirmation = $this->orderConfirmations->findById($command->id);
        if ($orderConfirmation === null) {
            throw new NotFoundException("Auftragsbestätigung \"{$command->id}\" wurde nicht gefunden.");
        }

        if (!$orderConfirmation->isEditable()) {
            throw new ConflictException('Nur eine Auftragsbestätigung im Status "Entwurf" kann bearbeitet werden.');
        }

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

        $orderConfirmation->customerId = $data['customerId'];
        $orderConfirmation->eventLocation = $data['eventLocation'];
        $orderConfirmation->venueId = $data['venueId'];
        $orderConfirmation->startDate = $data['startDate'];
        $orderConfirmation->endDate = $data['endDate'];
        $orderConfirmation->note = $data['note'];
        $orderConfirmation->accessoryFlatRateEnabled = $command->accessoryFlatRateEnabled;
        $orderConfirmation->accessoryFlatRatePercent = $command->accessoryFlatRateEnabled
            ? $this->pricingSettings->get()->accessoryFlatRatePercent
            : null;
        $orderConfirmation->discountType = $data['discountType'];
        $orderConfirmation->discountValue = $data['discountValue'];
        $orderConfirmation->closingText = $this->htmlSanitizer->sanitize($command->closingText);
        $orderConfirmation->sourceRentalId = $data['rentalId'];
        $orderConfirmation->contactId = $data['contactId'];
        $orderConfirmation->updatedAt = new DateTimeImmutable();

        $this->orderConfirmations->save($orderConfirmation);

        $items = array_map(
            fn (array $item) => new OrderConfirmationItem(
                id: Uuid::v4(),
                orderConfirmationId: $orderConfirmation->id,
                articleId: $item['articleId'],
                quantity: $item['quantity'],
                unitPricePerDay: $item['unitPrice'],
                lineNote: $item['lineNote'],
                sortOrder: $item['sortOrder'],
            ),
            $data['items']
        );
        $this->orderConfirmations->replaceItems($orderConfirmation->id, $items);

        $freeTextItems = array_map(
            fn (array $item) => new OrderConfirmationFreeTextItem(
                id: Uuid::v4(),
                orderConfirmationId: $orderConfirmation->id,
                description: $item['description'],
                quantity: $item['quantity'],
                unitPrice: $item['unitPrice'],
                sortOrder: $item['sortOrder'],
            ),
            $data['freeTextItems']
        );
        $this->orderConfirmations->replaceFreeTextItems($orderConfirmation->id, $freeTextItems);

        $sectionTexts = array_map(
            fn (array $sectionText) => new OrderConfirmationSectionText(
                id: Uuid::v4(),
                orderConfirmationId: $orderConfirmation->id,
                heading: $sectionText['heading'],
                sortOrder: $sectionText['sortOrder'],
            ),
            $data['sectionTexts']
        );
        $this->orderConfirmations->replaceSectionTexts($orderConfirmation->id, $sectionTexts);

        return null;
    }
}
