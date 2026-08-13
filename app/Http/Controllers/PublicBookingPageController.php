<?php

namespace App\Http\Controllers;

use App\Services\CaptchaVerifier;
use App\Services\PublicBookingCatalog;
use App\Services\PublicBookingRegistrationService;
use Inertia\Inertia;
use Inertia\Response;

class PublicBookingPageController extends Controller
{
    public function __invoke(
        PublicBookingCatalog $catalog,
        CaptchaVerifier $captchaVerifier,
        PublicBookingRegistrationService $registration,
    ): Response {
        return Inertia::render('public/booking', [
            ...$catalog->data(),
            'captcha' => [
                'enabled' => $captchaVerifier->enabled(),
                'site_key' => $captchaVerifier->siteKey(),
            ],
            'registration' => [
                'is_closed' => $registration->isClosed(),
            ],
        ]);
    }
}
