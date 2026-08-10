<?php

namespace App\Http\Controllers\Content;

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ChangeGlobalMediaStatusRequest;
use App\Http\Requests\Content\UpdateGlobalMediaRequest;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Services\BookingGalleryMediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CustomerMediaController extends Controller
{
    public function index(Request $request, BookingGalleryMediaService $service): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:100'],
            'booking' => ['nullable', 'integer'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $results = collect();

        if ($search !== '') {
            $pattern = '%'.mb_strtolower($search).'%';
            $results = Booking::query()
                ->where('status', BookingStatus::Approved)
                ->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw('LOWER(booking_number) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(customer_name) LIKE ?', [$pattern]);
                })
                ->with(['tableSlots:id,booking_id,code', 'incenseSlots:id,booking_id,number'])
                ->withCount('galleryMedia')
                ->orderByDesc('approved_at')
                ->limit(20)
                ->get()
                ->map(fn (Booking $booking): array => $this->bookingPayload($booking));
        }

        $selected = null;
        $media = collect();
        $selectedId = isset($validated['booking']) ? (int) $validated['booking'] : null;

        if (is_int($selectedId)) {
            $booking = Booking::query()
                ->with(['tableSlots:id,booking_id,code', 'incenseSlots:id,booking_id,number'])
                ->withCount('galleryMedia')
                ->findOrFail($selectedId);
            $service->assertApproved($booking);
            $selected = $this->bookingPayload($booking);
            $media = $booking->galleryMedia()
                ->where('scope', GalleryMediaScope::Booking)
                ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (GalleryMedia $item): array => $this->mediaPayload($item));
        }

        return Inertia::render('content/customer-media/index', [
            'results' => $results,
            'selectedBooking' => $selected,
            'media' => $media,
            'filters' => ['q' => $search],
            'limits' => [
                'photoMb' => (int) config('gallery.photo_max_bytes') / 1024 / 1024,
                'videoMb' => (int) config('gallery.video_max_bytes') / 1024 / 1024,
                'captionCharacters' => (int) config('gallery.caption_max_characters'),
            ],
        ]);
    }

    public function update(
        UpdateGlobalMediaRequest $request,
        Booking $booking,
        GalleryMedia $media,
        BookingGalleryMediaService $service,
    ): JsonResponse {
        $service->assertApproved($booking);
        $service->assertOwnedBy($media, $booking);
        $caption = $request->validated('caption');
        $media->update(['caption' => is_string($caption) && trim($caption) !== '' ? trim($caption) : null]);

        return response()->json(['media' => $this->mediaPayload($media->refresh())]);
    }

    public function changeStatus(
        ChangeGlobalMediaStatusRequest $request,
        Booking $booking,
        GalleryMedia $media,
        BookingGalleryMediaService $service,
    ): JsonResponse {
        $service->assertApproved($booking);
        $service->assertOwnedBy($media, $booking);
        abort_unless(in_array($media->status, [GalleryMediaStatus::Ready, GalleryMediaStatus::Hidden], true), 404);
        $media->update(['status' => GalleryMediaStatus::from((string) $request->validated('status'))]);

        return response()->json(['media' => $this->mediaPayload($media->refresh())]);
    }

    public function destroy(
        Booking $booking,
        GalleryMedia $media,
        BookingGalleryMediaService $service,
    ): JsonResponse {
        $service->delete($booking, $media, request()->user());

        return response()->json(['message' => 'Media customer berhasil dihapus permanen.']);
    }

    /** @return array<string, mixed> */
    private function bookingPayload(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'bookingNumber' => $booking->booking_number,
            'customerName' => $booking->customer_name,
            'packageName' => $booking->package_name_snapshot,
            'tableNumber' => $booking->tableSlots->first()?->code,
            'incenseNumber' => ($number = $booking->incenseSlots->first()?->number) !== null ? (string) $number : null,
            'mediaCount' => (int) ($booking->gallery_media_count ?? $booking->galleryMedia()->count()),
        ];
    }

    /** @return array<string, mixed> */
    private function mediaPayload(GalleryMedia $media): array
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
