<?php

declare(strict_types=1);

namespace App\Application\Network;

use App\Domain\Article\Article;
use App\Domain\Exception\ValidationException;
use App\Domain\Network\IpAddress;
use App\Domain\Network\MacAddress;
use App\Domain\Network\Network;
use App\Domain\Network\NetworkInterfaceAssignment;
use App\Domain\Network\NetworkRepositoryInterface;

/**
 * IP and MAC addresses of inventory numbers (migration 155): builds the
 * form with suggested addresses, validates the input and saves it.
 *
 * Suggestion: the network of the category range (or the only network),
 * last address used in the range (category range, otherwise the whole
 * subnet) plus one. Any other free address inside the subnet of the
 * chosen network may be entered instead.
 */
final class InventoryNumberNetworkService
{
    public function __construct(
        private readonly NetworkRepositoryInterface $networks,
        private readonly NetworkInterfaceResolver $resolver,
    ) {
    }

    public function resolver(): NetworkInterfaceResolver
    {
        return $this->resolver;
    }

    /** Form for a new inventory number (inventoryNumberId null) or an existing one. */
    public function form(Article $article, ?string $inventoryNumberId = null): NetworkInterfaceForm
    {
        $slots = $this->resolver->slots($article);
        $existing = [];
        if ($inventoryNumberId !== null) {
            foreach ($this->networks->findAssignmentsByInventoryNumber($inventoryNumberId) as $assignment) {
                $existing[$assignment->interfaceKey] = $assignment;
            }
        }
        $slots = $this->withOrphans($slots, $existing);
        $networks = $this->networks->findAll();
        $categoryRange = $this->resolver->categoryRange($article);
        if ($slots === []) {
            return new NetworkInterfaceForm([], $networks, [], false, $categoryRange);
        }

        $candidateCount = 2 * count($slots) + 2;
        $candidates = [];
        foreach ($networks as $network) {
            foreach ([4, 6] as $version) {
                $candidates[$network->id][$version] = [];
                $range = $this->suggestionRange($network, $version, $categoryRange);
                if ($range !== null) {
                    $candidates[$network->id][$version] = IpAddressSuggester::suggest(
                        $range,
                        $version,
                        $this->networks->usedAddresses($network->id, $version, $inventoryNumberId),
                        $candidateCount,
                    );
                }
            }
        }

        $defaultNetworkId = $categoryRange?->networkId;
        if ($defaultNetworkId === null && count($networks) === 1) {
            $defaultNetworkId = $networks[0]->id;
        }

        // Addresses already present in the form must not be suggested again.
        $inForm = [];
        foreach ($existing as $assignment) {
            foreach ([4, 6] as $version) {
                if ($assignment->networkId !== null && $assignment->address($version) !== null) {
                    $inForm[$assignment->networkId][$version][] = $assignment->address($version);
                }
            }
        }

        $rows = [];
        foreach ($slots as $slot) {
            $assignment = $existing[$slot->key] ?? null;
            if ($assignment !== null || $slot->kind === NetworkInterfaceSlot::KIND_ORPHAN) {
                $rows[] = new NetworkInterfaceFormRow($slot, $assignment?->networkId, $assignment?->ipv4Address, $assignment?->ipv6Address, $assignment?->macAddress, false);
                continue;
            }
            $network = $defaultNetworkId !== null ? $this->findIn($networks, $defaultNetworkId) : null;
            $suggestions = [4 => null, 6 => null];
            if ($network !== null && !$slot->optional) {
                foreach ([4, 6] as $version) {
                    foreach ($candidates[$network->id][$version] ?? [] as $candidate) {
                        if (!in_array($candidate, $inForm[$network->id][$version] ?? [], true)) {
                            $suggestions[$version] = $candidate;
                            $inForm[$network->id][$version][] = $candidate;
                            break;
                        }
                    }
                }
            }
            $rows[] = new NetworkInterfaceFormRow($slot, $slot->optional ? null : $network?->id, $suggestions[4], $suggestions[6], null, true);
        }

        return new NetworkInterfaceForm($rows, $networks, $candidates, $this->resolver->requiresDhcp($article), $categoryRange);
    }

    /**
     * Validates the submitted rows (network[<key>][network_id|ipv4|ipv6|mac]).
     *
     * @param array<string, array<string, mixed>> $input
     * @return NetworkInterfaceAssignment[] assignments with empty inventory number ID (see save())
     * @throws ValidationException
     */
    public function validate(Article $article, ?string $inventoryNumberId, array $input): array
    {
        $existing = [];
        if ($inventoryNumberId !== null) {
            foreach ($this->networks->findAssignmentsByInventoryNumber($inventoryNumberId) as $assignment) {
                $existing[$assignment->interfaceKey] = $assignment;
            }
        }
        $slots = $this->withOrphans($this->resolver->slots($article), $existing);

        $errors = [];
        $assignments = [];
        $seenAddresses = [];
        $seenMacs = [];
        foreach ($slots as $slot) {
            $row = is_array($input[$slot->key] ?? null) ? $input[$slot->key] : [];
            $text = static fn (string $field): ?string => is_string($row[$field] ?? null) && trim($row[$field]) !== '' ? trim($row[$field]) : null;
            $networkId = $text('network_id');
            $ipv4 = $text('ipv4');
            $ipv6 = $text('ipv6');
            $macRaw = $text('mac');
            $label = $slot->label;

            $network = null;
            if ($networkId !== null) {
                $network = $this->networks->findById($networkId);
                if ($network === null) {
                    $errors[] = "{$label}: Bitte ein gültiges Netzwerk auswählen.";
                    continue;
                }
            } elseif ($ipv4 !== null || $ipv6 !== null) {
                $errors[] = "{$label}: Bitte ein Netzwerk für die IP-Adresse auswählen.";
                continue;
            }

            $addresses = [4 => null, 6 => null];
            foreach ([4 => $ipv4, 6 => $ipv6] as $version => $raw) {
                if ($raw === null || $network === null) {
                    continue;
                }
                $versionLabel = 'IPv' . $version;
                if (!$network->supports($version)) {
                    $errors[] = "{$label}: Das Netzwerk „{$network->name}“ hat kein {$versionLabel}.";
                    continue;
                }
                $address = IpAddress::normalize($raw, $version);
                if ($address === null) {
                    $errors[] = "{$label}: „{$raw}“ ist keine gültige {$versionLabel}-Adresse.";
                    continue;
                }
                if (!$network->isAssignable($address, $version)) {
                    $errors[] = "{$label}: {$address} liegt nicht im nutzbaren Bereich von {$network->cidr($version)} (Netzwerk „{$network->name}“).";
                    continue;
                }
                $seenKey = $network->id . '|' . $address;
                if (isset($seenAddresses[$seenKey])) {
                    $errors[] = "{$label}: {$address} ist bereits bei „{$seenAddresses[$seenKey]}“ eingetragen.";
                    continue;
                }
                $owner = $this->networks->findAddressOwner($network->id, $version, $address, $inventoryNumberId);
                if ($owner !== null) {
                    $errors[] = "{$label}: {$address} wird bereits von Inventarnummer {$owner} verwendet.";
                    continue;
                }
                $seenAddresses[$seenKey] = $label;
                $addresses[$version] = $address;
            }

            $mac = null;
            if ($macRaw !== null) {
                $mac = MacAddress::normalize($macRaw);
                if ($mac === null) {
                    $errors[] = "{$label}: „{$macRaw}“ ist keine gültige MAC-Adresse (z. B. 00:1D:C1:12:34:56).";
                } elseif (isset($seenMacs[$mac])) {
                    $errors[] = "{$label}: Die MAC-Adresse {$mac} ist bereits bei „{$seenMacs[$mac]}“ eingetragen.";
                    $mac = null;
                } elseif (($owner = $this->networks->findMacOwner($mac, $inventoryNumberId)) !== null) {
                    $errors[] = "{$label}: Die MAC-Adresse {$mac} ist bereits bei Inventarnummer {$owner} hinterlegt.";
                    $mac = null;
                } else {
                    $seenMacs[$mac] = $label;
                }
            }

            $assignment = new NetworkInterfaceAssignment('', $slot->key, $label, $network?->id, $addresses[4], $addresses[6], $mac);
            if (!$assignment->isEmpty()) {
                $assignments[] = $assignment;
            }
        }

        if ($errors !== []) {
            throw new ValidationException(['network' => implode(' ', $errors)]);
        }

        return $assignments;
    }

    /** @param NetworkInterfaceAssignment[] $assignments result of validate() */
    public function save(string $inventoryNumberId, array $assignments): void
    {
        $this->networks->replaceAssignments($inventoryNumberId, array_map(
            static fn (NetworkInterfaceAssignment $a) => new NetworkInterfaceAssignment(
                $inventoryNumberId,
                $a->interfaceKey,
                $a->label,
                $a->networkId,
                $a->ipv4Address,
                $a->ipv6Address,
                $a->macAddress,
            ),
            $assignments,
        ));
    }

    /**
     * @param NetworkInterfaceSlot[] $slots
     * @param array<string, NetworkInterfaceAssignment> $existing
     * @return NetworkInterfaceSlot[]
     */
    private function withOrphans(array $slots, array $existing): array
    {
        $keys = array_map(static fn (NetworkInterfaceSlot $slot) => $slot->key, $slots);
        foreach ($existing as $key => $assignment) {
            if (!in_array($key, $keys, true)) {
                $slots[] = new NetworkInterfaceSlot($key, $assignment->label, NetworkInterfaceSlot::KIND_ORPHAN, optional: true);
            }
        }

        return $slots;
    }

    /** @return array{0: string, 1: string}|null */
    private function suggestionRange(Network $network, int $version, ?\App\Domain\Network\NetworkCategoryRange $categoryRange): ?array
    {
        if (!$network->supports($version)) {
            return null;
        }
        if ($categoryRange !== null && $categoryRange->networkId === $network->id && ($range = $categoryRange->range($version)) !== null) {
            return $range;
        }

        return $network->hostRange($version);
    }

    /** @param Network[] $networks */
    private function findIn(array $networks, string $id): ?Network
    {
        foreach ($networks as $network) {
            if ($network->id === $id) {
                return $network;
            }
        }

        return null;
    }
}
