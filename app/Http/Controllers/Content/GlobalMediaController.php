<?php

namespace App\Http\Controllers\Content;

use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ChangeGlobalMediaStatusRequest;
use App\Http\Requests\Content\UpdateGlobalMediaRequest;
use App\Models\GalleryMedia;
use App\Services\GlobalGalleryMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GlobalMediaController extends Controller
{
    public function index(): Response
    {
        $media = GalleryMedia::query()
            ->where('scope', GalleryMediaScope::Global)
            ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (GalleryMedia $item): array => $this->payload($item));

        return Inertia::render('content/global-media/index', [
            'media' => $media,
            'limits' => [
                'photoMb' => (int) config('gallery.photo_max_bytes') / 1024 / 1024,
                'videoMb' => (int) config('gallery.video_max_bytes') / 1024 / 1024,
                'captionCharacters' => (int) config('gallery.caption_max_characters'),
            ],
            'upload' => [
                'singleMaxBytes' => (int) config('gallery.single_upload_max_bytes'),
                'partSizeBytes' => (int) config('gallery.multipart_part_size_bytes'),
            ],
        ]);
    }

    public function update(UpdateGlobalMediaRequest $request, GalleryMedia $media): JsonResponse
    {
        abort_unless($media->scope === GalleryMediaScope::Global, 404);
        $caption = $request->validated('caption');
        $media->update(['caption' => is_string($caption) && trim($caption) !== '' ? trim($caption) : null]);

        return response()->json(['media' => $this->payload($media->refresh())]);
    }

    public function destroy(
        GalleryMedia $media,
        GlobalGalleryMediaService $service,
    ): JsonResponse {
        $service->delete($media, request()->user());

        return response()->json(['message' => 'Media berhasil dihapus permanen.']);
    }

    public function changeStatus(ChangeGlobalMediaStatusRequest $request, GalleryMedia $media): JsonResponse
    {
        abort_unless(
            $media->scope === GalleryMediaScope::Global
            && in_array($media->status, [GalleryMediaStatus::Ready, GalleryMediaStatus::Hidden], true),
            404,
        );
        $media->update(['status' => GalleryMediaStatus::from((string) $request->validated('status'))]);

        return response()->json(['media' => $this->payload($media->refresh())]);
    }

    /** @return array<string, mixed> */
    private function payload(GalleryMedia $media): array
    {
        return [
            'id' => $media->id,
            'uuid' => $media->uuid,
            'type' => $media->media_type->value,
            'status' => $media->status->value,
            'filename' => $media->original_filename,
            'mimeType' => $media->mime_type,
            'sizeBytes' => $media->size_bytes,
            'caption' => $media->caption,
            'sortOrder' => $media->sort_order,
            'previewUrl' => $this->temporaryUrl($media),
            'createdAt' => $media->created_at?->toIso8601String(),
            'errorMessage' => $media->error_message,
        ];
    }

    private function temporaryUrl(GalleryMedia $media): ?string
    {
        $path = $media->thumbnail_path ?: $media->original_path;

        try {
            $disk = Storage::disk($media->storage_disk);

            return $disk->exists($path) ? $disk->temporaryUrl($path, now()->addMinutes(30)) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
