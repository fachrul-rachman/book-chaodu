<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrayerPaperType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePrayerPaperMarkingRequest;
use App\Models\AppSetting;
use App\Services\PrayerPaperTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PrayerPaperMarkingController extends Controller
{
    public function __construct(
        private readonly PrayerPaperTemplateService $templateService,
    ) {}

    public function edit(Request $request): Response
    {
        $type = $this->resolveType($request->query('type'));

        return Inertia::render('admin/prayer-paper-marking/edit', [
            'types' => [
                ['value' => PrayerPaperType::A->value, 'label' => 'Kertas Doa'],
                ['value' => PrayerPaperType::B->value, 'label' => 'Kertas Hio'],
            ],
            'marking_type' => $type->value,
            'marking' => $this->markingPayload($type),
        ]);
    }

    public function update(UpdatePrayerPaperMarkingRequest $request): RedirectResponse
    {
        $type = PrayerPaperType::from((string) $request->validated()['type']);
        $settings = AppSetting::getMany([
            $this->templateService->pathKey($type),
            $this->templateService->diskKey($type),
        ]);

        $diskName = 'local';
        $pathKey = $this->templateService->pathKey($type);
        $diskKey = $this->templateService->diskKey($type);
        $markingKey = $this->templateService->markingKey($type);
        $path = $settings[$pathKey];

        if ($request->hasFile('template_image')) {
            $newPath = $request->file('template_image')->store('prayer-paper-templates', $diskName);

            if (! is_string($newPath)) {
                return back()->withErrors([
                    'template_image' => 'Gambar template tidak berhasil disimpan.',
                ]);
            }

            if ($path) {
                Storage::disk($diskName)->delete($path);
            }

            $path = $newPath;
        }

        AppSetting::putMany([
            $pathKey => $path,
            $diskKey => $diskName,
            $markingKey => json_encode([
                'canvas_width' => (int) $request->validated()['canvas_width'],
                'canvas_height' => (int) $request->validated()['canvas_height'],
                'markers' => $request->validated()['markers'],
            ], JSON_THROW_ON_ERROR),
        ]);

        return to_route('admin.prayer-paper-marking.edit', ['type' => $type->value])
            ->with('status', $type === PrayerPaperType::A
                ? 'Tanda posisi Kertas Doa berhasil disimpan.'
                : 'Tanda posisi Kertas Hio berhasil disimpan.');
    }

    private function resolveType(mixed $value): PrayerPaperType
    {
        if (is_string($value) && in_array($value, [PrayerPaperType::A->value, PrayerPaperType::B->value], true)) {
            return PrayerPaperType::from($value);
        }

        return PrayerPaperType::A;
    }

    /**
     * @return array{
     *     image_url:string|null,
     *     canvas_width:int,
     *     canvas_height:int,
     *     markers: array{
     *         single: array{x:float|int,y:float|int,width:float|int,height:float|int},
     *         left: array{x:float|int,y:float|int,width:float|int,height:float|int},
     *         right: array{x:float|int,y:float|int,width:float|int,height:float|int}
     *     },
     *     has_image:bool,
     *     storage_disk:string,
     *     title:string,
     *     show_three_markers:bool
     * }
     */
    private function markingPayload(PrayerPaperType $type): array
    {
        $settings = AppSetting::getMany($this->templateService->keysFor($type));
        $marking = $this->templateService->decodeMarking(
            $settings[$this->templateService->markingKey($type)] ?? null,
            $type,
        );
        $diskName = $settings[$this->templateService->diskKey($type)] ?: 'local';
        $imagePath = $settings[$this->templateService->pathKey($type)];

        return [
            'image_url' => $imagePath ? route('admin.prayer-paper-marking.image.show', ['type' => $type->value]) : null,
            'canvas_width' => $marking['canvas_width'],
            'canvas_height' => $marking['canvas_height'],
            'markers' => $marking['markers'],
            'has_image' => filled($imagePath),
            'storage_disk' => $diskName,
            'title' => $type === PrayerPaperType::A ? 'Kertas Doa' : 'Kertas Hio',
            'show_three_markers' => $type === PrayerPaperType::A,
        ];
    }
}
