<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentSettingsRequest;
use App\Models\AppSetting;
use App\Services\VirtualAccountService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        $virtualAccountService = app(VirtualAccountService::class);

        return Inertia::render('admin/settings/edit', [
            'payment_settings' => AppSetting::getMany([
                'bank_name',
                'bank_account_holder',
            ]),
            'virtual_account_summary' => $virtualAccountService->summary(),
        ]);
    }

    public function update(
        UpdatePaymentSettingsRequest $request,
        VirtualAccountService $virtualAccountService,
    ): RedirectResponse
    {
        $validated = $request->validated();

        AppSetting::putMany([
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_holder' => $validated['bank_account_holder'] ?? null,
        ]);

        $summary = $virtualAccountService->import([
            'PRAYER' => $request->virtualAccountNumbers('prayer_virtual_accounts'),
            'INCENSE' => $request->virtualAccountNumbers('incense_virtual_accounts'),
            'COMBO' => $request->virtualAccountNumbers('combo_virtual_accounts'),
        ]);

        $status = sprintf(
            'Informasi pembayaran berhasil diperbarui. VA baru: sembahyang %d, hio %d, combo %d.',
            $summary['PRAYER']['added'] ?? 0,
            $summary['INCENSE']['added'] ?? 0,
            $summary['COMBO']['added'] ?? 0,
        );

        return back()->with('status', $status);
    }
}
