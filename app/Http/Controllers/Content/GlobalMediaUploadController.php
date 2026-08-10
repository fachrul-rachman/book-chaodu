<?php

namespace App\Http\Controllers\Content;

use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CompleteGlobalMediaUploadRequest;
use App\Http\Requests\Content\InitiateGlobalMediaUploadRequest;
use App\Http\Requests\Content\SignGlobalMediaPartRequest;
use App\Models\GalleryMedia;
use App\Services\GalleryDirectUploadService;
use App\Services\GlobalGalleryMediaService;
use Illuminate\Http\JsonResponse;

class GlobalMediaUploadController extends Controller
{
    public function store(
        InitiateGlobalMediaUploadRequest $request,
        GlobalGalleryMediaService $service,
    ): JsonResponse {
        /** @var array{original_filename: string, upload_token: string, mime_type: string, size_bytes: int, caption?: string|null} $data */
        $data = $request->validated();
        $result = $service->initiate($data, $request->user());

        return response()->json([
            'media' => $this->mediaPayload($result['media']),
            'upload' => $result['upload'],
        ], 201);
    }

    public function signPart(
        SignGlobalMediaPartRequest $request,
        GalleryMedia $media,
        GalleryDirectUploadService $directUpload,
    ): JsonResponse {
        abort_unless(
            $media->scope === GalleryMediaScope::Global
            && $media->status === GalleryMediaStatus::Processing
            && $media->upload_mode === 'MULTIPART'
            && $media->upload_id,
            404,
        );

        $partNumber = (int) $request->validated('part_number');
        $expectedPartCount = (int) ceil($media->size_bytes / (int) config('gallery.multipart_part_size_bytes'));
        abort_if($partNumber > $expectedPartCount, 422, 'Nomor bagian melebihi ukuran file.');

        return response()->json($directUpload->signPart($media, $partNumber));
    }

    public function complete(
        CompleteGlobalMediaUploadRequest $request,
        GalleryMedia $media,
        GlobalGalleryMediaService $service,
    ): JsonResponse {
        /** @var array<int, array{part_number: int, etag: string}> $parts */
        $parts = $request->validated('parts');
        $media = $service->complete($media, $parts);

        return response()->json(['media' => $this->mediaPayload($media)]);
    }

    /** @return array<string, mixed> */
    private function mediaPayload(GalleryMedia $media): array
    {
        return [
            'id' => $media->id,
            'uuid' => $media->uuid,
            'status' => $media->status->value,
            'filename' => $media->original_filename,
            'caption' => $media->caption,
        ];
    }
}
