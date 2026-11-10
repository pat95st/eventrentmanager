<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\DryHire\Command\SaveDryHireTiers;
use App\Domain\DryHire\DryHireRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Einstellungen › Preise & Zahlung › Dry-Hire-Staffel (Migration 141). */
final class DryHireSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly DryHireRepositoryInterface $dryHire,
    ) {
    }

    public function edit(Request $request): void
    {
        $tiers = array_map(static fn ($t) => [(string) $t->fromDay, (string) $t->percentage], $this->dryHire->tiers());
        $this->render($tiers, null);
    }

    public function update(Request $request): void
    {
        $days = $request->inputArray('from_day');
        $pcts = $request->inputArray('percentage');
        $tiers = [];
        foreach ($days as $i => $day) {
            $tiers[] = [(string) $day, (string) ($pcts[$i] ?? '')];
        }
        try {
            $this->commands->dispatch(new SaveDryHireTiers($tiers));
            Flash::success('Die Dry-Hire-Staffel wurde gespeichert.');
            Response::redirect('/einstellungen/dry-hire-staffel');
        } catch (ValidationException $e) {
            $this->render($tiers, implode(' ', $e->errors()));
        }
    }

    /** @param list<array{0: string, 1: string}> $tiers */
    private function render(array $tiers, ?string $error): void
    {
        echo View::page('settings/dry-hire', [
            'tiers' => $tiers,
            'error' => $error,
            'dryHireArticleCount' => count($this->dryHire->articlePrices()),
            'dryHireCustomerCount' => count($this->dryHire->dryHireCustomerIds()),
        ], activeNav: 'settings-dry-hire', title: 'Dry-Hire-Staffel');
    }
}
