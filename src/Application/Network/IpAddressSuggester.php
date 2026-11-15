<?php

declare(strict_types=1);

namespace App\Application\Network;

use App\Domain\Network\IpAddress;

/**
 * Suggests free addresses (migration 155): the last address used in the
 * range plus one, then onwards; when the end of the range is reached, the
 * search continues at its start (gaps left by deleted devices).
 */
final class IpAddressSuggester
{
    private const MAX_STEPS = 70000;

    /**
     * @param array{0: string, 1: string} $range packed first and last address
     * @param string[] $used canonical addresses already in use
     * @return string[] up to $count free addresses (canonical text)
     */
    public static function suggest(array $range, int $version, array $used, int $count = 1): array
    {
        [$first, $last] = $range;
        $taken = [];
        $highest = null;
        foreach ($used as $address) {
            $packed = IpAddress::parse($address, $version);
            if ($packed === null) {
                continue;
            }
            $taken[$packed] = true;
            if (
                IpAddress::compare($packed, $first) >= 0 && IpAddress::compare($packed, $last) <= 0
                && ($highest === null || IpAddress::compare($packed, $highest) > 0)
            ) {
                $highest = $packed;
            }
        }

        $start = $highest !== null ? IpAddress::increment($highest) : $first;
        if ($start === null || IpAddress::compare($start, $last) > 0) {
            $start = $first;
        }

        $result = [];
        $current = $start;
        $wrapped = false;
        for ($step = 0; $step < self::MAX_STEPS && count($result) < $count; $step++) {
            if (!isset($taken[$current])) {
                $result[] = IpAddress::format($current);
                $taken[$current] = true;
            }
            $next = IpAddress::increment($current);
            if ($next === null || IpAddress::compare($next, $last) > 0) {
                if ($wrapped || $start === $first) {
                    break;
                }
                $wrapped = true;
                $next = $first;
            }
            if ($wrapped && $next === $start) {
                break;
            }
            $current = $next;
        }

        return $result;
    }
}
