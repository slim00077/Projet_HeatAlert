<?php

namespace App\Http\Controllers;

use App\Models\PowerOutage;
use App\Services\OutageImpactSummaryService;
use Illuminate\Http\JsonResponse;

class OutageImpactSummaryController extends Controller
{
    public function __invoke(PowerOutage $powerOutage, OutageImpactSummaryService $summaryService): JsonResponse
    {
        return response()->json($summaryService->summarize($powerOutage));
    }
}
