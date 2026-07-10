<?php

namespace App\Services;

use App\Enums\PrayerPaperType;
use App\Models\AppSetting;
use App\Models\Package;

class PublicBookingCatalog
{
    /**
     * @return array{
     *     packages: array<int, array<string, mixed>>,
     *     availability: array<string, mixed>,
     *     payment: array<string, mixed>,
     *     limits: array{
     *         upload_max_mb:int,
     *         ocr_upload_max_mb:int
     *     },
     *     preview: array<string, array<string, mixed>>
     * }
     */
    public function data(): array
    {
        $availability = app(AvailabilityService::class)->summary();
        $packageAvailability = collect($availability['packages'])->keyBy('code');
        $templateService = app(PrayerPaperTemplateService::class);
        $virtualAccountService = app(VirtualAccountService::class);
        $prayerTemplate = $templateService->previewFor(PrayerPaperType::A);
        $incenseTemplate = $templateService->previewFor(PrayerPaperType::B);
        $settings = AppSetting::getMany([
            'upload_max_mb',
        ]);

        return [
            'packages' => Package::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->map(fn (Package $package): array => [
                    'code' => $package->code->value,
                    'name' => $package->name,
                    'description' => $package->description,
                    'price' => $package->price,
                    'meal_quota' => $package->meal_quota,
                    'requires_table' => $package->requires_table,
                    'requires_incense' => $package->requires_incense,
                    'available' => (bool) ($packageAvailability[$package->code->value]['available'] ?? false),
                    'unavailable_reason' => $packageAvailability[$package->code->value]['reason'] ?? null,
                    'image_url' => $package->image_path
                        ? route('packages.image.show', $package)
                        : null,
                ])
                ->values()
                ->all(),
            'availability' => $availability,
            'payment' => [
                ...$virtualAccountService->paymentIdentity(),
                'virtual_account_mode' => $virtualAccountService->mode(),
                'accounts_by_package' => $virtualAccountService->packageAccounts(),
                'hold_minutes' => $virtualAccountService->holdMinutes(),
            ],
            'limits' => [
                'upload_max_mb' => max(1, (int) ($settings['upload_max_mb'] ?? config('phase3.upload_max_mb'))),
                'ocr_upload_max_mb' => max(1, (int) config('phase4.ocr_upload_max_mb')),
            ],
            'preview' => [
                ...config('phase4.preview'),
                'render_url' => route('prayer-paper-preview.image'),
                'prayer' => [
                    ...config('phase4.preview.prayer'),
                    'image_url' => $prayerTemplate['image_url'],
                    'canvas_width' => $prayerTemplate['canvas_width'],
                    'canvas_height' => $prayerTemplate['canvas_height'],
                    'markers' => $prayerTemplate['markers'],
                ],
                'incense' => [
                    ...config('phase4.preview.incense'),
                    'image_url' => $incenseTemplate['image_url'],
                    'canvas_width' => $incenseTemplate['canvas_width'],
                    'canvas_height' => $incenseTemplate['canvas_height'],
                    'markers' => $incenseTemplate['markers'],
                ],
            ],
        ];
    }
}
