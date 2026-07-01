<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleServiceAccountAccessTokenService
{
    public function getAccessToken(): string
    {
        $credentials = $this->readCredentials();
        $tokenUri = (string) ($credentials['token_uri'] ?? '');
        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $privateKey = (string) ($credentials['private_key'] ?? '');

        if ($tokenUri === '' || $clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Data Google service account belum lengkap.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']) ?: '');
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]) ?: '');

        $signature = '';

        if (! openssl_sign($header.'.'.$payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Token Google tidak berhasil dibuat.');
        }

        $response = Http::asForm()
            ->timeout((int) config('phase7.google.timeout_seconds'))
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $header.'.'.$payload.'.'.$this->base64UrlEncode($signature),
            ])
            ->throw();

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Access token Google tidak ditemukan.');
        }

        return $accessToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function readCredentials(): array
    {
        $path = (string) config('phase7.google.service_account_json_path');

        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('File Google service account belum ditemukan.');
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('File Google service account tidak bisa dibaca.');
        }

        $credentials = json_decode($content, true);

        if (! is_array($credentials)) {
            throw new RuntimeException('Isi file Google service account tidak valid.');
        }

        return $credentials;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
