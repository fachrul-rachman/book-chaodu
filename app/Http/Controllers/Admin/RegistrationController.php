<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PublicBookingRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function update(
        Request $request,
        PublicBookingRegistrationService $registration,
    ): RedirectResponse {
        $validated = $request->validate([
            'is_closed' => ['required', 'boolean'],
        ]);

        $registration->setClosed((bool) $validated['is_closed']);

        return to_route('admin.dashboard')->with(
            'status',
            $validated['is_closed']
                ? 'Pendaftaran publik berhasil ditutup.'
                : 'Pendaftaran publik berhasil dibuka kembali.',
        );
    }
}
