<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Network\Command\DeleteNetwork;
use App\Application\Network\Command\DeleteNetworkCategoryRange;
use App\Application\Network\Command\SaveNetwork;
use App\Application\Network\Command\SaveNetworkCategoryRange;
use App\Application\Network\Query\GetNetworkDetail;
use App\Application\Network\Query\GetNetworkSettings;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Settings › networks (migration 155): networks, category ranges and address list. */
final class NetworkSettingsController
{
    private const URL = '/einstellungen/netzwerke';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $this->renderIndex([], []);
    }

    public function show(Request $request): void
    {
        try {
            $detail = $this->queries->ask(new GetNetworkDetail($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        echo View::page('settings/network', [
            'detail' => $detail,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-netzwerke', title: 'Netzwerk: ' . $detail->network->name);
    }

    public function create(Request $request): void
    {
        $command = $this->commandFromRequest(null, $request);
        try {
            $id = $this->commands->dispatch($command);
            Flash::success('Das Netzwerk wurde angelegt.');
            Response::redirect(self::URL . '/' . $id);
        } catch (ValidationException $e) {
            $this->renderIndex($e->errors(), $request->post);
        }
    }

    public function save(Request $request): void
    {
        $id = $request->param('id');
        try {
            $this->commands->dispatch($this->commandFromRequest($id, $request));
            Flash::success('Das Netzwerk wurde gespeichert.');
        } catch (ValidationException $e) {
            try {
                $detail = $this->queries->ask(new GetNetworkDetail($id));
            } catch (NotFoundException $notFound) {
                Response::notFound($notFound->getMessage());

                return;
            }
            echo View::page('settings/network', [
                'detail' => $detail,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-netzwerke', title: 'Netzwerk: ' . $detail->network->name);

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect(self::URL . '/' . $id);
    }

    public function delete(Request $request): void
    {
        try {
            $this->commands->dispatch(new DeleteNetwork($request->param('id')));
            Flash::success('Das Netzwerk wurde gelöscht.');
            Response::redirect(self::URL);
        } catch (ConflictException | NotFoundException $e) {
            Flash::error($e->getMessage());
            Response::redirect(self::URL . '/' . $request->param('id'));
        }
    }

    public function saveRange(Request $request): void
    {
        $id = $request->param('id');
        try {
            $this->commands->dispatch(new SaveNetworkCategoryRange(
                networkId: $id,
                categoryId: $request->input('category_id', '') ?? '',
                ipv4Start: $request->input('ipv4_start'),
                ipv4End: $request->input('ipv4_end'),
                ipv6Start: $request->input('ipv6_start'),
                ipv6End: $request->input('ipv6_end'),
            ));
            Flash::success('Der Adressbereich der Kategorie wurde gespeichert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect(self::URL . '/' . $id . '#kategorien');
    }

    public function deleteRange(Request $request): void
    {
        $this->commands->dispatch(new DeleteNetworkCategoryRange($request->param('categoryId')));
        Flash::success('Der Adressbereich der Kategorie wurde entfernt.');
        Response::redirect(self::URL . '/' . $request->param('id') . '#kategorien');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderIndex(array $errors, array $old): void
    {
        echo View::page('settings/networks', [
            'settings' => $this->queries->ask(new GetNetworkSettings()),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-netzwerke', title: 'Netzwerke');
    }

    private function commandFromRequest(?string $id, Request $request): SaveNetwork
    {
        return new SaveNetwork(
            id: $id,
            name: $request->input('name', '') ?? '',
            ipMode: $request->input('ip_mode', '') ?? '',
            vlanId: $request->input('vlan_id'),
            ipv4Network: $request->input('ipv4_network'),
            ipv4Mask: $request->input('ipv4_mask'),
            ipv6Network: $request->input('ipv6_network'),
            ipv6Prefix: $request->input('ipv6_prefix'),
            ipv6DefaultPrefix: $request->input('ipv6_default_prefix'),
            notes: $request->input('notes'),
            sortOrder: $request->input('sort_order'),
        );
    }
}
