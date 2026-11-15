<?php

declare(strict_types=1);

namespace App\Application\MixingConsole\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\DigitalStagebox\DigitalStageboxRepositoryInterface;
use App\Domain\MixingConsole\MixingConsoleRepositoryInterface;

/** Answers GetArticleMixingConsole, GetMixingConsoleFormData and GetCompatibleConsoles (migration 153). */
final class MixingConsoleQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly MixingConsoleRepositoryInterface $consoles,
        private readonly DigitalStageboxRepositoryInterface $stageboxes,
    ) {
    }

    public function handle(QueryInterface $query): mixed
    {
        if ($query instanceof GetArticleMixingConsole) {
            $console = $this->consoles->findByArticle($query->articleId);
            if ($console === null) {
                return null;
            }
            $protocols = [];
            foreach ($this->stageboxes->findAllProtocols() as $protocol) {
                $protocols[$protocol->id] = $protocol;
            }
            $options = [];
            if ($console->stageboxes !== []) {
                foreach ($this->consoles->findStageboxOptions() as $option) {
                    $options[$option->articleId] = $option;
                }
            }

            return new ArticleMixingConsoleDetail($console, $protocols, $options);
        }
        if ($query instanceof GetCompatibleConsoles) {
            return $this->consoles->findConsolesForStagebox($query->stageboxArticleId);
        }
        assert($query instanceof GetMixingConsoleFormData);

        return new MixingConsoleFormData($this->stageboxes->findAllProtocols(), $this->consoles->findStageboxOptions());
    }
}
