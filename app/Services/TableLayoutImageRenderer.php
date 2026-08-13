<?php

namespace App\Services;

use App\Models\TableSlot;
use Illuminate\Support\Collection;
use RuntimeException;

class TableLayoutImageRenderer
{
    public const WIDTH = 1400;

    public const HEIGHT = 1000;

    /**
     * @param  Collection<int, TableSlot>  $slots
     */
    public function render(Collection $slots, string $targetCode): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Ekstensi GD dibutuhkan untuk membuat denah meja.');
        }

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($image === false) {
            throw new RuntimeException('Kanvas denah meja tidak dapat dibuat.');
        }

        try {
            $white = imagecolorallocate($image, 255, 255, 255);
            $ink = imagecolorallocate($image, 30, 41, 59);
            $border = imagecolorallocate($image, 148, 163, 184);
            $green = imagecolorallocate($image, 16, 185, 129);
            $greenBorder = imagecolorallocate($image, 5, 150, 105);
            $gray = imagecolorallocate($image, 100, 116, 139);
            $lightGray = imagecolorallocate($image, 226, 232, 240);
            $blue = imagecolorallocate($image, 186, 230, 253);
            $yellow = imagecolorallocate($image, 253, 224, 71);

            imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $white);
            $this->centeredText($image, 5, 36, 'DENAH MEJA ANDA', $ink, self::WIDTH / 2);
            $this->centeredText($image, 4, 68, 'Meja Anda: '.$targetCode, $greenBorder, self::WIDTH / 2);

            $this->labeledBox($image, 550, 105, 850, 170, 'MESIN KREMASI', $lightGray, $border, $ink);

            $rowOrder = ['J', 'H', 'G', 'F', 'A', 'B', 'D', 'E'];
            $rowX = [110, 240, 370, 500, 800, 930, 1060, 1190];
            $grouped = $slots->groupBy('row_code');
            $maxSlots = max(1, ...collect($rowOrder)->map(fn (string $row): int => $grouped->get($row, collect())->count())->all());
            $boxWidth = 72;
            $boxHeight = 22;
            $gap = max(3, min(8, (int) floor((540 - ($maxSlots * $boxHeight)) / $maxSlots)));
            $startY = 205;
            $showClosed = (bool) config('table_slots.show_closed_slots', false);

            foreach ($rowOrder as $rowIndex => $rowCode) {
                $rowSlots = $grouped->get($rowCode, collect())->sortByDesc('number')->values();

                foreach ($rowSlots as $slotIndex => $slot) {
                    $y = $startY + ($slotIndex * ($boxHeight + $gap));
                    $isTarget = $slot->code === $targetCode;

                    if ($slot->isTemporarilyClosed() && ! $showClosed && ! $isTarget) {
                        continue;
                    }

                    $fill = $isTarget ? $green : ($slot->isTemporarilyClosed() ? $gray : $white);
                    $outline = $isTarget ? $greenBorder : $border;
                    $text = $slot->isTemporarilyClosed() && ! $isTarget ? $white : $ink;
                    imagefilledrectangle($image, $rowX[$rowIndex], $y, $rowX[$rowIndex] + $boxWidth, $y + $boxHeight, $fill);
                    imagerectangle($image, $rowX[$rowIndex], $y, $rowX[$rowIndex] + $boxWidth, $y + $boxHeight, $outline);
                    $this->centeredText($image, 2, $y + 4, (string) $slot->number, $text, $rowX[$rowIndex] + ($boxWidth / 2));
                }

                $labelY = $startY + ($maxSlots * ($boxHeight + $gap)) + 10;
                imagefilledrectangle($image, $rowX[$rowIndex], $labelY, $rowX[$rowIndex] + $boxWidth, $labelY + 24, $yellow);
                $this->centeredText($image, 2, $labelY + 5, 'ROW '.$rowCode, $ink, $rowX[$rowIndex] + ($boxWidth / 2));
            }

            $this->labeledBox($image, 550, 890, 850, 960, 'ALTAR', $blue, $border, $ink);

            ob_start();
            imagepng($image, null, 6);
            $bytes = ob_get_clean();

            if (! is_string($bytes) || $bytes === '') {
                throw new RuntimeException('Gambar denah meja tidak dapat dikodekan.');
            }

            return $bytes;
        } finally {
            imagedestroy($image);
        }
    }

    private function labeledBox(\GdImage $image, int $left, int $top, int $right, int $bottom, string $label, int $fill, int $border, int $text): void
    {
        imagefilledrectangle($image, $left, $top, $right, $bottom, $fill);
        imagerectangle($image, $left, $top, $right, $bottom, $border);
        $this->centeredText($image, 4, $top + (int) (($bottom - $top - imagefontheight(4)) / 2), $label, $text, ($left + $right) / 2);
    }

    private function centeredText(\GdImage $image, int $font, int $y, string $text, int $color, float $centerX): void
    {
        $x = (int) round($centerX - ((imagefontwidth($font) * strlen($text)) / 2));
        imagestring($image, $font, max(0, $x), $y, $text, $color);
    }
}
