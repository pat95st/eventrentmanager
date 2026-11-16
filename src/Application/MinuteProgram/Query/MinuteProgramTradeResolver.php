<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramItem;
use App\Domain\MinuteProgram\MinuteProgramTemplate;
use App\Domain\MinuteProgram\MinuteProgramTemplateItem;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\Trade;
use App\Domain\Technician\TradeRepositoryInterface;

/**
 * Determines which trades (Gewerke, migration 134) a minute program offers
 * notes for (migration 172):
 *  - explicitly chosen trades (MinuteProgram::$tradeIds), or
 *  - automatically: the trades needed at the event, the trades of its
 *    (not declined) technicians and every trade an item has a note for.
 * Templates without a selection offer all trades.
 *
 * Trades are always returned in the global trade order. Each trade gets a
 * stable color from PALETTE by its position in the global list, so a trade
 * keeps its color across programs and different trades differ.
 */
final class MinuteProgramTradeResolver
{
    /** Readable on dark (FOH) and light backgrounds. */
    public const PALETTE = [
        '#3b82f6', // blue
        '#f59e0b', // amber
        '#a855f7', // purple
        '#22c55e', // green
        '#ec4899', // pink
        '#06b6d4', // cyan
        '#f97316', // orange
        '#84cc16', // lime
        '#f43f5e', // rose
        '#14b8a6', // teal
        '#6366f1', // indigo
        '#eab308', // yellow
    ];

    /** @var list<MinuteProgramTrade>|null */
    private ?array $all = null;

    public function __construct(
        private readonly TradeRepositoryInterface $trades,
        private readonly ?RentalTechnicianRepositoryInterface $technicians = null,
    ) {
    }

    /** @return list<MinuteProgramTrade> all trades with their colors */
    public function all(): array
    {
        if ($this->all === null) {
            $this->all = [];
            foreach (array_values($this->trades->findAll()) as $index => $trade) {
                assert($trade instanceof Trade);
                $this->all[] = new MinuteProgramTrade($trade->id, $trade->name, self::PALETTE[$index % count(self::PALETTE)]);
            }
        }

        return $this->all;
    }

    /** @return list<string> trade IDs needed at the event or served by its technicians */
    public function automaticIdsForRental(string $rentalId): array
    {
        $ids = $this->trades->tradeIdsForRental($rentalId);
        foreach ($this->technicians?->findByRental($rentalId) ?? [] as $technician) {
            if ($technician->status->grantsAccess()) {
                $ids = array_merge($ids, $technician->tradeIds);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param list<MinuteProgramItem> $items
     *
     * @return list<MinuteProgramTrade>
     */
    public function forProgram(MinuteProgram $program, array $items): array
    {
        $ids = $program->tradeIds ?? array_merge($this->automaticIdsForRental($program->rentalId), self::noteTradeIds($items));

        return $this->pick($ids);
    }

    /**
     * @param list<MinuteProgramTemplateItem> $items
     *
     * @return list<MinuteProgramTrade>
     */
    public function forTemplate(MinuteProgramTemplate $template, array $items): array
    {
        return $template->tradeIds === null ? $this->all() : $this->pick($template->tradeIds);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<MinuteProgramTrade>
     */
    public function pick(array $ids): array
    {
        return array_values(array_filter($this->all(), static fn (MinuteProgramTrade $t): bool => in_array($t->id, $ids, true)));
    }

    /**
     * @param list<MinuteProgramItem|MinuteProgramTemplateItem> $items
     *
     * @return list<string>
     */
    private static function noteTradeIds(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $ids = array_merge($ids, array_map('strval', array_keys($item->tradeNotes)));
        }

        return array_values(array_unique($ids));
    }
}
