<?php

declare(strict_types=1);

namespace App\Application\Network\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Network\IpAddress;
use App\Domain\Network\IpMode;
use App\Domain\Network\Network;
use App\Domain\Network\NetworkCategoryRange;
use App\Domain\Network\NetworkRepositoryInterface;
use App\Shared\Uuid;

/**
 * Settings › networks (migration 155): create, change and delete networks
 * and the fixed address ranges of categories. Only private address ranges
 * are allowed (RFC 1918, IPv6 Unique Local Addresses fc00::/7).
 */
final class NetworkSettingsHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly NetworkRepositoryInterface $networks,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SaveNetwork => $this->save($command),
            $command instanceof DeleteNetwork => $this->delete($command->id),
            $command instanceof SaveNetworkCategoryRange => $this->saveRange($command),
            $command instanceof DeleteNetworkCategoryRange => $this->deleteRange($command->categoryId),
            default => throw new \LogicException('Unbekannter Befehl.'),
        };
    }

    private function save(SaveNetwork $command): string
    {
        $existing = $command->id !== null ? $this->networks->findById($command->id) : null;
        if ($command->id !== null && $existing === null) {
            throw new NotFoundException('Netzwerk wurde nicht gefunden.');
        }

        $errors = [];
        $name = trim($command->name);
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Bitte eine Bezeichnung angeben (max. 100 Zeichen), z. B. „Dante Primary“.';
        } elseif ($this->networks->nameExists($name, $existing?->id)) {
            $errors['name'] = 'Ein Netzwerk mit dieser Bezeichnung gibt es bereits.';
        }

        $mode = IpMode::tryFrom($command->ipMode);
        if ($mode === null) {
            $errors['ipMode'] = 'Bitte IPv4, IPv6 oder Dualstack auswählen.';
        }

        $vlanId = null;
        $vlanRaw = trim((string) $command->vlanId);
        if ($vlanRaw !== '') {
            if (preg_match('/^\d{1,4}$/', $vlanRaw) !== 1 || (int) $vlanRaw < 1 || (int) $vlanRaw > 4094) {
                $errors['vlanId'] = 'Die VLAN-ID muss zwischen 1 und 4094 liegen.';
            } else {
                $vlanId = (int) $vlanRaw;
            }
        }

        $ipv4 = [null, null];
        $ipv6 = [null, null];
        $ipv6Default = null;
        if ($mode !== null && $mode->supports(4)) {
            $ipv4 = $this->parseSubnet(4, $command->ipv4Network, $command->ipv4Mask, $errors);
        }
        if ($mode !== null && $mode->supports(6)) {
            $ipv6 = $this->parseSubnet(6, $command->ipv6Network, $command->ipv6Prefix, $errors);
            $defaultRaw = trim((string) $command->ipv6DefaultPrefix);
            if ($defaultRaw !== '') {
                $ipv6Default = IpAddress::parsePrefix($defaultRaw, 6);
                if ($ipv6Default === null || ($ipv6[1] !== null && $ipv6Default < $ipv6[1])) {
                    $errors['ipv6DefaultPrefix'] = 'Die Standard-Präfixlänge muss zwischen der Präfixlänge des Netzwerks und 128 liegen (z. B. 64).';
                    $ipv6Default = null;
                }
            } elseif ($ipv6[1] !== null) {
                $ipv6Default = max(64, $ipv6[1]);
            }
        }

        $notes = trim((string) $command->notes);
        if (mb_strlen($notes) > 255) {
            $errors['notes'] = 'Die Notiz darf höchstens 255 Zeichen lang sein.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        assert($mode !== null);

        $network = new Network(
            $existing?->id ?? Uuid::v4(),
            $name,
            $mode,
            $vlanId,
            $ipv4[0],
            $ipv4[1],
            $ipv6[0],
            $ipv6[1],
            $ipv6Default,
            $notes !== '' ? $notes : null,
            self::sort($command->sortOrder, $existing?->sortOrder),
        );

        if ($existing !== null) {
            $this->guardExistingAssignments($network);
        }
        $this->networks->save($network);

        return $network->id;
    }

    /**
     * @param array<string, string> $errors
     * @return array{0: ?string, 1: ?int} canonical network address and prefix length
     */
    private function parseSubnet(int $version, ?string $networkRaw, ?string $prefixRaw, array &$errors): array
    {
        $field = 'ipv' . $version;
        $label = 'IPv' . $version;
        $networkRaw = trim((string) $networkRaw);
        $prefixRaw = trim((string) $prefixRaw);
        if (str_contains($networkRaw, '/')) {
            [$networkRaw, $cidrPrefix] = explode('/', $networkRaw, 2);
            $prefixRaw = $prefixRaw !== '' ? $prefixRaw : $cidrPrefix;
        }
        if ($networkRaw === '') {
            $errors[$field . 'Network'] = "Bitte die {$label}-Netzwerkadresse angeben.";

            return [null, null];
        }
        $packed = IpAddress::parse($networkRaw, $version);
        if ($packed === null) {
            $errors[$field . 'Network'] = "„{$networkRaw}“ ist keine gültige {$label}-Adresse.";

            return [null, null];
        }
        $prefix = $prefixRaw !== '' ? IpAddress::parsePrefix($prefixRaw, $version) : null;
        [$min, $max] = $version === 4 ? [Network::IPV4_MIN_PREFIX, Network::IPV4_MAX_PREFIX] : [Network::IPV6_MIN_PREFIX, Network::IPV6_MAX_PREFIX];
        if ($prefix === null || $prefix < $min || $prefix > $max) {
            $errors[$field . 'Prefix'] = $version === 4
                ? "Bitte eine gültige Subnetzmaske angeben: /{$min} bis /{$max} oder z. B. 255.255.255.0."
                : "Bitte eine gültige Präfixlänge zwischen /{$min} und /{$max} angeben (z. B. /64).";

            return [null, null];
        }
        $network = IpAddress::networkAddress($packed, $prefix);
        if ($network !== $packed) {
            $errors[$field . 'Network'] = "{$networkRaw} ist keine Netzwerkadresse für /{$prefix} – gemeint ist vermutlich "
                . IpAddress::format($network) . '/' . $prefix . '.';

            return [null, null];
        }
        if (!IpAddress::isPrivateNetwork($network, $prefix)) {
            $errors[$field . 'Network'] = $version === 4
                ? 'Nur private IPv4-Bereiche sind erlaubt: 10.0.0.0/8, 172.16.0.0/12 oder 192.168.0.0/16.'
                : 'Nur private IPv6-Bereiche (Unique Local Addresses, fc00::/7, z. B. fd00::/8) sind erlaubt.';

            return [null, null];
        }

        return [IpAddress::format($network), $prefix];
    }

    /** Changing the subnet must not leave assigned addresses or category ranges outside of it. */
    private function guardExistingAssignments(Network $network): void
    {
        foreach ($this->networks->findAddressList($network->id) as $entry) {
            foreach ([4, 6] as $version) {
                $address = $entry->assignment->address($version);
                if ($address !== null && (!$network->supports($version) || !$network->isAssignable($address, $version))) {
                    throw new ValidationException(['network' => "Die Adresse {$address} (Inventarnummer {$entry->inventoryNumber}) läge danach außerhalb des Netzwerks. Bitte zuerst die Adresse ändern."]);
                }
            }
        }
        foreach ($this->networks->findAllCategoryRanges() as $range) {
            if ($range->networkId !== $network->id) {
                continue;
            }
            foreach ([4, 6] as $version) {
                $bounds = $range->range($version);
                if (
                    $bounds !== null && (!$network->supports($version)
                    || !$network->isAssignable(IpAddress::format($bounds[0]), $version)
                    || !$network->isAssignable(IpAddress::format($bounds[1]), $version))
                ) {
                    $category = $this->categories->findById($range->categoryId)?->name ?? $range->categoryId;
                    throw new ValidationException(['network' => "Der Adressbereich der Kategorie „{$category}“ läge danach außerhalb des Netzwerks. Bitte zuerst den Bereich ändern."]);
                }
            }
        }
    }

    private function delete(string $id): mixed
    {
        $network = $this->networks->findById($id) ?? throw new NotFoundException('Netzwerk wurde nicht gefunden.');
        $used = $this->networks->assignmentCounts()[$id] ?? 0;
        if ($used > 0) {
            throw new ConflictException("Das Netzwerk „{$network->name}“ ist bei {$used} Schnittstelle(n) eingetragen und kann nicht gelöscht werden.");
        }
        $this->networks->delete($id);

        return null;
    }

    private function saveRange(SaveNetworkCategoryRange $command): mixed
    {
        $network = $this->networks->findById($command->networkId) ?? throw new NotFoundException('Netzwerk wurde nicht gefunden.');
        $category = $this->categories->findById($command->categoryId);
        if ($category === null) {
            throw new ValidationException(['categoryId' => 'Bitte eine gültige Kategorie auswählen.']);
        }
        $other = $this->networks->findCategoryRange($category->id);
        if ($other !== null && $other->networkId !== $network->id) {
            $otherNetwork = $this->networks->findById($other->networkId);
            throw new ValidationException(['categoryId' => "Die Kategorie „{$category->name}“ hat bereits einen Bereich im Netzwerk „" . ($otherNetwork?->name ?? '?') . '“.']);
        }

        $errors = [];
        $bounds = [4 => [null, null], 6 => [null, null]];
        foreach ([4 => [$command->ipv4Start, $command->ipv4End], 6 => [$command->ipv6Start, $command->ipv6End]] as $version => [$startRaw, $endRaw]) {
            $startRaw = trim((string) $startRaw);
            $endRaw = trim((string) $endRaw);
            if ($startRaw === '' && $endRaw === '') {
                continue;
            }
            $label = 'IPv' . $version;
            if (!$network->supports($version)) {
                $errors['ipv' . $version] = "Das Netzwerk hat kein {$label}.";
                continue;
            }
            $start = IpAddress::normalize($startRaw, $version);
            $end = IpAddress::normalize($endRaw, $version);
            if ($start === null || $end === null) {
                $errors['ipv' . $version] = "Bitte Anfang und Ende des {$label}-Bereichs als gültige Adressen angeben.";
                continue;
            }
            if (!$network->isAssignable($start, $version) || !$network->isAssignable($end, $version)) {
                $errors['ipv' . $version] = "Der {$label}-Bereich muss innerhalb von {$network->cidr($version)} liegen (ohne Netzwerk- und Broadcast-Adresse).";
                continue;
            }
            if (IpAddress::compare((string) inet_pton($start), (string) inet_pton($end)) > 0) {
                $errors['ipv' . $version] = "Der Anfang des {$label}-Bereichs muss vor dem Ende liegen.";
                continue;
            }
            $bounds[$version] = [$start, $end];
        }
        if ($errors === [] && $bounds[4][0] === null && $bounds[6][0] === null) {
            $errors['ipv4'] = 'Bitte mindestens einen Adressbereich angeben.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $range = new NetworkCategoryRange($category->id, $network->id, $bounds[4][0], $bounds[4][1], $bounds[6][0], $bounds[6][1]);
        foreach ($this->networks->findAllCategoryRanges() as $existing) {
            if ($existing->networkId !== $network->id || $existing->categoryId === $category->id) {
                continue;
            }
            foreach ([4, 6] as $version) {
                $a = $range->range($version);
                $b = $existing->range($version);
                if ($a !== null && $b !== null && IpAddress::compare($a[0], $b[1]) <= 0 && IpAddress::compare($b[0], $a[1]) <= 0) {
                    $otherName = $this->categories->findById($existing->categoryId)?->name ?? '?';
                    throw new ValidationException(['ipv' . $version => "Der IPv{$version}-Bereich überschneidet sich mit dem Bereich der Kategorie „{$otherName}“ ({$existing->label($version)})."]);
                }
            }
        }
        $this->networks->saveCategoryRange($range);

        return null;
    }

    private function deleteRange(string $categoryId): mixed
    {
        $this->networks->deleteCategoryRange($categoryId);

        return null;
    }

    private static function sort(?string $raw, ?int $fallback): int
    {
        $raw = $raw !== null ? trim($raw) : '';

        return $raw !== '' && preg_match('/^-?\d{1,6}$/', $raw) === 1 ? (int) $raw : ($fallback ?? 0);
    }
}
