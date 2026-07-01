<?php

namespace App\Services;

use App\Enums\PrayerPaperType;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

class PrayerPaperTemplateService
{
    /**
     * @return array{
     *     image_url:string|null,
     *     image_data_uri:string|null,
     *     canvas_width:int,
     *     canvas_height:int,
     *     markers: array{
     *         single: array{x:float|int,y:float|int,width:float|int,height:float|int},
     *         left: array{x:float|int,y:float|int,width:float|int,height:float|int},
     *         right: array{x:float|int,y:float|int,width:float|int,height:float|int}
     *     }
     * }
     */
    public function previewFor(PrayerPaperType $type): array
    {
        $settings = AppSetting::getMany($this->keysFor($type));
        $path = $settings[$this->pathKey($type)];
        $diskName = $settings[$this->diskKey($type)] ?: 'local';
        $marking = $this->decodeMarking($settings[$this->markingKey($type)] ?? null, $type);

        return [
            'image_url' => filled($path)
                ? route('public.prayer-paper-template.image.show', ['type' => $type->value])
                : null,
            'image_data_uri' => filled($path) ? $this->imageDataUri($diskName, (string) $path) : null,
            'canvas_width' => $marking['canvas_width'],
            'canvas_height' => $marking['canvas_height'],
            'markers' => $marking['markers'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function keysFor(PrayerPaperType $type): array
    {
        return [
            $this->pathKey($type),
            $this->diskKey($type),
            $this->markingKey($type),
        ];
    }

    public function pathKey(PrayerPaperType $type): string
    {
        return match ($type) {
            PrayerPaperType::A => 'prayer_paper_a_template_image_path',
            PrayerPaperType::B => 'prayer_paper_b_template_image_path',
        };
    }

    public function diskKey(PrayerPaperType $type): string
    {
        return match ($type) {
            PrayerPaperType::A => 'prayer_paper_a_template_image_disk',
            PrayerPaperType::B => 'prayer_paper_b_template_image_disk',
        };
    }

    public function markingKey(PrayerPaperType $type): string
    {
        return match ($type) {
            PrayerPaperType::A => 'prayer_paper_a_marking',
            PrayerPaperType::B => 'prayer_paper_b_marking',
        };
    }

    private function imageDataUri(string $diskName, string $path): ?string
    {
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $mimeType = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    /**
     * @return array{
     *     canvas_width:int,
     *     canvas_height:int,
     *     markers: array{
     *         single: array{x:float|int,y:float|int,width:float|int,height:float|int},
     *         left: array{x:float|int,y:float|int,width:float|int,height:float|int},
     *         right: array{x:float|int,y:float|int,width:float|int,height:float|int}
     *     }
     * }
     */
    public function decodeMarking(?string $value, PrayerPaperType $type): array
    {
        $default = $this->defaultMarking($type);

        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return $default;
        }

        return [
            'canvas_width' => (int) ($decoded['canvas_width'] ?? $default['canvas_width']),
            'canvas_height' => (int) ($decoded['canvas_height'] ?? $default['canvas_height']),
            'markers' => [
                'single' => $this->marker($decoded['markers']['single'] ?? null, $default['markers']['single']),
                'left' => $this->marker($decoded['markers']['left'] ?? null, $default['markers']['left']),
                'right' => $this->marker($decoded['markers']['right'] ?? null, $default['markers']['right']),
            ],
        ];
    }

    /**
     * @return array{
     *     canvas_width:int,
     *     canvas_height:int,
     *     markers: array{
     *         single: array{x:float|int,y:float|int,width:float|int,height:float|int},
     *         left: array{x:float|int,y:float|int,width:float|int,height:float|int},
     *         right: array{x:float|int,y:float|int,width:float|int,height:float|int}
     *     }
     * }
     */
    public function defaultMarking(PrayerPaperType $type): array
    {
        return match ($type) {
            PrayerPaperType::A => [
                'canvas_width' => 1121,
                'canvas_height' => 3437,
                'markers' => [
                    'single' => ['x' => 470, 'y' => 980, 'width' => 180, 'height' => 1200],
                    'left' => ['x' => 360, 'y' => 980, 'width' => 150, 'height' => 1200],
                    'right' => ['x' => 610, 'y' => 980, 'width' => 150, 'height' => 1200],
                ],
            ],
            PrayerPaperType::B => [
                'canvas_width' => 900,
                'canvas_height' => 1400,
                'markers' => [
                    'single' => ['x' => 390, 'y' => 430, 'width' => 120, 'height' => 500],
                    'left' => ['x' => 390, 'y' => 430, 'width' => 120, 'height' => 500],
                    'right' => ['x' => 390, 'y' => 430, 'width' => 120, 'height' => 500],
                ],
            ],
        };
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $default
     * @return array{x:float|int,y:float|int,width:float|int,height:float|int}
     */
    private function marker(?array $value, array $default): array
    {
        if (! is_array($value)) {
            return $default;
        }

        return [
            'x' => is_numeric($value['x'] ?? null) ? (float) $value['x'] : $default['x'],
            'y' => is_numeric($value['y'] ?? null) ? (float) $value['y'] : $default['y'],
            'width' => is_numeric($value['width'] ?? null) ? (float) $value['width'] : $default['width'],
            'height' => is_numeric($value['height'] ?? null) ? (float) $value['height'] : $default['height'],
        ];
    }
}
