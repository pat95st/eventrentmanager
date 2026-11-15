<?php

declare(strict_types=1);

namespace App\Application\Network\Query;

use App\Domain\Category\Category;
use App\Domain\Network\Network;
use App\Domain\Network\NetworkAddressEntry;
use App\Domain\Network\NetworkCategoryRange;

final class NetworkDetail
{
    /**
     * @param array<int, array{range: NetworkCategoryRange, category: string}> $ranges sorted by category name
     * @param NetworkAddressEntry[] $addresses sorted by address
     * @param array<string, string> $categoryOptions category ID => path label (without a range in another network)
     */
    public function __construct(
        public readonly Network $network,
        public readonly array $ranges,
        public readonly array $addresses,
        public readonly array $categoryOptions,
    ) {
    }
}
