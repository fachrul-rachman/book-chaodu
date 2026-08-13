<?php

namespace App\Http\Middleware;

use App\Services\PublicBookingRegistrationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicBookingIsOpen
{
    public function __construct(
        private readonly PublicBookingRegistrationService $registration,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->registration->isClosed()) {
            throw ValidationException::withMessages([
                'booking' => 'Pendaftaran booking sedang ditutup.',
            ]);
        }

        return $next($request);
    }
}
