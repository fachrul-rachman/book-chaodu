<?php

namespace App\Http\Controllers;

use App\Services\PublicBookingCatalog;
use Illuminate\Http\JsonResponse;

class PublicPackageController extends Controller
{
    public function index(PublicBookingCatalog $catalog): JsonResponse
    {
        return response()->json($catalog->data());
    }
}
