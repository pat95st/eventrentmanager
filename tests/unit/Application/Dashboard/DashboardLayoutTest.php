<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Dashboard;

use App\Application\Dashboard\ApplyDashboardTemplate;
use App\Application\Dashboard\DashboardLayoutCommandHandler;
use App\Application\Dashboard\DashboardTemplateCommandHandler;
use App\Application\Dashboard\DashboardTileCatalog;
use App\Application\Dashboard\ResetDashboardLayout;
use App\Application\Dashboard\SaveDashboardTemplate;
use App\Application\Dashboard\UpdateDashboardLayout;
use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\DashboardTemplate;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryDashboardTemplateRepository;
use Tests\Support\Fakes\InMemoryUserRepository;

/** Eigene Anordnung, Vorlagen und vor allem: niemand bekommt Kacheln, für die ihm das Recht fehlt. */
final class DashboardLayoutTest extends Unit
{
    private InMemoryUserRepository $users;
    private InMemoryDashboardTemplateRepository $templates;
    private DashboardLayoutCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new InMemoryUserRepository();
        $this->templates = new InMemoryDashboardTemplateRepository();
        $this->handler = new DashboardLayoutCommandHandler($this->users, $this->templates);
    }

    public function testDefaultLayoutShowsOnlyPermittedTiles(): void
    {
        $warehouse = $this->user('lager', Permission::Artikelverwaltung);

        $this->assertSame(
            ['main' => [], 'side' => ['open_purchase_orders', 'receipt_inbox', 'open_rma'], 'bottom' => ['stat:top-artikel', 'stat:bestand', 'stat:elektropruefungen']],
            DashboardTileCatalog::layoutFor($warehouse)->zones(),
        );
        $this->assertTrue(DashboardTileCatalog::layoutFor($this->user('kunden', Permission::Kundenverwaltung))->isEmpty());
        $this->assertSame(array_keys(DashboardTileCatalog::all()), DashboardTileCatalog::layoutFor($this->user('admin', Permission::Administration))->keys());
    }

    public function testEarlierWidgetSelectionStillAppliesUntilALayoutIsSaved(): void
    {
        $user = $this->user('verleih', Permission::AusgabeRuecknahme);
        $user->dashboardWidgets = ['open_offers', 'income_expense'];

        $this->assertSame(
            ['main' => [], 'side' => ['open_offers'], 'bottom' => ['stat:veranstaltungen', 'stat:angebote']],
            DashboardTileCatalog::layoutFor($user)->zones(),
        );
    }

    public function testSavingDropsUnknownAndForbiddenTiles(): void
    {
        $this->user('lager', Permission::Artikelverwaltung);

        $this->handler->handle(new UpdateDashboardLayout('lager', [
            'main' => ['stat:bestand', 'income_expense', 'gibt-es-nicht'],
            'side' => ['open_rma', 'stat:einnahmen'],
        ]));

        $this->assertSame(
            ['main' => ['stat:bestand'], 'side' => ['open_rma'], 'bottom' => []],
            $this->users->findById('lager')?->dashboardLayout?->zones(),
        );
    }

    public function testRevokedPermissionHidesTilesOfASavedLayout(): void
    {
        $user = $this->user('lager', Permission::Artikelverwaltung);
        $this->handler->handle(new UpdateDashboardLayout('lager', ['main' => ['open_rma', 'stat:bestand']]));

        $user->permissions = [Permission::Kundenverwaltung];

        $this->assertTrue(DashboardTileCatalog::layoutFor($user)->isEmpty());
    }

    public function testApplyingATemplateCopiesOnlyPermittedTiles(): void
    {
        $this->user('lager', Permission::Artikelverwaltung);
        $this->templates->save(new DashboardTemplate('t1', 'Büro', null, DashboardLayout::fromZones([
            'main' => ['income_expense', 'receipt_inbox'],
            'bottom' => ['stat:einnahmen', 'stat:bestand'],
        ])));

        $this->handler->handle(new ApplyDashboardTemplate('lager', 't1'));

        $this->assertSame(
            ['main' => ['receipt_inbox'], 'side' => [], 'bottom' => ['stat:bestand']],
            $this->users->findById('lager')?->dashboardLayout?->zones(),
        );
    }

    public function testTemplateWithoutAnyPermittedTileCannotBeApplied(): void
    {
        $this->user('lager', Permission::Artikelverwaltung);
        $this->templates->save(new DashboardTemplate('t1', 'Finanzen', null, DashboardLayout::fromZones(['main' => ['income_expense']])));

        $this->expectException(NotFoundException::class);
        $this->handler->handle(new ApplyDashboardTemplate('lager', 't1'));
    }

    public function testResetRestoresTheDefault(): void
    {
        $user = $this->user('lager', Permission::Artikelverwaltung);
        $user->dashboardWidgets = [];
        $this->handler->handle(new UpdateDashboardLayout('lager', ['main' => ['open_rma']]));

        $this->handler->handle(new ResetDashboardLayout('lager'));

        $this->assertNull($user->dashboardLayout);
        $this->assertCount(6, DashboardTileCatalog::layoutFor($user)->keys());
    }

    public function testTemplatesAreValidatedAndMayContainAnyTile(): void
    {
        $handler = new DashboardTemplateCommandHandler($this->templates);

        $id = $handler->handle(new SaveDashboardTemplate(null, ' Büro ', '', ['side' => ['income_expense', 'gibt-es-nicht']]));
        $this->assertSame('Büro', $this->templates->findById($id)?->name);
        $this->assertSame(['income_expense'], $this->templates->findById($id)?->layout->keys());

        $handler->handle(new SaveDashboardTemplate($id, 'Lager', 'Für das Lager', ['main' => ['open_rma']]));
        $this->assertCount(1, $this->templates->findAll());
        $this->assertSame('Lager', $this->templates->findById($id)?->name);

        try {
            $handler->handle(new SaveDashboardTemplate(null, '', '', ['main' => ['gibt-es-nicht']]));
            $this->fail('ValidationException erwartet');
        } catch (ValidationException $e) {
            $this->assertSame(['name', 'layout'], array_keys($e->errors()));
        }
    }

    private function user(string $id, Permission $permission): User
    {
        $now = new DateTimeImmutable();
        $user = new User($id, $id . '@example.test', null, null, null, [$permission], TwoFactorMethod::None, null, false, false, true, $now, $now);
        $this->users->save($user);

        return $user;
    }
}
