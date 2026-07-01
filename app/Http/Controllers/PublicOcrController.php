<?php

namespace App\Http\Controllers;

use App\Exceptions\OcrRecognitionException;
use App\Http\Requests\Public\RecognizeMandarinNameRequest;
use App\Services\TwoOcrClient;
use Illuminate\Http\JsonResponse;

class PublicOcrController extends Controller
{
    public function store(RecognizeMandarinNameRequest $request, TwoOcrClient $twoOcrClient): JsonResponse
    {
        try {
            $text = $twoOcrClient->recognize($request->file('source_image'));
        } catch (OcrRecognitionException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Tulisan pada foto belum bisa dibaca. Anda tetap bisa isi manual.',
            ], 422);
        }

        return response()->json([
            'text' => $text,
        ]);
    }
}
