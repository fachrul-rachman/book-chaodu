<?php

namespace App\Http\Controllers;

use App\Enums\PackageCode;
use App\Http\Requests\Public\ReleaseVirtualAccountRequest;
use App\Http\Requests\Public\ReserveVirtualAccountRequest;
use App\Services\VirtualAccountService;
use Illuminate\Http\JsonResponse;

class PublicVirtualAccountReservationController extends Controller
{
    public function store(
        ReserveVirtualAccountRequest $request,
        VirtualAccountService $virtualAccountService,
    ): JsonResponse {
        return response()->json(
            $virtualAccountService->reserve(
                PackageCode::from((string) $request->validated('package_code')),
                (string) $request->validated('idempotency_key'),
            ),
        );
    }

    public function destroy(
        ReleaseVirtualAccountRequest $request,
        VirtualAccountService $virtualAccountService,
    ): JsonResponse {
        $virtualAccountService->releaseReservation(
            (string) $request->validated('idempotency_key'),
        );

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
