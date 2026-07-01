<?php

namespace App\Services;

use App\Exceptions\OcrRecognitionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class TwoOcrClient
{
    public function recognize(?UploadedFile $sourceImage): string
    {
        if (! $sourceImage) {
            throw new OcrRecognitionException('Source image is missing.');
        }

        $apiKey = (string) config('services.two_ocr.api_key');
        $baseUrl = (string) config('services.two_ocr.base_url');

        if ($apiKey === '' || $baseUrl === '') {
            throw new OcrRecognitionException('2OCR is not configured.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout(max(1, (int) config('phase4.ocr_timeout_seconds')))
                ->retry(max(0, (int) config('phase4.ocr_retry_times')))
                ->withQueryParameters([
                    'access_token' => $apiKey,
                ])
                ->asMultipart()
                ->attach(
                    'files',
                    file_get_contents($sourceImage->getRealPath()) ?: '',
                    $sourceImage->getClientOriginalName(),
                )
                ->post((string) config('phase4.ocr_endpoint'), [
                    'type' => (string) config('phase4.ocr_type'),
                    'lang' => (string) config('phase4.ocr_lang'),
                    'retain' => config('phase4.ocr_retain') ? 'true' : 'false',
                ]);

            $response->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new OcrRecognitionException('2OCR request failed.', previous: $exception);
        }

        $text = $this->extractText($response->json());

        if ($text === null || $text === '') {
            throw new OcrRecognitionException('2OCR response does not contain readable text.');
        }

        return $text;
    }

    private function extractText(mixed $payload): ?string
    {
        $plainTextBase64 = data_get($payload, 'documents.0.plainTextBase64');

        if (is_string($plainTextBase64) && trim($plainTextBase64) !== '') {
            $decoded = base64_decode($plainTextBase64, true);

            if (is_string($decoded)) {
                $normalized = trim(preg_replace('/\s+/u', '', $decoded) ?? '');

                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        $words = '';

        foreach (data_get($payload, 'documents.0.textAnnotation.Pages', []) as $page) {
            if (! is_array($page)) {
                continue;
            }

            foreach (($page['Words'] ?? []) as $word) {
                if (! is_array($word)) {
                    continue;
                }

                $words .= trim((string) ($word['Text'] ?? ''));
            }
        }

        if ($words !== '') {
            return $words;
        }

        $candidates = [
            data_get($payload, 'text'),
            data_get($payload, 'data.text'),
            data_get($payload, 'result.text'),
            data_get($payload, 'data.result'),
            data_get($payload, 'result'),
            data_get($payload, 'documents.0.text'),
            data_get($payload, 'documents.0.content'),
            data_get($payload, 'documents.0.result'),
            data_get($payload, 'documents.0.extractedText'),
            data_get($payload, 'documents.0.ocr.text'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalized = trim(preg_replace('/\s+/u', ' ', $candidate) ?? '');

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }
}
