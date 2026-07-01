<?php

namespace App\Services;

use App\Enums\PrayerPaperType;
use App\Models\Booking;

class PrayerPaperRenderer
{
    public function __construct(
        private readonly PrayerPaperTemplateService $templateService,
    ) {}

    /**
     * @param  array<int, array{text:string, vertical:bool}>  $names
     */
    public function render(Booking $booking, PrayerPaperType $type, array $names): string
    {
        return match ($type) {
            PrayerPaperType::A => $this->renderPrayerPaperA($names),
            PrayerPaperType::B => $this->renderPrayerPaperB($names),
        };
    }

    private function escapeSvgText(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @param  array<int, array{text:string, vertical:bool}>  $names
     */
    private function renderPrayerPaperA(array $names): string
    {
        $template = $this->templateService->previewFor(PrayerPaperType::A);
        $entry = $names[0] ?? null;
        $nameNode = $entry
            ? $this->renderMarkerText(
                $entry['text'],
                $entry['vertical'],
                $template['markers']['single'],
                '#000000',
                true,
            )
            : '';

        $backgroundNode = $template['image_data_uri']
            ? sprintf(
                '<image href="%s" x="0" y="0" width="%d" height="%d" preserveAspectRatio="none" />',
                $this->escapeSvgText($template['image_data_uri']),
                (int) $template['canvas_width'],
                (int) $template['canvas_height'],
            )
            : '<rect width="100%" height="100%" fill="#fff7e6" />';

        return sprintf(
            <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
  %s
  %s
</svg>
SVG,
            (int) $template['canvas_width'],
            (int) $template['canvas_height'],
            (int) $template['canvas_width'],
            (int) $template['canvas_height'],
            $backgroundNode,
            $nameNode,
        );
    }

    /**
     * @param  array<int, array{text:string, vertical:bool}>  $names
     */
    private function renderPrayerPaperB(array $names): string
    {
        $template = $this->templateService->previewFor(PrayerPaperType::B);
        $entry = $names[0] ?? null;
        $nameNode = $entry
            ? $this->renderMarkerText(
                $entry['text'],
                $entry['vertical'],
                $template['markers']['single'],
                '#E82C2A',
                false,
            )
            : '';

        $backgroundNode = $template['image_data_uri']
            ? sprintf(
                '<image href="%s" x="0" y="0" width="%d" height="%d" preserveAspectRatio="none" />',
                $this->escapeSvgText($template['image_data_uri']),
                (int) $template['canvas_width'],
                (int) $template['canvas_height'],
            )
            : '<rect width="100%" height="100%" fill="#fff7e6" />';

        return sprintf(
            <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
  %s
  %s
</svg>
SVG,
            (int) $template['canvas_width'],
            (int) $template['canvas_height'],
            (int) $template['canvas_width'],
            (int) $template['canvas_height'],
            $backgroundNode,
            $nameNode,
        );
    }

    /**
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function renderMarkerText(
        string $text,
        bool $vertical,
        array $marker,
        string $fill,
        bool $rotateIndonesian,
    ): string {
        return $vertical
            ? $this->renderVerticalText($text, $marker, $fill)
            : ($rotateIndonesian
                ? $this->renderRotatedText($text, $marker, $fill)
                : $this->renderHorizontalText($text, $marker, $fill));
    }

    /**
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function renderVerticalText(string $text, array $marker, string $fill): string
    {
        $characters = preg_split('//u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($characters) || $characters === []) {
            return '';
        }

        $count = count($characters);
        $x = (float) $marker['x'] + ((float) $marker['width'] / 2);
        $top = (float) $marker['y'];
        $height = (float) $marker['height'];
        $width = (float) $marker['width'];
        $fontSize = max(14.0, min($width * 0.72, ($height * 0.84) / max($count + 0.45, 1)));
        $gap = $height / ($count + 1);
        $nodes = [];

        foreach ($characters as $index => $character) {
            $nodes[] = sprintf(
                '<text x="%.2f" y="%.2f" text-anchor="middle" dominant-baseline="middle" font-size="%.2f" fill="%s" font-family="Noto Serif CJK SC, SimSun, serif">%s</text>',
                $x,
                $top + ($gap * ($index + 1)),
                $fontSize,
                $fill,
                $this->escapeSvgText((string) $character),
            );
        }

        return implode("\n", $nodes);
    }

    /**
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function renderRotatedText(string $text, array $marker, string $fill): string
    {
        $centerX = (float) $marker['x'] + ((float) $marker['width'] / 2);
        $centerY = (float) $marker['y'] + ((float) $marker['height'] / 2);
        $textLength = max(40.0, (float) $marker['height'] * 0.88);
        $characterCount = max(1, mb_strlen(trim($text)));
        $fontSize = max(
            12.0,
            min(
                (float) $marker['width'] * 0.68,
                ($textLength * 0.84) / max($characterCount * 0.78, 1),
            ),
        );

        return sprintf(
            '<text x="%.2f" y="%.2f" text-anchor="middle" dominant-baseline="middle" transform="rotate(90 %.2f %.2f)" textLength="%.2f" lengthAdjust="spacingAndGlyphs" font-size="%.2f" fill="%s" font-family="Arial, sans-serif">%s</text>',
            $centerX,
            $centerY,
            $centerX,
            $centerY,
            $textLength,
            $fontSize,
            $fill,
            $this->escapeSvgText($text),
        );
    }

    /**
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function renderHorizontalText(string $text, array $marker, string $fill): string
    {
        $centerX = (float) $marker['x'] + ((float) $marker['width'] / 2);
        $centerY = (float) $marker['y'] + ((float) $marker['height'] / 2);
        $textLength = max(40.0, (float) $marker['width'] * 0.88);
        $characterCount = max(1, mb_strlen(trim($text)));
        $fontSize = max(
            12.0,
            min(
                (float) $marker['height'] * 0.68,
                ($textLength * 0.84) / max($characterCount * 0.74, 1),
            ),
        );

        return sprintf(
            '<text x="%.2f" y="%.2f" text-anchor="middle" dominant-baseline="middle" textLength="%.2f" lengthAdjust="spacingAndGlyphs" font-size="%.2f" fill="%s" font-family="Arial, sans-serif">%s</text>',
            $centerX,
            $centerY,
            $textLength,
            $fontSize,
            $fill,
            $this->escapeSvgText($text),
        );
    }
}
