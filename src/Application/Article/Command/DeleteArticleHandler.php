<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Article\Channel\ArticleChannelPublisherRegistry;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleChannelListingRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\CaseContent\CaseContentItemRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteArticleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly LocalFileStorage $fileStorage,
        private readonly CaseContentItemRepositoryInterface $caseContentItems,
        private readonly RentalItemRepositoryInterface $rentalItems,
        private readonly ArticleChannelListingRepositoryInterface $channelListings,
        private readonly ArticleChannelPublisherRegistry $channelPublishers,
        private readonly ?\App\Domain\Article\ArticleBundleComponentRepositoryInterface $bundleComponents = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteArticle);

        $article = $this->articles->findById($command->id);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->caseContentItems->isUsedByArticle($command->id)) {
            throw new ConflictException('Dieser Artikel ist noch in einem Case gepackt. Bitte zuerst aus dem Case entfernen.');
        }

        if ($this->rentalItems->isUsedByArticle($command->id)) {
            throw new ConflictException('Dieser Artikel ist noch in einer Vermietung eingeplant. Bitte zuerst aus der Vermietung entfernen.');
        }

        // Bundles (Migration 107/111): als Bestandteil oder Alternative verwendet.
        if ($this->bundleComponents !== null
            && ($this->bundleComponents->findBundleIdsContaining($command->id) !== [] || $this->bundleComponents->findBundleIdsWithAlternative($command->id) !== [])) {
            throw new ConflictException('Dieser Artikel ist Bestandteil oder Alternative in einem Bundle. Bitte ihn zuerst dort entfernen.');
        }

        // Inventurnummern, RMAs, RMA-Dokumente und article_channel_listings hängen
        // per FK ON DELETE CASCADE an articles/inventory_numbers/rmas und werden von
        // der Datenbank entfernt. Die physischen Artikelbild-Dateien müssen wir
        // selbst aufräumen — und, damit auf Google/WhatsApp kein verwaister Eintrag
        // für einen nicht mehr existierenden Artikel stehen bleibt, auch die extern
        // übermittelten Kanal-Einträge selbst abmelden (best effort, siehe
        // ArticleChannelPublisherInterface::unpublish()-Docblock).
        foreach ($this->channelListings->findByArticle($article->id) as $listing) {
            if ($listing->enabled) {
                $this->channelPublishers->for($listing->channel)->unpublish($listing);
            }
        }

        foreach ($article->images as $image) {
            $this->fileStorage->delete('articles', $image->filePath);
        }

        $this->articles->delete($command->id);

        return null;
    }
}
