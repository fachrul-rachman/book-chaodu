<?php

namespace App\Services;

use App\Enums\PackageCode;
use App\Enums\VirtualAccountStatus;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\VirtualAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VirtualAccountService
{
    public function reserve(PackageCode $packageCode, string $reference): array
    {
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
                    return $this->reservationPayload($row);
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

            return $this->reservationPayload($account);
        });
    }

    public function releaseReservation(string $reference): void
    {
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
     * @param  array<string, array<int, string>>  $numbersByPackage
     * @return array<string, array{added:int, skipped:int}>
     */
    public function import(array $numbersByPackage): array
    {
        return DB::transaction(function () use ($numbersByPackage): array {
            $summary = [];

            foreach ($numbersByPackage as $packageCode => $numbers) {
                $added = 0;
                $skipped = 0;

                foreach ($numbers as $number) {
                    $existing = VirtualAccount::query()
                        ->where('package_code', $packageCode)
                        ->where('account_number', $number)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $skipped++;

                        continue;
                    }

                    VirtualAccount::query()->create([
                        'package_code' => $packageCode,
                        'account_number' => $number,
                        'status' => VirtualAccountStatus::Available,
                    ]);

                    $added++;
                }

                $summary[$packageCode] = [
                    'added' => $added,
                    'skipped' => $skipped,
                ];
            }

            return $summary;
        });
    }

    /**
     * @return array<string, array{total:int,available:int,held:int,assigned:int}>
     */
    public function summary(): array
    {
        $this->releaseExpired();

        $counts = VirtualAccount::query()
            ->selectRaw('package_code, status, count(*) as aggregate')
            ->groupBy('package_code', 'status')
            ->get();

        $summary = [];

        foreach (PackageCode::cases() as $packageCode) {
            $summary[$packageCode->value] = [
                'total' => 0,
                'available' => 0,
                'held' => 0,
                'assigned' => 0,
            ];
        }

        foreach ($counts as $row) {
            $packageCode = $row->package_code instanceof PackageCode
                ? $row->package_code->value
                : (string) $row->package_code;
            $status = $row->status instanceof VirtualAccountStatus
                ? strtolower($row->status->value)
                : strtolower((string) $row->status);
            $count = (int) $row->aggregate;

            if (! isset($summary[$packageCode])) {
                continue;
            }

            $summary[$packageCode]['total'] += $count;

            if (array_key_exists($status, $summary[$packageCode])) {
                $summary[$packageCode][$status] = $count;
            }
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

    private function holdMinutes(): int
    {
        return max(1, (int) config('phase3.virtual_account_hold_minutes', 60));
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

    private function reservationPayload(VirtualAccount $account): array
    {
        $paymentIdentity = $this->paymentIdentity();

        return [
            'package_code' => $account->package_code->value,
            'account_number' => $account->account_number,
            'bank_name' => $paymentIdentity['bank_name'],
            'account_holder' => $paymentIdentity['bank_account_holder'],
            'expires_at' => optional($account->hold_expires_at)?->toIso8601String(),
        ];
    }
}
