<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrayerPaperType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PrayerPaperRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrayerPaperPreviewDownloadController extends Controller
{
    public function __construct(
        private readonly PrayerPaperRenderer $renderer,
    ) {}

    public function __invoke(Request $request): Response
    {
        $type = $this->resolveType($request->query('type'));
        $index = max(1, (int) $request->query('index', 1));
        $entries = $this->entriesForType($type, $request);
        $entry = $entries[$index - 1] ?? null;

        abort_if($entry === null, 404);

        $rendered = $this->renderer->render(Booking::make(), $type, [$entry]);
        $fileName = $type === PrayerPaperType::A
            ? 'kertas-doa-'.$index.'.'.$rendered['extension']
            : 'kertas-hio.'.$rendered['extension'];

        return response($rendered['content'], 200, [
            'Content-Type' => $rendered['content_type'],
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
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
     * @return array<int, array{text:string, vertical:bool}>
     */
    private function entriesForType(PrayerPaperType $type, Request $request): array
    {
        if ($type === PrayerPaperType::B) {
            $entry = $this->nameEntry(
                (string) $request->query('incense_indonesian', ''),
                (string) $request->query('incense_mandarin', ''),
            );

            return $entry ? [$entry] : [];
        }

        return array_values(array_filter([
            $this->nameEntry(
                (string) $request->query('name_1_indonesian', ''),
                (string) $request->query('name_1_mandarin', ''),
            ),
            $this->nameEntry(
                (string) $request->query('name_2_indonesian', ''),
                (string) $request->query('name_2_mandarin', ''),
            ),
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
