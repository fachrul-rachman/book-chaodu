<?php

namespace App\Services;

use App\Enums\PackageCode;
use App\Enums\VirtualAccountStatus;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\VirtualAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VirtualAccountService
{
    public const MODE_FIXED = 'FIXED';

    public const MODE_POOL = 'POOL';

    public function mode(): string
    {
        $mode = AppSetting::getMany(['virtual_account_mode'])['virtual_account_mode'] ?? null;

        if (in_array($mode, [self::MODE_FIXED, self::MODE_POOL], true)) {
            return $mode;
        }

        return $this->detectModeFromAccounts();
    }

    public function isFixedMode(): bool
    {
        return $this->mode() === self::MODE_FIXED;
    }

    public function isPoolMode(): bool
    {
        return $this->mode() === self::MODE_POOL;
    }

    /**
     * @return array<string, array{configured:bool,account_number:string|null,total:int,available:int,held:int,assigned:int,numbers:array<int, string>}>
     */
    public function summary(): array
    {
        $accounts = $this->accountsByPackage();
        $summary = [];

        foreach (PackageCode::cases() as $packageCode) {
            /** @var Collection<int, VirtualAccount> $rows */
            $rows = $accounts->get($packageCode->value) ?? collect();

            $summary[$packageCode->value] = [
                'configured' => $rows->isNotEmpty(),
                'account_number' => $rows->first()?->account_number,
                'total' => $rows->count(),
                'available' => $rows->where('status', VirtualAccountStatus::Available)->count(),
                'held' => $rows->where('status', VirtualAccountStatus::Held)->count(),
                'assigned' => $rows->where('status', VirtualAccountStatus::Assigned)->count(),
                'numbers' => $rows->pluck('account_number')->values()->all(),
            ];
        }

        return $summary;
    }

    public function paymentIdentity(): array
    {
        $settings = AppSetting::getMany([
            'bank_name',
            'bank_account_holder',
        ]);

        return [
            'bank_name' => $settings['bank_name'],
            'bank_account_holder' => $settings['bank_account_holder'],
        ];
    }

    public function holdMinutes(): int
    {
        $settings = AppSetting::getMany(['virtual_account_hold_minutes']);
        $stored = $settings['virtual_account_hold_minutes'] ?? null;

        if (is_numeric($stored)) {
            return max(1, (int) $stored);
        }

        return max(1, (int) config('phase3.virtual_account_hold_minutes', 60));
    }

    /**
     * @return array<string, string|null>
     */
    public function packageAccounts(): array
    {
        $accounts = $this->accountsByPackage();
        $result = [];

        foreach (PackageCode::cases() as $packageCode) {
            $result[$packageCode->value] = $accounts->get($packageCode->value)?->first()?->account_number;
        }

        return $result;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function packageAccountLists(): array
    {
        $accounts = $this->accountsByPackage();
        $result = [];

        foreach (PackageCode::cases() as $packageCode) {
            $result[$packageCode->value] = $accounts
                ->get($packageCode->value, collect())
                ->pluck('account_number')
                ->values()
                ->all();
        }

        return $result;
    }

    public function requirePackageAccount(PackageCode $packageCode): VirtualAccount
    {
        $account = VirtualAccount::query()
            ->where('package_code', $packageCode)
            ->orderBy('id')
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'package_code' => 'Sistem pembayaran sedang tidak tersedia. Silakan coba lagi nanti.',
            ]);
        }

        return $account;
    }

    public function findPackageAccountByNumber(PackageCode $packageCode, string $accountNumber): ?VirtualAccount
    {
        $normalized = preg_replace('/\D+/', '', $accountNumber) ?? '';

        if ($normalized === '') {
            return null;
        }

        return VirtualAccount::query()
            ->where('package_code', $packageCode)
            ->where('account_number', $normalized)
            ->first();
    }

    public function reserve(PackageCode $packageCode, string $reference): array
    {
        if ($this->isFixedMode()) {
            $account = $this->requirePackageAccount($packageCode);

            return $this->reservationPayload($account, null);
        }

        return DB::transaction(function () use ($packageCode, $reference): array {
            $now = now();
            $this->releaseExpiredRows($now);

            $existing = VirtualAccount::query()
                ->where('hold_reference', trim($reference))
                ->where('status', VirtualAccountStatus::Held)
                ->lockForUpdate()
                ->get();

            foreach ($existing as $row) {
                if (
                    $row->package_code === $packageCode
                    && $row->hold_expires_at instanceof CarbonInterface
                    && $row->hold_expires_at->isFuture()
                ) {
                    return $this->reservationPayload($row, $row->hold_expires_at);
                }

                $this->releaseRow($row);
            }

            $account = VirtualAccount::query()
                ->where('package_code', $packageCode)
                ->where('status', VirtualAccountStatus::Available)
                ->orderBy('id')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();

            if (! $account) {
                throw ValidationException::withMessages([
                    'package_code' => 'Sistem pembayaran sedang tidak tersedia. Silakan coba lagi nanti.',
                ]);
            }

            $account->forceFill([
                'status' => VirtualAccountStatus::Held,
                'hold_reference' => trim($reference),
                'hold_expires_at' => $now->copy()->addMinutes($this->holdMinutes()),
                'booking_id' => null,
            ])->save();

            return $this->reservationPayload($account, $account->hold_expires_at);
        });
    }

    public function releaseReservation(string $reference): void
    {
        if ($this->isFixedMode()) {
            return;
        }

        DB::transaction(function () use ($reference): void {
            $rows = VirtualAccount::query()
                ->where('hold_reference', trim($reference))
                ->where('status', VirtualAccountStatus::Held)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $this->releaseRow($row);
            }
        });
    }

    public function assignToBooking(Booking $booking, string $reference, PackageCode $packageCode): VirtualAccount
    {
        if ($this->isFixedMode()) {
            return $this->requirePackageAccount($packageCode);
        }

        return DB::transaction(function () use ($booking, $reference, $packageCode): VirtualAccount {
            $now = now();
            $this->releaseExpiredRows($now);

            $account = VirtualAccount::query()
                ->where('hold_reference', trim($reference))
                ->where('package_code', $packageCode)
                ->where('status', VirtualAccountStatus::Held)
                ->lockForUpdate()
                ->first();

            if (
                ! $account
                || ! $account->hold_expires_at instanceof CarbonInterface
                || $account->hold_expires_at->isPast()
            ) {
                throw ValidationException::withMessages([
                    'package_code' => 'Nomor pembayaran sudah lewat waktu. Silakan pilih paket lagi.',
                ]);
            }

            $account->forceFill([
                'status' => VirtualAccountStatus::Assigned,
                'hold_reference' => null,
                'hold_expires_at' => null,
                'booking_id' => $booking->id,
            ])->save();

            return $account;
        });
    }

    public function releaseByBooking(Booking $booking): void
    {
        if ($this->isFixedMode()) {
            return;
        }

        DB::transaction(function () use ($booking): void {
            $account = VirtualAccount::query()
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                return;
            }

            $this->releaseRow($account);
        });
    }

    public function releaseExpired(): int
    {
        if ($this->isFixedMode()) {
            return 0;
        }

        return DB::transaction(function (): int {
            $rows = $this->expiredRowsQuery(now())
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $this->releaseRow($row);
            }

            return $rows->count();
        });
    }

    /**
     * @param  array<string, string|null>  $numbersByPackage
     * @return array<string, bool>
     */
    public function replaceFixedAccounts(array $numbersByPackage): array
    {
        return DB::transaction(function () use ($numbersByPackage): array {
            $summary = [];

            foreach (PackageCode::cases() as $packageCode) {
                $number = trim((string) ($numbersByPackage[$packageCode->value] ?? ''));

                VirtualAccount::query()
                    ->where('package_code', $packageCode)
                    ->delete();

                if ($number !== '') {
                    VirtualAccount::query()->create([
                        'package_code' => $packageCode,
                        'account_number' => $number,
                        'status' => VirtualAccountStatus::Available,
                    ]);
                }

                $summary[$packageCode->value] = $number !== '';
            }

            return $summary;
        });
    }

    /**
     * @param  array<string, array<int, string>>  $numbersByPackage
     * @return array<string, int>
     */
    public function replacePoolAccounts(array $numbersByPackage): array
    {
        return DB::transaction(function () use ($numbersByPackage): array {
            $summary = [];

            foreach (PackageCode::cases() as $packageCode) {
                $numbers = collect($numbersByPackage[$packageCode->value] ?? [])
                    ->map(fn (string $number): string => trim($number))
                    ->filter()
                    ->unique()
                    ->values();

                VirtualAccount::query()
                    ->where('package_code', $packageCode)
                    ->delete();

                foreach ($numbers as $number) {
                    VirtualAccount::query()->create([
                        'package_code' => $packageCode,
                        'account_number' => $number,
                        'status' => VirtualAccountStatus::Available,
                    ]);
                }

                $summary[$packageCode->value] = $numbers->count();
            }

            return $summary;
        });
    }

    /**
     * @return Collection<string, Collection<int, VirtualAccount>>
     */
    private function accountsByPackage(): Collection
    {
        return VirtualAccount::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (VirtualAccount $account): string => $account->package_code->value);
    }

    private function detectModeFromAccounts(): string
    {
        $hasMultipleAccounts = VirtualAccount::query()
            ->select('package_code')
            ->groupBy('package_code')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        return $hasMultipleAccounts
            ? self::MODE_POOL
            : self::MODE_FIXED;
    }

    private function releaseExpiredRows(CarbonInterface $now): void
    {
        $rows = $this->expiredRowsQuery($now)
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            $this->releaseRow($row);
        }
    }

    private function expiredRowsQuery(CarbonInterface $now)
    {
        return VirtualAccount::query()
            ->where('status', VirtualAccountStatus::Held)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', $now);
    }

    private function releaseRow(VirtualAccount $account): void
    {
        $account->forceFill([
            'status' => VirtualAccountStatus::Available,
            'hold_reference' => null,
            'hold_expires_at' => null,
            'booking_id' => null,
        ])->save();
    }

    private function reservationPayload(VirtualAccount $account, ?CarbonInterface $expiresAt): array
    {
        $paymentIdentity = $this->paymentIdentity();

        return [
            'package_code' => $account->package_code->value,
            'account_number' => $account->account_number,
            'bank_name' => $paymentIdentity['bank_name'],
            'account_holder' => $paymentIdentity['bank_account_holder'],
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    public function useManualAccountForBooking(
        Booking $booking,
        string $reference,
        PackageCode $packageCode,
        string $accountNumber,
    ): VirtualAccount {
        $account = $this->findPackageAccountByNumber($packageCode, $accountNumber);

        if (! $account) {
            throw ValidationException::withMessages([
                'manual_virtual_account_number' => 'Nomor VA tidak valid untuk paket yang dipilih.',
            ]);
        }

        if ($this->isPoolMode()) {
            DB::transaction(function () use ($reference, $booking, $account): void {
                $this->releaseReservation($reference);

                $locked = VirtualAccount::query()
                    ->whereKey($account->id)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    return;
                }

                if (
                    $locked->status === VirtualAccountStatus::Available
                    || (
                        $locked->status === VirtualAccountStatus::Held
                        && $locked->hold_reference === trim($reference)
                    )
                ) {
                    $locked->forceFill([
                        'status' => VirtualAccountStatus::Assigned,
                        'hold_reference' => null,
                        'hold_expires_at' => null,
                        'booking_id' => $booking->id,
                    ])->save();
                }
            });
        }

        return $account->fresh() ?? $account;
    }
}
