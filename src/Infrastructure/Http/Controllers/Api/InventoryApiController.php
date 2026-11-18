<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Article\Query\GetInventoryNumberContext;
use App\Application\CaseItem\Query\GetCaseItem;
use App\Application\CaseItem\Query\ListCaseItems;
use App\Application\ElectricalInspection\Command\CreateElectricalInspection;
use App\Application\ElectricalInspection\Query\GetElectricalInspection;
use App\Application\ElectricalInspection\Query\ListElectricalInspectionsForInventoryNumber;
use App\Application\ElectricalInspection\Query\ListRecentElectricalInspections;
use App\Application\Rack\Query\GetRack;
use App\Application\Rack\Query\ListRacks;
use App\Application\Rma\Query\GetRma;
use App\Application\Rma\Query\ListRmas;
use App\Application\Sale\Query\ListSales;
use App\Domain\Rma\RmaStatus;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\Normalizer;

/**
 * Lager & Technik: /api/v1/cases, /racks, /rmas, /electrical-inspections,
 * /sales — scope inventory:read; recording an electrical inspection needs
 * inventory:write.
 */
final class InventoryApiController extends ApiController
{
    public function cases(ApiRequest $request): ApiResponse
    {
        $result = $this->ask(new ListCaseItems(manufacturerId: $request->query('manufacturer_id'), page: 1, pageSize: 1_000_000));

        return $this->listResponse($request, Normalizer::list($result->items));
    }

    public function caseItem(ApiRequest $request): ApiResponse
    {
        $detail = $this->ask(new GetCaseItem($request->param('id')));

        return ApiResponse::data(Normalizer::normalize($detail->case) + [
            'manufacturer' => $detail->manufacturer->name,
            'inventory_numbers' => Normalizer::normalize($detail->inventoryNumbers),
        ]);
    }

    public function racks(ApiRequest $request): ApiResponse
    {
        $racks = $this->ask(new ListRacks());

        return $this->listResponse($request, array_map(static fn ($detail) => Normalizer::normalize($detail->rack) + [
            'manufacturer' => $detail->manufacturer?->name,
            'exemplar_count' => count($detail->exemplars),
        ], $racks));
    }

    public function rack(ApiRequest $request): ApiResponse
    {
        $detail = $this->ask(new GetRack($request->param('id')));

        return ApiResponse::data(Normalizer::normalize($detail->rack) + [
            'manufacturer' => $detail->manufacturer?->name,
            'exemplars' => Normalizer::normalize($detail->exemplars),
        ]);
    }

    public function rmas(ApiRequest $request): ApiResponse
    {
        $status = $request->query('status');
        $statusEnum = $status !== null && $status !== '' ? (RmaStatus::tryFrom($status) ?? throw ApiException::badRequest('Unbekannter Status "' . $status . '".')) : null;
        $entries = $this->ask(new ListRmas($statusEnum));

        return $this->listResponse($request, array_map(static fn ($entry) => Normalizer::normalize($entry->rma) + [
            'inventory_number' => ['id' => $entry->inventoryNumber->id, 'number' => $entry->inventoryNumber->number],
            'article' => ['id' => $entry->article->id, 'name' => $entry->article->name],
            'manufacturer' => $entry->manufacturer->name,
            'document_count' => $entry->documentCount,
        ], $entries));
    }

    public function rma(ApiRequest $request): ApiResponse
    {
        $detail = $this->ask(new GetRma($request->param('id')));

        return ApiResponse::data(Normalizer::normalize($detail->rma) + [
            'documents' => Normalizer::normalize($detail->documents),
            'receipts' => Normalizer::normalize($detail->receipts),
            'package_photos' => Normalizer::normalize($detail->packagePhotos),
        ]);
    }

    /** Most recent inspections across all inventory numbers (max. 500). */
    public function electricalInspections(ApiRequest $request): ApiResponse
    {
        $entries = $this->ask(new ListRecentElectricalInspections(limit: self::MAX_PAGE_SIZE));

        return $this->listResponse($request, array_map(static fn ($entry) => Normalizer::normalize($entry->inspection) + [
            'inventory_number' => $entry->inventoryNumber,
            'article_label' => $entry->articleLabel,
        ], $entries));
    }

    public function electricalInspection(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data(Normalizer::normalize($this->ask(new GetElectricalInspection($request->param('id')))));
    }

    public function inventoryNumberInspections(ApiRequest $request): ApiResponse
    {
        $this->ask(new GetInventoryNumberContext($request->param('id')));

        return $this->listResponse($request, Normalizer::list($this->ask(new ListElectricalInspectionsForInventoryNumber($request->param('id')))));
    }

    /** POST /inventory-numbers/{id}/electrical-inspections — scope inventory:write. */
    public function storeInspection(ApiRequest $request): ApiResponse
    {
        $this->ask(new GetInventoryNumberContext($request->param('id')));
        $id = $this->run(CreateElectricalInspection::class, $request, ['inventoryNumberId' => $request->param('id')]);

        return $this->created(Normalizer::normalize($this->ask(new GetElectricalInspection((string) $id))));
    }

    public function sales(ApiRequest $request): ApiResponse
    {
        $result = $this->ask(new ListSales(page: 1, pageSize: 1_000_000));
        $items = [];
        foreach ($result->items as $entry) {
            $items[] = Normalizer::normalize($entry->sale) + [
                'article' => $entry->article !== null ? ['id' => $entry->article->id, 'name' => $entry->article->name] : null,
                'customer' => SalesDocumentSupport::customerRef($entry->customer),
            ];
        }

        return $this->listResponse($request, $items);
    }
}
