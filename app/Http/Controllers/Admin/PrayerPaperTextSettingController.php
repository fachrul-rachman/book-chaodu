<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePrayerPaperTextSettingsRequest;
use App\Services\PrayerPaperTextSettingService;
use Illuminate\Http\RedirectResponse;

class PrayerPaperTextSettingController extends Controller
{
    public function __invoke(
        UpdatePrayerPaperTextSettingsRequest $request,
        PrayerPaperTextSettingService $settingService,
    ): RedirectResponse {
        $settingService->save($request->validated());

        return back()->with('status', 'Ukuran tulisan kertas berhasil disimpan.');
    }
}
