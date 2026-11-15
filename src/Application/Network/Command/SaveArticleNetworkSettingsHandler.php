<?php

declare(strict_types=1);

namespace App\Application\Network\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Network\NetworkInterfaceResolver;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Network\ArticleNetworkSettings;
use App\Domain\Network\NetworkRepositoryInterface;

final class SaveArticleNetworkSettingsHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly NetworkRepositoryInterface $networks,
        private readonly ArticleRepositoryInterface $articles,
        private readonly NetworkInterfaceResolver $resolver,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveArticleNetworkSettings);
        $article = $this->articles->findById($command->articleId) ?? throw new NotFoundException('Artikel wurde nicht gefunden.');

        // Cables and bundles have no network interface.
        if (!$command->hasNetworkInterface || $article->isBundle || $this->resolver->isCable($article)) {
            $this->networks->deleteArticleSettings($article->id);

            return null;
        }
        $this->networks->saveArticleSettings(new ArticleNetworkSettings($article->id, true, $command->requiresDhcp));

        return null;
    }
}
