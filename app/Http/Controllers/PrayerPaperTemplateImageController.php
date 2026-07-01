<?php

namespace App\Http\Controllers;

use App\Enums\PrayerPaperType;
use App\Models\AppSetting;
use App\Services\PrayerPaperTemplateService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrayerPaperTemplateImageController extends Controller
{
    public function __construct(
        private readonly PrayerPaperTemplateService $templateService,
    ) {}

    public function __invoke(string $type): StreamedResponse
    {
        $paperType = PrayerPaperType::from($type);
        $settings = AppSetting::getMany([
            $this->templateService->pathKey($paperType),
            $this->templateService->diskKey($paperType),
        ]);

        $path = $settings[$this->templateService->pathKey($paperType)];
        $diskName = $settings[$this->templateService->diskKey($paperType)] ?: 'local';

        abort_if(blank($path), 404);

        return Storage::disk($diskName)->response((string) $path);
    }
}
