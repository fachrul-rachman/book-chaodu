<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGallerySettingsRequest;
use App\Services\GallerySettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GallerySettingController extends Controller
{
    public function edit(GallerySettingService $service): Response
    {
        $settings = $service->values();

        return Inertia::render('admin/gallery-settings/edit', [
            'settings' => [
                'event_name' => $settings['event_name'],
                'event_date' => $settings['event_date'],
                'album_title' => $settings['album_title'],
                'empty_state_text' => $settings['empty_state_text'],
                'wallpaper_url' => $settings['wallpaper_path']
                    ? route('admin.gallery-settings.wallpaper')
                    : null,
            ],
            'wallpaper_max_megabytes' => (int) ceil((int) config('gallery.photo_max_bytes') / 1024 / 1024),
        ]);
    }

    public function update(UpdateGallerySettingsRequest $request, GallerySettingService $service): RedirectResponse
    {
        /** @var array{event_name:string,event_date:string,album_title:string,empty_state_text:string} $validated */
        $validated = $request->safe()->only([
            'event_name', 'event_date', 'album_title', 'empty_state_text',
        ]);
        $service->update($validated, $request->file('wallpaper'));

        return to_route('admin.gallery-settings.edit')
            ->with('status', 'Pengaturan album galeri berhasil disimpan.');
    }
}
