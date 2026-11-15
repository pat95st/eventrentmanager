<?php

declare(strict_types=1);

namespace App\Application\Network;

use App\Domain\Article\Article;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\DigitalStagebox\DigitalProtocol;
use App\Domain\DigitalStagebox\DigitalStageboxRepositoryInterface;
use App\Domain\MixingConsole\MixingConsoleRepositoryInterface;
use App\Domain\Network\NetworkCategoryRange;
use App\Domain\Network\NetworkRepositoryInterface;

/** Determines which interfaces of an article get addresses (migration 155). */
final class NetworkInterfaceResolver
{
    public function __construct(
        private readonly NetworkRepositoryInterface $networks,
        private readonly CategoryRepositoryInterface $categories,
        private readonly ?DigitalStageboxRepositoryInterface $stageboxes = null,
        private readonly ?MixingConsoleRepositoryInterface $consoles = null,
    ) {
    }

    public function isCable(Article $article): bool
    {
        $cableCategoryId = $this->categories->findCableCategoryId();

        return $cableCategoryId !== null && $article->categoryId === $cableCategoryId;
    }

    /** @return NetworkInterfaceSlot[] */
    public function slots(Article $article): array
    {
        if ($article->isBundle || $this->isCable($article)) {
            return [];
        }

        $slots = [];
        $settings = $this->networks->findArticleSettings($article->id);
        if ($settings !== null && $settings->hasNetworkInterface) {
            $slots[] = new NetworkInterfaceSlot('main', 'Netzwerkschnittstelle', NetworkInterfaceSlot::KIND_MAIN);
        }

        if ($this->stageboxes === null) {
            return $slots;
        }
        $protocols = [];
        foreach ($this->stageboxes->findAllProtocols() as $protocol) {
            $protocols[$protocol->id] = $protocol;
        }
        $ipProtocol = static fn (?string $id): ?DigitalProtocol => $id !== null && isset($protocols[$id]) && $protocols[$id]->isIpBased ? $protocols[$id] : null;

        $stagebox = $this->stageboxes->findByArticle($article->id);
        if ($stagebox !== null && ($protocol = $ipProtocol($stagebox->protocolId)) !== null) {
            $slots[] = new NetworkInterfaceSlot('stagebox', $protocol->name . '-Schnittstelle', NetworkInterfaceSlot::KIND_PROTOCOL);
        }

        $console = $this->consoles?->findByArticle($article->id);
        if ($console !== null && $console->isDigital()) {
            foreach ($console->interfaces as $position => $interface) {
                $protocol = $ipProtocol($interface->protocolId);
                if ($protocol === null) {
                    continue;
                }
                for ($n = 1; $n <= max(1, $interface->quantity); $n++) {
                    $slots[] = new NetworkInterfaceSlot(
                        'console-if-' . ($position + 1) . '-' . $n,
                        $protocol->name . '-Schnittstelle' . ($interface->quantity > 1 ? ' ' . $n : ''),
                        NetworkInterfaceSlot::KIND_PROTOCOL,
                    );
                }
            }
            foreach ($console->cards as $position => $card) {
                $protocol = $ipProtocol($card->protocolId);
                if ($protocol !== null) {
                    $slots[] = new NetworkInterfaceSlot(
                        'console-card-' . ($position + 1),
                        'Erweiterungskarte „' . $card->name . '“ (' . $protocol->name . ')',
                        NetworkInterfaceSlot::KIND_PROTOCOL,
                        optional: true,
                    );
                }
            }
        }

        return $slots;
    }

    public function requiresDhcp(Article $article): bool
    {
        $settings = $this->networks->findArticleSettings($article->id);

        return $settings !== null && $settings->hasNetworkInterface && $settings->requiresDhcp;
    }

    /** Range of the article's category or, failing that, of its nearest parent category. */
    public function categoryRange(Article $article): ?NetworkCategoryRange
    {
        $categoryId = $article->categoryId;
        $visited = [];
        while ($categoryId !== null && !isset($visited[$categoryId])) {
            $visited[$categoryId] = true;
            $range = $this->networks->findCategoryRange($categoryId);
            if ($range !== null) {
                return $range;
            }
            $categoryId = $this->categories->findById($categoryId)?->parentId;
        }

        return null;
    }
}
