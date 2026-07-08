<?php

namespace App\Http\Controllers\Printer;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\PrayerPaper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrayerPaperFileController extends Controller
{
    public function __invoke(PrayerPaper $prayerPaper): RedirectResponse|StreamedResponse
    {
        abort_if($prayerPaper->booking?->status !== BookingStatus::Approved, 404);
        abort_if(blank($prayerPaper->file_path), 404);

        $disk = Storage::disk((string) config('phase5.storage_disk'));
        $driver = config('filesystems.disks.'.config('phase5.storage_disk').'.driver');

        if ($driver === 's3') {
            return redirect()->away(
                $disk->temporaryUrl($prayerPaper->file_path, now()->addMinutes(10)),
            );
        }

        return $disk->response($prayerPaper->file_path);
    }
}
