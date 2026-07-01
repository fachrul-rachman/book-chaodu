<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrayerPaperType;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\PrayerPaperTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrayerPaperMarkingImageController extends Controller
{
    public function __construct(
        private readonly PrayerPaperTemplateService $templateService,
    ) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $type = is_string($request->query('type')) && in_array($request->query('type'), [PrayerPaperType::A->value, PrayerPaperType::B->value], true)
            ? PrayerPaperType::from((string) $request->query('type'))
            : PrayerPaperType::A;

        $settings = AppSetting::getMany([
            $this->templateService->pathKey($type),
            $this->templateService->diskKey($type),
        ]);

        $path = $settings[$this->templateService->pathKey($type)];
        $diskName = $settings[$this->templateService->diskKey($type)] ?: 'local';

        abort_if(blank($path), 404);

        return Storage::disk($diskName)->response((string) $path);
    }
}
