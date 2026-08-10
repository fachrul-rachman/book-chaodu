<?php

namespace App\Http\Controllers\Content;

use App\Enums\GalleryMediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CompleteGlobalMediaUploadRequest;
use App\Http\Requests\Content\InitiateGlobalMediaUploadRequest;
use App\Http\Requests\Content\SignGlobalMediaPartRequest;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Services\BookingGalleryMediaService;
use App\Services\GalleryDirectUploadService;
use Illuminate\Http\JsonResponse;

class CustomerMediaUploadController extends Controller
{
    public function store(
        InitiateGlobalMediaUploadRequest $request,
        Booking $booking,
        BookingGalleryMediaService $service,
    ): JsonResponse {
        /** @var array{original_filename: string, upload_token: string, mime_type: string, size_bytes: int, caption?: string|null} $data */
        $data = $request->validated();
        $result = $service->initiate($data, $request->user(), $booking);

        return response()->json([
            'media' => $this->payload($result['media']),
            'upload' => $result['upload'],
        ], 201);
    }

    public function signPart(
        SignGlobalMediaPartRequest $request,
        Booking $booking,
        GalleryMedia $media,
        BookingGalleryMediaService $service,
        GalleryDirectUploadService $directUpload,
    ): JsonResponse {
        $service->assertApproved($booking);
        $service->assertOwnedBy($media, $booking);
        abort_unless($media->status === GalleryMediaStatus::Processing && $media->upload_mode === 'MULTIPART' && $media->upload_id, 404);
        $part = (int) $request->validated('part_number');
        $expected = (int) ceil($media->size_bytes / (int) config('gallery.multipart_part_size_bytes'));
        abort_if($part > $expected, 422, 'Nomor bagian melebihi ukuran file.');

        return response()->json($directUpload->signPart($media, $part));
    }

    public function complete(
        CompleteGlobalMediaUploadRequest $request,
        Booking $booking,
        GalleryMedia $media,
        BookingGalleryMediaService $service,
    ): JsonResponse {
        /** @var array<int, array{part_number: int, etag: string}> $parts */
        $parts = $request->validated('parts');
        $media = $service->complete($booking, $media, $parts);

        return response()->json(['media' => $this->payload($media)]);
    }

    /** @return array<string, mixed> */
    private function payload(GalleryMedia $media): array
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
