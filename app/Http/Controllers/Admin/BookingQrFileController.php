<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingQrFileController extends Controller
{
    public function __invoke(Booking $booking): RedirectResponse|StreamedResponse
    {
        abort_if(blank($booking->approvalIntegration?->qr_image_path), 404);

        $diskName = (string) config('phase7.storage_disk');
        $disk = Storage::disk($diskName);
        $driver = config('filesystems.disks.'.$diskName.'.driver');

        if ($driver === 's3') {
            return redirect()->away(
                $disk->temporaryUrl($booking->approvalIntegration->qr_image_path, now()->addMinutes(10)),
            );
        }

        return $disk->response($booking->approvalIntegration->qr_image_path);
    }
}
