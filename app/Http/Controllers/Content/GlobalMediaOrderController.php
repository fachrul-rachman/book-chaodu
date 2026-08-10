<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ReorderGlobalMediaRequest;
use App\Services\GlobalGalleryMediaService;
use Illuminate\Http\JsonResponse;

class GlobalMediaOrderController extends Controller
{
    public function __invoke(ReorderGlobalMediaRequest $request, GlobalGalleryMediaService $service): JsonResponse
    {
        /** @var array<int, int> $ids */
        $ids = $request->validated('media_ids');
        $service->reorder($ids);

        return response()->json(['message' => 'Urutan media berhasil disimpan.']);
    }
}
