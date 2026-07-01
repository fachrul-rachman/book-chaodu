<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

class QrCodeService
{
    public function generatePng(string $value): string
    {
        $qrCode = Encoder::encode($value, ErrorCorrectionLevel::L());
        $matrix = $qrCode->getMatrix();
        $moduleCount = $matrix->getWidth();
        $size = max(200, (int) config('phase7.qr_size'));
        $paddingModules = 4;
        $moduleSize = max(1, (int) floor($size / ($moduleCount + ($paddingModules * 2))));
        $imageSize = ($moduleCount + ($paddingModules * 2)) * $moduleSize;

        if ($imageSize < 1) {
            throw new \RuntimeException('Ukuran QR tidak valid.');
        }

        $image = imagecreatetruecolor($imageSize, $imageSize);

        if ($image === false) {
            throw new \RuntimeException('QR tidak berhasil dibuat.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        if ($white === false || $black === false) {
            imagedestroy($image);

            throw new \RuntimeException('Warna QR tidak berhasil dibuat.');
        }

        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $moduleCount; $y++) {
            for ($x = 0; $x < $moduleCount; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                imagefilledrectangle(
                    $image,
                    ($x + $paddingModules) * $moduleSize,
                    ($y + $paddingModules) * $moduleSize,
                    (($x + $paddingModules + 1) * $moduleSize) - 1,
                    (($y + $paddingModules + 1) * $moduleSize) - 1,
                    $black,
                );
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if (! is_string($png) || $png === '') {
            throw new \RuntimeException('QR PNG tidak berhasil dibuat.');
        }

        return $png;
    }
}
