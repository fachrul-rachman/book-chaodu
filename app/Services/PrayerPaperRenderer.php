<?php

namespace App\Services;

use App\Enums\PrayerPaperType;
use App\Models\Booking;

class PrayerPaperRenderer
{
    private const PRINT_WIDTH_PX = 950;

    private const PRAYER_PRINT_HEIGHT_PX = 2900;

    private const INCENSE_PRINT_HEIGHT_PX = 2100;

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
            self::PRINT_WIDTH_PX,
            self::PRAYER_PRINT_HEIGHT_PX,
        );

        $entry = $names[0] ?? null;
        if ($entry) {
            $this->drawMarkerText(
                $image,
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
            self::PRINT_WIDTH_PX,
            self::INCENSE_PRINT_HEIGHT_PX,
        );
        $entry = $names[0] ?? null;

        if ($entry) {
            $this->drawMarkerText(
                $image,
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
        string $text,
        bool $vertical,
        array $marker,
        string $fill,
        bool $rotateIndonesian,
    ): void {
        $displayText = $vertical ? $text : mb_strtoupper($text);

        if ($vertical) {
            $this->drawVerticalText($image, $displayText, $marker, $fill);

            return;
        }

        if ($rotateIndonesian) {
            $this->drawRotatedText($image, $displayText, $marker, $fill);

            return;
        }

        $this->drawHorizontalText($image, $displayText, $marker, $fill);
    }

    /**
     * @param  resource|\GdImage  $image
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function drawVerticalText($image, string $text, array $marker, string $fill): void
    {
        $characters = preg_split('//u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($characters) || $characters === []) {
            return;
        }

        $font = $this->fontPath(true);
        $count = count($characters);
        $width = (float) $marker['width'];
        $height = (float) $marker['height'];
        $fontSize = max(
            12.0,
            min(
                $width * 0.58,
                $height / max(($count * 1.2), 1),
            ),
        );
        $lineHeight = $fontSize * 1.38;
        $stackHeight = ($lineHeight * max($count - 1, 0)) + $fontSize;
        $centerX = (float) $marker['x'] + ($width / 2);
        $startY = (float) $marker['y'] + ($height / 2) - ($stackHeight / 2) + ($fontSize / 2);

        foreach ($characters as $index => $character) {
            $this->drawCenteredText(
                $image,
                (string) $character,
                $font,
                0,
                $fontSize,
                $centerX,
                $startY + ($lineHeight * $index),
                $fill,
            );
        }
    }

    /**
     * @param  resource|\GdImage  $image
     * @param  array{x:float|int,y:float|int,width:float|int,height:float|int}  $marker
     */
    private function drawRotatedText($image, string $text, array $marker, string $fill): void
    {
        $font = $this->fontPath(false);
        $fontSize = $this->estimateRotatedPreviewFontSize($marker, $text);

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
    private function drawHorizontalText($image, string $text, array $marker, string $fill): void
    {
        $font = $this->fontPath(false);
        $fontSize = $this->estimateHorizontalPreviewFontSize($marker, $text);

        $this->drawCenteredText(
            $image,
            trim($text),
            $font,
            0,
            $fontSize,
            (float) $marker['x'] + ((float) $marker['width'] / 2),
            (float) $marker['y'] + ((float) $marker['height'] / 2),
            $fill,
        );
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
            self::PRINT_WIDTH_PX,
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
     */
    private function estimateRotatedPreviewFontSize(array $marker, string $text): float
    {
        $count = max(mb_strlen(trim($text)), 1);

        return max(
            26.0,
            min(
                (float) $marker['width'] * 0.34,
                ((((float) $marker['height'] * 1.05) * 1.05) / ($count * 0.78)),
            ),
        );
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
}
