<?php

namespace App\Services;

use App\Exceptions\OcrRecognitionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwoOcrClient
{
    public function recognize(?UploadedFile $sourceImage): string
    {
        if (! $sourceImage) {
            throw new OcrRecognitionException('Source image is missing.');
        }

        $credentials = $this->credentials();

        if ($credentials === []) {
            throw new OcrRecognitionException('2OCR is not configured.');
        }

        $fileContents = file_get_contents($sourceImage->getRealPath());

        if ($fileContents === false) {
            throw new OcrRecognitionException('Source image cannot be read.');
        }

        $lastException = null;

        foreach ($credentials as $index => $credential) {
            try {
                $response = Http::baseUrl((string) $credential['base_url'])
                    ->acceptJson()
                    ->timeout(max(1, (int) config('phase4.ocr_timeout_seconds')))
                    ->retry(max(0, (int) config('phase4.ocr_retry_times')))
                    ->withQueryParameters([
                        'access_token' => (string) $credential['api_key'],
                    ])
                    ->asMultipart()
                    ->attach(
                        'files',
                        $fileContents,
                        $sourceImage->getClientOriginalName(),
                    )
                    ->post((string) config('phase4.ocr_endpoint'), [
                        'type' => (string) config('phase4.ocr_type'),
                        'lang' => (string) config('phase4.ocr_lang'),
                        'retain' => config('phase4.ocr_retain') ? 'true' : 'false',
                    ]);

                $response->throw();

                $text = $this->extractText($response->json());

                if ($text === null || $text === '') {
                    throw new OcrRecognitionException('2OCR response does not contain readable text.');
                }

                if ($index > 0) {
                    Log::info('OCR succeeded using fallback credential.', [
                        'provider' => 'two_ocr',
                        'credential_label' => $credential['label'],
                        'credential_position' => $index + 1,
                    ]);
                }

                return $text;
            } catch (ConnectionException|RequestException $exception) {
                $lastException = $exception;

                if (! $this->shouldTryNextCredential($exception, $index, count($credentials))) {
                    throw new OcrRecognitionException('2OCR request failed.', previous: $exception);
                }

                Log::warning('OCR credential failed. Trying next credential.', [
                    'provider' => 'two_ocr',
                    'credential_label' => $credential['label'],
                    'credential_position' => $index + 1,
                    'status_code' => $exception instanceof RequestException ? $exception->response?->status() : null,
                    'reason' => $this->failureReason($exception),
                ]);
            }
        }

        throw new OcrRecognitionException('2OCR request failed.', previous: $lastException);
    }

    /**
     * @return array<int, array{label:string, api_key:string, base_url:string}>
     */
    private function credentials(): array
    {
        $credentials = config('services.two_ocr.credentials', []);

        if (! is_array($credentials)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $credential): ?array => is_array($credential)
                ? [
                    'label' => trim((string) ($credential['label'] ?? 'ocr')),
                    'api_key' => trim((string) ($credential['api_key'] ?? '')),
                    'base_url' => trim((string) ($credential['base_url'] ?? '')),
                ]
                : null,
            $credentials,
        ), static fn (?array $credential): bool => $credential !== null
            && $credential['api_key'] !== ''
            && $credential['base_url'] !== ''));
    }

    private function shouldTryNextCredential(ConnectionException|RequestException $exception, int $index, int $total): bool
    {
        if ($index >= ($total - 1)) {
            return false;
        }

        if ($exception instanceof ConnectionException) {
            return true;
        }

        $status = $exception->response?->status();

        if ($status === null) {
            return true;
        }

        if (in_array($status, [401, 402, 403, 408, 409, 423, 425, 429], true) || $status >= 500) {
            return true;
        }

        $payload = $exception->response?->json();
        $message = strtolower(trim((string) (
            data_get($payload, 'message')
            ?: data_get($payload, 'error')
            ?: data_get($payload, 'detail')
            ?: data_get($payload, 'status')
        )));

        if ($message === '') {
            return false;
        }

        foreach (['limit', 'quota', 'exceed', 'expired', 'unauthorized', 'forbidden', 'rate'] as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function failureReason(ConnectionException|RequestException $exception): string
    {
        if ($exception instanceof ConnectionException) {
            return 'connection';
        }

        $payload = $exception->response?->json();
        $message = trim((string) (
            data_get($payload, 'message')
            ?: data_get($payload, 'error')
            ?: data_get($payload, 'detail')
            ?: $exception->getMessage()
        ));

        return $message !== '' ? $message : 'request_failed';
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
