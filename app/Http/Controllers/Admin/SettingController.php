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
        $virtualAccountSummary = $virtualAccountService->summary();
        $virtualAccountLists = $virtualAccountService->packageAccountLists();

        return Inertia::render('admin/settings/edit', [
            'payment_settings' => AppSetting::getMany([
                'bank_name',
                'bank_account_holder',
            ]) + [
                'virtual_account_mode' => $virtualAccountService->mode(),
                'prayer_virtual_account' => $virtualAccountSummary['PRAYER']['account_number'],
                'incense_virtual_account' => $virtualAccountSummary['INCENSE']['account_number'],
                'combo_virtual_account' => $virtualAccountSummary['COMBO']['account_number'],
                'prayer_virtual_accounts' => implode("\n", $virtualAccountLists['PRAYER'] ?? []),
                'incense_virtual_accounts' => implode("\n", $virtualAccountLists['INCENSE'] ?? []),
                'combo_virtual_accounts' => implode("\n", $virtualAccountLists['COMBO'] ?? []),
            ],
            'virtual_account_summary' => $virtualAccountSummary,
        ]);
    }

    public function update(
        UpdatePaymentSettingsRequest $request,
        VirtualAccountService $virtualAccountService,
    ): RedirectResponse {
        $validated = $request->validated();

        AppSetting::putMany([
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_holder' => $validated['bank_account_holder'] ?? null,
            'virtual_account_mode' => $validated['virtual_account_mode'],
        ]);

        if (($validated['virtual_account_mode'] ?? VirtualAccountService::MODE_FIXED) === VirtualAccountService::MODE_POOL) {
            $summary = $virtualAccountService->replacePoolAccounts([
                'PRAYER' => $request->virtualAccountNumbers('prayer_virtual_accounts'),
                'INCENSE' => $request->virtualAccountNumbers('incense_virtual_accounts'),
                'COMBO' => $request->virtualAccountNumbers('combo_virtual_accounts'),
            ]);

            $status = sprintf(
                'Informasi pembayaran berhasil diperbarui. Nomor sembahyang %d, hio %d, combo %d.',
                $summary['PRAYER'] ?? 0,
                $summary['INCENSE'] ?? 0,
                $summary['COMBO'] ?? 0,
            );

            return back()->with('status', $status);
        }

        $summary = $virtualAccountService->replaceFixedAccounts([
            'PRAYER' => $validated['prayer_virtual_account'] ?? null,
            'INCENSE' => $validated['incense_virtual_account'] ?? null,
            'COMBO' => $validated['combo_virtual_account'] ?? null,
        ]);

        $status = sprintf(
            'Informasi pembayaran berhasil diperbarui. Nomor sembahyang %s, hio %s, combo %s.',
            ($summary['PRAYER'] ?? false) ? 'sudah diisi' : 'belum diisi',
            ($summary['INCENSE'] ?? false) ? 'sudah diisi' : 'belum diisi',
            ($summary['COMBO'] ?? false) ? 'sudah diisi' : 'belum diisi',
        );

        return back()->with('status', $status);
    }
}
