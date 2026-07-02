<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrayerPaperType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PrayerPaperRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrayerPaperPreviewController extends Controller
{
    public function __construct(
        private readonly PrayerPaperRenderer $renderer,
    ) {}

    public function __invoke(Request $request): Response
    {
        $type = $this->resolveType($request->query('type'));
        $inputs = $this->inputs($request);

        return Inertia::render('admin/prayer-paper-preview/index', [
            'paper_type' => $type->value,
            'types' => [
                ['value' => PrayerPaperType::A->value, 'label' => 'Kertas Doa'],
                ['value' => PrayerPaperType::B->value, 'label' => 'Kertas Hio'],
            ],
            'inputs' => $inputs,
            'previews' => $this->previews($type, $inputs),
        ]);
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
     *     name_1_indonesian:string,
     *     name_1_mandarin:string,
     *     name_2_indonesian:string,
     *     name_2_mandarin:string,
     *     incense_indonesian:string,
     *     incense_mandarin:string
     * }
     */
    private function inputs(Request $request): array
    {
        return [
            'name_1_indonesian' => trim((string) $request->query('name_1_indonesian', '')),
            'name_1_mandarin' => trim((string) $request->query('name_1_mandarin', '')),
            'name_2_indonesian' => trim((string) $request->query('name_2_indonesian', '')),
            'name_2_mandarin' => trim((string) $request->query('name_2_mandarin', '')),
            'incense_indonesian' => trim((string) $request->query('incense_indonesian', '')),
            'incense_mandarin' => trim((string) $request->query('incense_mandarin', '')),
        ];
    }

    /**
     * @param  array{
     *     name_1_indonesian:string,
     *     name_1_mandarin:string,
     *     name_2_indonesian:string,
     *     name_2_mandarin:string,
     *     incense_indonesian:string,
     *     incense_mandarin:string
     * }  $inputs
     * @return array<int, array{label:string,image_url:string,download_url:string}>
     */
    private function previews(PrayerPaperType $type, array $inputs): array
    {
        $rows = [];
        $entries = $this->entriesForType($type, $inputs);

        foreach ($entries as $index => $entry) {
            $rendered = $this->renderer->render(Booking::make(), $type, [$entry]);
            $rows[] = [
                'label' => $type === PrayerPaperType::A
                    ? 'Kertas Doa '.($index + 1)
                    : 'Kertas Hio',
                'image_url' => 'data:'.$rendered['content_type'].';base64,'.base64_encode($rendered['content']),
                'download_url' => route('admin.prayer-paper-preview.download', array_merge(
                    ['type' => $type->value, 'index' => $index + 1],
                    $inputs,
                )),
            ];
        }

        return $rows;
    }

    /**
     * @param  array{
     *     name_1_indonesian:string,
     *     name_1_mandarin:string,
     *     name_2_indonesian:string,
     *     name_2_mandarin:string,
     *     incense_indonesian:string,
     *     incense_mandarin:string
     * }  $inputs
     * @return array<int, array{text:string, vertical:bool}>
     */
    private function entriesForType(PrayerPaperType $type, array $inputs): array
    {
        if ($type === PrayerPaperType::B) {
            $entry = $this->nameEntry($inputs['incense_indonesian'], $inputs['incense_mandarin']);

            return $entry ? [$entry] : [];
        }

        return array_values(array_filter([
            $this->nameEntry($inputs['name_1_indonesian'], $inputs['name_1_mandarin']),
            $this->nameEntry($inputs['name_2_indonesian'], $inputs['name_2_mandarin']),
        ]));
    }

    /**
     * @return array{text:string, vertical:bool}|null
     */
    private function nameEntry(string $indonesianName, string $mandarinName): ?array
    {
        $mandarin = trim($mandarinName);
        $indonesian = trim($indonesianName);
        $display = $mandarin !== '' ? $mandarin : $indonesian;

        if ($display === '') {
            return null;
        }

        return [
            'text' => $display,
            'vertical' => $mandarin !== '',
        ];
    }
}
