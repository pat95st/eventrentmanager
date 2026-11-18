<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Depreciation\GetDepreciationReport;
use App\Application\MinuteProgram\Query\GetMinuteProgramTemplateDetail;
use App\Application\MinuteProgram\Query\ListMinuteProgramTemplates;
use App\Application\Reporting\Query\GetIncomeExpenseOverview;
use App\Application\Reporting\Query\GetRevenueForecast;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\Normalizer;

/**
 * /api/v1/reports/* (scope reports:read) and /api/v1/minute-program-templates
 * (scope minute_programs:read).
 */
final class ReportApiController extends ApiController
{
    public function incomeExpense(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data(Normalizer::normalize($this->ask(new GetIncomeExpenseOverview($this->year($request, 'year')))));
    }

    public function revenueForecast(ApiRequest $request): ApiResponse
    {
        $years = (int) ($request->query('years') ?? '5');
        if ($years < 1 || $years > 20) {
            throw ApiException::badRequest('"years" muss zwischen 1 und 20 liegen.');
        }
        $forecast = $this->ask(new GetRevenueForecast($this->year($request, 'from_year'), $years));

        return ApiResponse::data(Normalizer::normalize($forecast) + ['grand_total' => $forecast->grandTotal()]);
    }

    public function depreciation(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data(Normalizer::normalize($this->ask(new GetDepreciationReport($this->year($request, 'year')))));
    }

    public function minuteProgramTemplates(ApiRequest $request): ApiResponse
    {
        return $this->listResponse($request, Normalizer::list($this->ask(new ListMinuteProgramTemplates())));
    }

    public function minuteProgramTemplate(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data(Normalizer::normalize($this->ask(new GetMinuteProgramTemplateDetail($request->param('id')))));
    }

    private function year(ApiRequest $request, string $name): int
    {
        $raw = $request->query($name) ?? date('Y');
        if (preg_match('/^\d{4}$/', $raw) !== 1) {
            throw ApiException::badRequest('"' . $name . '" muss eine vierstellige Jahreszahl sein.');
        }

        return (int) $raw;
    }
}
