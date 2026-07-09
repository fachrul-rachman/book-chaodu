<?php

namespace App\Services;

use App\Enums\PrayerPaperType;
use App\Models\Booking;

class PrayerPaperRenderer
{
    private const PRAYER_PRINT_WIDTH_PX = 1121;

    private const PRAYER_PRINT_HEIGHT_PX = 3437;

    private const INCENSE_PRINT_WIDTH_PX = 1122;

    private const INCENSE_PRINT_HEIGHT_PX = 2480;

    /**
     * @var array<int, string>
     */
    private const LATIN_FONT_PATHS = [
        'C:\Windows\Fonts\arial.ttf',
        'C:\Windows\Fonts\NotoSans-Regular.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
        '/usr/share/fonts/opentype/noto/NotoSans-Regular.ttf',
    ];

    /**
     * @var array<int, string>
     */
    private const CJK_FONT_PATHS = [
        'C:\Windows\Fonts\simsun.ttc',
        'C:\Windows\Fonts\msyh.ttc',
        'C:\Windows\Fonts\msjh.ttc',
        '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
        '/usr/share/fonts/opentype/noto/NotoSerifCJK-Regular.ttc',
        '/usr/share/fonts/opentype/noto/NotoSansCJKSC-Regular.otf',
        '/usr/share/fonts/opentype/noto/NotoSerifCJKSC-Regular.otf',
        '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
        '/usr/share/fonts/truetype/arphic/uming.ttc',
        '/usr/share/fonts/truetype/arphic/ukai.ttc',
    ];

    public function __construct(
        private readonly PrayerPaperTemplateService $templateService,
    ) {}

    /**
     * @param  array<int, array{text:string, vertical:bool}>  $names
     * @return array{content:string,content_type:string,extension:string}
     */
    public function render(Booking $booking, PrayerPaperType $type, array $names): array
    {
        return match ($type) {
            PrayerPaperType::A => $this->renderPrayerPaperA($names),
            PrayerPaperType::B => $this->renderPrayerPaperB($names),
        };
    }

    /**
     * @param  array<int, array{text:string, vertical:bool}>  $names
     * @return array{content:string,content_type:string,extension:string}
     */
    private function renderPrayerPaperA(array $names): array
    {
        $template = $this->templateService->previewFor(PrayerPaperType::A);
        $image = $this->createCanvasFromTemplate($template, PrayerPaperType::A);
        $marker = $this->scaleMarker(
            $template['markers']['single'],
            (int) $template['canvas_width'],
            (int) $template['canvas_height'],
            self::PRAYER_PRINT_WIDTH_PX,
            self::PRAYER_PRINT_HEIGHT_PX,
        );

        $entry = $names[0] ?? null;
        if ($entry) {
            $this->drawMarkerText(
                $image,
                PrayerPaperType::A,
                $entry['text'],
                $entry['vertical'],
                $marker,
                '#000000',
                true,
            );
        }

        return [
            'content' => $this->encodePng($image),
            'content_type' => 'image/png',
            'extension' => 'png',
        ];
    }

    /**
     * @param  array<int, array{text:string, vertical:bool}>  $names
     * @return array{content:string,content_type:string,extension:string}
     */
    private function renderPrayerPaperB(array $names): array
    {
        $template = $this->templateService->previewFor(PrayerPaperType::B);
        $image = $this->createCanvasFromTemplate($template, PrayerPaperType::B);
        $marker = $this->scaleMarker(
            $template['markers']['single'],
            (int) $template['canvas_width'],
            (int) $template['canvas_height'],
            self::INCENSE_PRINT_WIDTH_PX,
            self::INCENSE_PRINT_HEIGHT_PX,
        );
        $entry = $names[0] ?? null;

        if ($entry) {
            $this->drawMarkerText(
                $image,
                PrayerPaperType::B,
                $entry['text'],
                $entry['vertical'],
                $marker,
                '#E82C2A',
                false,
            );
        }

        return [
            'content' => $this->encodePng($image),
            'content_type' => 'image/png',
            'extension' => 'png',
        ];
    }

    /**
     * @param  resource|\GdImage  $image
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function drawMarkerText(
        $image,
        PrayerPaperType $type,
        string $text,
        bool $vertical,
        array $marker,
        string $fill,
        bool $rotateIndonesian,
    ): void {
        $displayText = $vertical ? $text : mb_strtoupper($text);

        if ($vertical) {
            $this->drawVerticalText($image, $type, $displayText, $marker, $fill);

            return;
        }

        if ($rotateIndonesian) {
            $this->drawRotatedText($image, $type, $displayText, $marker, $fill);

            return;
        }

        $this->drawHorizontalText($image, $type, $displayText, $marker, $fill);
    }

    /**
     * @param  resource|\GdImage  $image
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function drawVerticalText($image, PrayerPaperType $type, string $text, array $marker, string $fill): void
    {
        $lines = $this->textLines($text);

        if ($lines === []) {
            return;
        }

        $font = $this->fontPath(true);
        $width = (float) $marker['width'];
        $height = (float) $marker['height'];
        $maxCount = max(array_map(
            static fn (string $line): int => max(mb_strlen($line), 1),
            $lines,
        ));
        $fontSize = max(
            12.0,
            min(
                $width * 0.58,
                $height / max(($maxCount * 1.2), 1),
            ),
        ) * $this->fontScale($type, 'vertical');
        $lineHeight = $fontSize * $this->lineHeight($type, 'vertical', 1.38);
        $stackHeight = ($lineHeight * max($maxCount - 1, 0)) + $fontSize;
        $columnGap = max(
            $fontSize * $this->columnGapScale($type, 'vertical', 0.72),
            $width * 0.18,
        );
        $totalWidth = $fontSize + ($columnGap * max(count($lines) - 1, 0));
        $startX = (float) $marker['x'] + ($width / 2) - ($totalWidth / 2) + ($fontSize / 2);
        $startY = (float) $marker['y'] + ($height / 2) - ($stackHeight / 2) + ($fontSize / 2);

        foreach ($lines as $lineIndex => $line) {
            $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);

            if (! is_array($characters) || $characters === []) {
                continue;
            }

            foreach ($characters as $index => $character) {
                $this->drawCenteredText(
                    $image,
                    (string) $character,
                    $font,
                    0,
                    $fontSize,
                    $startX + ($columnGap * $lineIndex),
                    $startY + ($lineHeight * $index),
                    $fill,
                );
            }
        }
    }

    /**
     * @param  resource|\GdImage  $image
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function drawRotatedText($image, PrayerPaperType $type, string $text, array $marker, string $fill): void
    {
        $font = $this->fontPath(false);
        $fontSize = $this->estimateRotatedPreviewFontSize($type, $marker, $text);

        $this->drawCenteredText(
            $image,
            trim($text),
            $font,
            90,
            $fontSize,
            (float) $marker['x'] + ((float) $marker['width'] / 2),
            (float) $marker['y'] + ((float) $marker['height'] / 2),
            $fill,
        );
    }

    /**
     * @param  resource|\GdImage  $image
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function drawHorizontalText($image, PrayerPaperType $type, string $text, array $marker, string $fill): void
    {
        $font = $this->fontPath(false);
        $lines = $this->textLines($text);

        if ($lines === []) {
            return;
        }

        $fontSize = $this->estimateHorizontalMultilineFontSize($type, $marker, $lines);
        $lineHeight = $fontSize * $this->lineHeight($type, 'horizontal', 1.28);
        $startY = (float) $marker['y']
            + ((float) $marker['height'] / 2)
            - (($lineHeight * max(count($lines) - 1, 0)) / 2);

        foreach ($lines as $index => $line) {
            $this->drawCenteredText(
                $image,
                $line,
                $font,
                0,
                $fontSize,
                (float) $marker['x'] + ((float) $marker['width'] / 2),
                $startY + ($lineHeight * $index),
                $fill,
            );
        }
    }

    /**
     * @param  resource|\GdImage  $image
     */
    private function drawCenteredText(
        $image,
        string $text,
        string $font,
        int $angle,
        float $fontSize,
        float $centerX,
        float $centerY,
        string $fill,
    ): void {
        if ($text === '') {
            return;
        }

        $box = $this->textBox($font, $fontSize, $angle, $text);
        $x = $centerX - ($box['min_x'] + ($box['width'] / 2));
        $y = $centerY - ($box['min_y'] + ($box['height'] / 2));

        imagettftext(
            $image,
            $fontSize,
            $angle,
            (int) round($x),
            (int) round($y),
            $this->allocateColor($image, $fill),
            $font,
            $text,
        );
    }

    /**
     * @return array{min_x:float,max_x:float,min_y:float,max_y:float,width:float,height:float}
     */
    private function textBox(string $font, float $fontSize, int $angle, string $text): array
    {
        $box = imagettfbbox($fontSize, $angle, $font, $text);

        if (! is_array($box)) {
            throw new \RuntimeException('Ukuran tulisan tidak bisa dihitung.');
        }

        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $ys = [$box[1], $box[3], $box[5], $box[7]];
        $minX = (float) min($xs);
        $maxX = (float) max($xs);
        $minY = (float) min($ys);
        $maxY = (float) max($ys);

        return [
            'min_x' => $minX,
            'max_x' => $maxX,
            'min_y' => $minY,
            'max_y' => $maxY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
        ];
    }

    /**
     * @param  resource|\GdImage  $image
     */
    private function allocateColor($image, string $hex): int
    {
        $value = ltrim($hex, '#');
        $red = hexdec(substr($value, 0, 2));
        $green = hexdec(substr($value, 2, 2));
        $blue = hexdec(substr($value, 4, 2));

        return imagecolorallocate($image, $red, $green, $blue);
    }

    /**
     * @param  array{
     *     image_data_uri:string|null,
     *     canvas_width:int,
     *     canvas_height:int
     * }  $template
     * @return resource|\GdImage
     */
    private function createCanvasFromTemplate(array $template, PrayerPaperType $type)
    {
        $this->ensureGdIsAvailable();
        $this->ensureMemoryLimit();
        $image = null;

        if (filled($template['image_data_uri'])) {
            $binary = $this->decodeDataUri((string) $template['image_data_uri']);

            if ($binary !== null) {
                $loaded = imagecreatefromstring($binary);

                if ($loaded !== false) {
                    $image = $loaded;
                }
            }
        }

        if ($image === null) {
            $image = imagecreatetruecolor((int) $template['canvas_width'], (int) $template['canvas_height']);

            if ($image === false) {
                throw new \RuntimeException('Gagal membuat kanvas kertas hio.');
            }

            $white = imagecolorallocate($image, 255, 255, 255);
            imagefill($image, 0, 0, $white);
        }

        return $this->resizeCanvas(
            $image,
            $type === PrayerPaperType::A
                ? self::PRAYER_PRINT_WIDTH_PX
                : self::INCENSE_PRINT_WIDTH_PX,
            $type === PrayerPaperType::A
                ? self::PRAYER_PRINT_HEIGHT_PX
                : self::INCENSE_PRINT_HEIGHT_PX,
        );
    }

    private function encodePng($image): string
    {
        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);

        if (! is_string($content) || $content === '') {
            throw new \RuntimeException('File kertas doa gagal dibuat.');
        }

        return $content;
    }

    private function decodeDataUri(string $value): ?string
    {
        $parts = explode(',', $value, 2);

        if (count($parts) !== 2) {
            return null;
        }

        $decoded = base64_decode($parts[1], true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    private function fontPath(bool $cjk): string
    {
        $candidates = $this->fontCandidates($cjk);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_readable($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(sprintf(
            'File font kertas doa tidak ditemukan. Cek env %s atau pasang font di server Linux.',
            $cjk ? 'PRAYER_PAPER_CJK_FONT_PATHS' : 'PRAYER_PAPER_LATIN_FONT_PATHS',
        ));
    }

    /**
     * @param  resource|\GdImage  $image
     * @return resource|\GdImage
     */
    private function resizeCanvas($image, int $targetWidth, int $targetHeight)
    {
        $sourceWidth = (int) imagesx($image);
        $sourceHeight = (int) imagesy($image);
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($resized === false) {
            throw new \RuntimeException('Gagal menyesuaikan ukuran kertas cetak.');
        }

        imagealphablending($resized, true);
        imagesavealpha($resized, true);
        imagecopyresampled(
            $resized,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );
        imagedestroy($image);

        return $resized;
    }

    /**
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     * @return array{x:float,y:float,width:float,height:float}
     */
    private function scaleMarker(
        array $marker,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
    ): array {
        return [
            'x' => ((float) $marker['x'] / max($sourceWidth, 1)) * $targetWidth,
            'y' => ((float) $marker['y'] / max($sourceHeight, 1)) * $targetHeight,
            'width' => ((float) $marker['width'] / max($sourceWidth, 1)) * $targetWidth,
            'height' => ((float) $marker['height'] / max($sourceHeight, 1)) * $targetHeight,
        ];
    }

    /**
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function estimateHorizontalPreviewFontSize(array $marker, string $text): float
    {
        $count = max(mb_strlen(trim($text)), 1);

        return max(
            22.0,
            min(
                (float) $marker['height'] * 0.32,
                ((((float) $marker['width'] * 0.88) * 1.12) / ($count * 0.74)),
            ),
        );
    }

    /**
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     * @param  array<int, string>  $lines
     */
    private function estimateHorizontalMultilineFontSize(PrayerPaperType $type, array $marker, array $lines): float
    {
        $longestLine = collect($lines)
            ->sortByDesc(fn (string $line): int => mb_strlen($line))
            ->first() ?? '';
        $longestCount = max(mb_strlen(trim($longestLine)), 1);
        $lineCount = max(count($lines), 1);
        $widthBasedFont = ((((float) $marker['width'] * 0.88) * 1.12) / ($longestCount * 0.74));
        $heightBasedFont = (((float) $marker['height'] * 0.84) / ($lineCount * 1.28));

        return max(
            22.0,
            min(
                $widthBasedFont,
                $heightBasedFont,
            ),
        ) * $this->fontScale($type, 'horizontal');
    }

    /**
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function estimateRotatedPreviewFontSize(PrayerPaperType $type, array $marker, string $text): float
    {
        $count = max(mb_strlen(trim($text)), 1);

        return max(
            26.0,
            min(
                (float) $marker['width'] * 0.34,
                ((((float) $marker['height'] * 1.05) * 1.05) / ($count * 0.78)),
            ),
        ) * $this->fontScale($type, 'rotated');
    }

    /**
     * @return array<int, string>
     */
    private function fontCandidates(bool $cjk): array
    {
        $configured = $this->configuredFontPaths(
            (string) env($cjk ? 'PRAYER_PAPER_CJK_FONT_PATHS' : 'PRAYER_PAPER_LATIN_FONT_PATHS', ''),
        );
        $defaults = $cjk ? self::CJK_FONT_PATHS : self::LATIN_FONT_PATHS;

        return array_values(array_unique([
            ...$configured,
            ...$defaults,
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function configuredFontPaths(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $path): string => trim($path),
            explode(',', $value),
        )));
    }

    private function ensureGdIsAvailable(): void
    {
        $requiredFunctions = [
            'imagecreatetruecolor',
            'imagecreatefromstring',
            'imagettftext',
            'imagettfbbox',
            'imagepng',
        ];

        foreach ($requiredFunctions as $function) {
            if (! function_exists($function)) {
                throw new \RuntimeException('Fitur gambar PHP GD/Freetype belum aktif di server.');
            }
        }
    }

    private function ensureMemoryLimit(): void
    {
        $current = ini_get('memory_limit');

        if (! is_string($current) || $current === '' || $current === '-1') {
            return;
        }

        $bytes = $this->memoryToBytes($current);

        if ($bytes !== null && $bytes < 268435456) {
            ini_set('memory_limit', '256M');
        }
    }

    private function memoryToBytes(string $value): ?int
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (! preg_match('/^(\d+)([KMG])?$/i', $trimmed, $matches)) {
            return null;
        }

        $number = (int) $matches[1];
        $unit = strtoupper($matches[2] ?? '');

        return match ($unit) {
            'G' => $number * 1024 * 1024 * 1024,
            'M' => $number * 1024 * 1024,
            'K' => $number * 1024,
            default => $number,
        };
    }

    /**
     * @return array<int, string>
     */
    private function textLines(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split("/\r\n|\r|\n/", trim($value)) ?: [],
        ), static fn (string $line): bool => $line !== ''));
    }

    private function fontScale(PrayerPaperType $type, string $style): float
    {
        return max(0.1, (float) config($this->configPath($type, $style, 'font_scale'), 1.0));
    }

    private function lineHeight(PrayerPaperType $type, string $style, float $default): float
    {
        return max(0.5, (float) config($this->configPath($type, $style, 'line_height'), $default));
    }

    private function columnGapScale(PrayerPaperType $type, string $style, float $default): float
    {
        return max(0.1, (float) config($this->configPath($type, $style, 'column_gap_scale'), $default));
    }

    private function configPath(PrayerPaperType $type, string $style, string $key): string
    {
        $group = $type === PrayerPaperType::A ? 'prayer' : 'incense';

        return sprintf('prayer_paper_text.%s.%s.%s', $group, $style, $key);
    }
}
