<?php

declare(strict_types=1);

namespace App\Application\Network\Query;

use App\Application\Network\InventoryNumberNetworkService;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Network\IpAddress;
use App\Domain\Network\NetworkAddressEntry;
use App\Domain\Network\NetworkRepositoryInterface;

/** Answers the network queries (migration 155). */
final class NetworkQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly NetworkRepositoryInterface $networks,
        private readonly CategoryRepositoryInterface $categories,
        private readonly ArticleRepositoryInterface $articles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly InventoryNumberNetworkService $service,
    ) {
    }

    public function handle(QueryInterface $query): mixed
    {
        return match (true) {
            $query instanceof GetNetworkSettings => $this->settings(),
            $query instanceof GetNetworkDetail => $this->detail($query->id),
            $query instanceof GetInventoryNumberNetworkForm => $this->service->form(
                $this->articles->findById($query->articleId) ?? throw new NotFoundException('Artikel wurde nicht gefunden.'),
                $query->inventoryNumberId,
            ),
            $query instanceof GetArticleNetworkOverview => $this->overview($query->articleId),
            default => throw new \LogicException('Unbekannte Abfrage.'),
        };
    }

    private function settings(): NetworkSettings
    {
        $rangeCounts = [];
        foreach ($this->networks->findAllCategoryRanges() as $range) {
            $rangeCounts[$range->networkId] = ($rangeCounts[$range->networkId] ?? 0) + 1;
        }

        return new NetworkSettings($this->networks->findAll(), $this->networks->assignmentCounts(), $rangeCounts);
    }

    private function detail(string $id): NetworkDetail
    {
        $network = $this->networks->findById($id) ?? throw new NotFoundException('Netzwerk wurde nicht gefunden.');

        $paths = $this->categoryPaths();
        $ranges = [];
        $elsewhere = [];
        foreach ($this->networks->findAllCategoryRanges() as $range) {
            if ($range->networkId === $id) {
                $ranges[] = ['range' => $range, 'category' => $paths[$range->categoryId] ?? $range->categoryId];
            } else {
                $elsewhere[$range->categoryId] = true;
            }
        }
        usort($ranges, static fn (array $a, array $b) => strnatcasecmp($a['category'], $b['category']));

        $addresses = $this->networks->findAddressList($id);
        usort($addresses, static function (NetworkAddressEntry $a, NetworkAddressEntry $b): int {
            foreach ([4, 6] as $version) {
                $x = $a->assignment->address($version);
                $y = $b->assignment->address($version);
                if ($x !== null && $y !== null) {
                    return IpAddress::compare((string) inet_pton($x), (string) inet_pton($y));
                }
                if ($x !== null || $y !== null) {
                    return $x !== null ? -1 : 1;
                }
            }

            return strnatcasecmp($a->inventoryNumber, $b->inventoryNumber);
        });

        $options = array_diff_key($paths, $elsewhere);
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return new NetworkDetail($network, $ranges, $addresses, $options);
    }

    private function overview(string $articleId): ArticleNetworkOverview
    {
        $article = $this->articles->findById($articleId) ?? throw new NotFoundException('Artikel wurde nicht gefunden.');
        $resolver = $this->service->resolver();
        $ids = array_map(static fn ($inventoryNumber) => $inventoryNumber->id, $this->inventoryNumbers->findByArticle($articleId));
        $networks = [];
        foreach ($this->networks->findAll() as $network) {
            $networks[$network->id] = $network;
        }

        return new ArticleNetworkOverview(
            $this->networks->findArticleSettings($articleId),
            $resolver->slots($article),
            $this->networks->findAssignmentsByInventoryNumbers($ids),
            $networks,
            $resolver->isCable($article),
        );
    }

    /** @return array<string, string> category ID => "Parent › Child" */
    private function categoryPaths(): array
    {
        $byId = [];
        foreach ($this->categories->findAll() as $category) {
            $byId[$category->id] = $category;
        }
        $paths = [];
        foreach ($byId as $id => $category) {
            $names = [];
            $current = $category;
            $guard = 0;
            while ($current !== null && $guard++ < 20) {
                array_unshift($names, $current->name);
                $current = $current->parentId !== null ? ($byId[$current->parentId] ?? null) : null;
            }
            $paths[$id] = implode(' › ', $names);
        }

        return $paths;
    }
}
