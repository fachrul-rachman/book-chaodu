<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateGallerySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxKilobytes = max(1, (int) ceil((int) config('gallery.photo_max_bytes') / 1024));
        $wallpaperWidth = (int) config('gallery.wallpaper_width');
        $wallpaperHeight = (int) config('gallery.wallpaper_height');

        return [
            'event_name' => ['required', 'string', 'max:120'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'album_title' => ['required', 'string', 'max:160'],
            'empty_state_text' => ['required', 'string', 'max:240'],
            'wallpaper' => [
                'nullable',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max($maxKilobytes)
                    ->dimensions(Rule::dimensions()->width($wallpaperWidth)->height($wallpaperHeight)),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'wallpaper.dimensions' => sprintf(
                'Wallpaper album harus berukuran tepat %d x %d piksel.',
                (int) config('gallery.wallpaper_width'),
                (int) config('gallery.wallpaper_height'),
            ),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'event_name' => 'nama acara',
            'event_date' => 'tanggal acara',
            'album_title' => 'judul album',
            'empty_state_text' => 'teks album kosong',
            'wallpaper' => 'wallpaper album',
        ];
    }
}
