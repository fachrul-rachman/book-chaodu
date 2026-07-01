<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofFileController extends Controller
{
    public function __invoke(Booking $booking): RedirectResponse|StreamedResponse
    {
        abort_if(blank($booking->payment?->proof_path), 404);

        $diskName = (string) config('phase3.private_upload_disk');
        $disk = Storage::disk($diskName);
        $driver = config('filesystems.disks.'.$diskName.'.driver');

        if ($driver === 's3') {
            return redirect()->away(
                $disk->temporaryUrl($booking->payment->proof_path, now()->addMinutes(10)),
            );
        }

        return $disk->response($booking->payment->proof_path);
    }
}
