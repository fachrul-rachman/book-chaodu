<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CaptchaVerifier
{
    public function enabled(): bool
    {
        return (bool) config('phase3.captcha.enabled');
    }

    public function siteKey(): ?string
    {
        $siteKey = trim((string) config('phase3.captcha.site_key'));

        return $siteKey !== '' ? $siteKey : null;
    }

    public function verify(?string $token): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $secret = trim((string) config('phase3.captcha.secret_key'));
        $verifyUrl = trim((string) config('phase3.captcha.verify_url'));

        if ($token === null || $token === '' || $secret === '' || $verifyUrl === '') {
            return false;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post($verifyUrl, [
                'secret' => $secret,
                'response' => $token,
            ]);

        if (! $response->successful()) {
            return false;
        }

        return (bool) $response->json('success');
    }
}
