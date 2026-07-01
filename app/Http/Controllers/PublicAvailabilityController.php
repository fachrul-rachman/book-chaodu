<?php

namespace App\Http\Controllers;

use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

class PublicAvailabilityController extends Controller
{
    public function __invoke(AvailabilityService $availabilityService): JsonResponse
    {
        return response()->json($availabilityService->summary());
    }
}
