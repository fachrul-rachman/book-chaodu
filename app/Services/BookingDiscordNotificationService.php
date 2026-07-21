<?php

namespace App\Services;

use App\Enums\BookingNameCategory;
use App\Enums\PackageCode;
use App\Models\Booking;
use App\Models\BookingName;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingDiscordNotificationService
{
    public function __construct(
        private readonly VirtualAccountService $virtualAccountService,
    ) {}

    public function notifyPaymentSubmitted(Booking $booking): void
    {
        $booking->loadMissing([
            'payment',
            'meal',
            'names',
            'tableSlots',
            'incenseSlots',
        ]);

        $this->sendToWebhook(
            (string) config('services.discord.general_booking_webhook_url'),
            $this->submittedBookingPayload($booking),
            'general',
            $booking,
        );
    }

    public function notifyAgentApproved(Booking $booking): void
    {
        $booking->loadMissing(['payment']);

        if ($booking->referral_source !== 'AGENT') {
            return;
        }

        $this->sendToWebhook(
            (string) config('services.discord.agent_booking_webhook_url'),
            $this->approvalPayload($booking),
            'agent',
            $booking,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendToWebhook(string $url, array $payload, string $channel, Booking $booking): void
    {
        if (blank($url)) {
            return;
        }

        try {
            Http::asJson()
                ->timeout(max(1, (int) config('services.discord.timeout_seconds', 5)))
                ->retry(max(0, (int) config('services.discord.retry_times', 1)), 200)
                ->post($url, $payload)
                ->throw();
        } catch (\Throwable $throwable) {
            Log::warning('Gagal mengirim notifikasi Discord booking.', [
                'booking_number' => $booking->booking_number,
                'channel' => $channel,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function submittedBookingPayload(Booking $booking): array
    {
        $payment = $booking->payment;
        $meal = $booking->meal;
        $deceasedNames = $booking->names
            ->where('category', BookingNameCategory::Deceased)
            ->keyBy('position');
        $incenseName = $booking->names
            ->first(fn (BookingName $name): bool => $name->category === BookingNameCategory::Incense);
        $tableNumbers = $booking->tableSlots
            ->sortBy('allocation_order')
            ->pluck('code')
            ->filter()
            ->implode(', ');
        $incenseNumbers = $booking->incenseSlots
            ->sortBy('allocation_order')
            ->pluck('number')
            ->filter()
            ->implode(', ');

        $fields = [
            $this->field('🎫 Nomor Booking', $booking->booking_number),
            $this->field(
                '🕒 Waktu Booking',
                optional($booking->created_at)->timezone(config('app.timezone'))->format('d-m-Y H:i'),
            ),
            $this->field('👤 Nama Customer', $booking->customer_name),
            $this->field('📱 Nomor Telepon', $booking->customer_phone),
            $this->field('✉️ Email', $booking->customer_email, false),
            $this->field(
                '📦 Paket',
                $booking->package_name_snapshot.' — '.$this->formatCurrency((int) $booking->package_price_snapshot),
            ),
            $this->field('👥 Jumlah Hadir', $booking->attendee_count.' orang'),
        ];

        foreach ([1, 2] as $position) {
            $name = $deceasedNames->get($position);

            if ($name instanceof BookingName) {
                $fields[] = $this->field(
                    '🙏 Nama Mendiang '.$position,
                    $this->bookingNameValue($name),
                    false,
                );
            }
        }

        if ($incenseName instanceof BookingName) {
            $fields[] = $this->field('🧧 Nama Hio', $this->bookingNameValue($incenseName), false);
        }

        $fields = [
            ...$fields,
            $this->field('🥬 Vegetarian', $meal->vegetarian_quantity.' porsi'),
            $this->field('🍗 Nonvegetarian', $meal->non_vegetarian_quantity.' porsi'),
            $this->field('🪑 Nomor Meja', $tableNumbers),
            $this->field('🧧 Nomor Hio', $incenseNumbers),
            $this->field('📣 Sumber', $this->referralSourceLabel($booking->referral_source)),
        ];

        if (filled($booking->agent_name)) {
            $fields[] = $this->field('🧑‍💼 Nama Agent', $booking->agent_name);
        }

        $fields[] = $this->field('💳 Nomor VA', $this->virtualAccountNumber($booking), false);
        $fields[] = $this->field(
            '🧾 Status Pembayaran',
            $payment ? 'Bukti pembayaran sudah diunggah' : 'Belum mengunggah bukti pembayaran',
            false,
        );

        if ($payment) {
            $fields[] = $this->field('🏦 Nama Pengirim', $payment->sender_name);
            $fields[] = $this->field(
                '📅 Tanggal Transfer',
                optional($payment->transfer_date)->format('d-m-Y'),
            );
        }

        return $this->embed(
            $booking,
            '🆕 Booking Baru Masuk',
            3066993,
            $fields,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalPayload(Booking $booking): array
    {
        $payment = $booking->payment;
        $fields = [
            $this->field('🎫 Nomor Booking', $booking->booking_number),
            $this->field('👤 Nama Customer', $booking->customer_name),
            $this->field('📦 Paket', $booking->package_name_snapshot),
            $this->field('👥 Jumlah Hadir', (string) $booking->attendee_count),
            $this->field('📣 Sumber', $this->referralSourceLabel($booking->referral_source)),
        ];

        if (filled($booking->agent_name)) {
            $fields[] = $this->field('🧑‍💼 Nama Agent', $booking->agent_name);
            $fields[] = $this->spacer();
        }

        if ($payment && filled($payment->virtual_account_number)) {
            $fields[] = $this->field('💳 Nomor VA', $payment->virtual_account_number, false);
        }

        $fields[] = $this->field(
            '🕒 Waktu Disetujui',
            optional($booking->approved_at)->timezone(config('app.timezone'))->format('d-m-Y H:i'),
            false,
        );

        return $this->embed(
            $booking,
            '✅ Booking Agent Disetujui',
            3447003,
            $fields,
        );
    }

    /**
     * @param  array<int, array{name:string,value:string,inline:bool}>  $fields
     * @return array<string, mixed>
     */
    private function embed(Booking $booking, string $title, int $color, array $fields): array
    {
        return [
            'username' => (string) config('services.discord.username', config('app.name')),
            'embeds' => [[
                'title' => $title,
                'color' => $color,
                'author' => [
                    'name' => (string) config('app.name'),
                ],
                'fields' => $fields,
                'footer' => [
                    'text' => (string) config('app.name'),
                ],
                'timestamp' => optional($booking->created_at)->toIso8601String(),
            ]],
        ];
    }

    /**
     * @return array{name:string,value:string,inline:bool}
     */
    private function field(string $name, string|int|null $value, bool $inline = true): array
    {
        return [
            'name' => $name,
            'value' => filled($value) ? (string) $value : '-',
            'inline' => $inline,
        ];
    }

    /**
     * @return array{name:string,value:string,inline:bool}
     */
    private function spacer(): array
    {
        return [
            'name' => "\u{200b}",
            'value' => "\u{200b}",
            'inline' => true,
        ];
    }

    private function bookingNameValue(BookingName $name): string
    {
        return implode("\n", [
            'Indonesia: '.($name->indonesian_name ?: '-'),
            'Mandarin: '.($name->mandarin_name ?: '-'),
        ]);
    }

    private function virtualAccountNumber(Booking $booking): ?string
    {
        if (filled($booking->payment?->virtual_account_number)) {
            return $booking->payment->virtual_account_number;
        }

        try {
            $virtualAccount = $this->virtualAccountService->isFixedMode()
                ? $this->virtualAccountService->requirePackageAccount(
                    PackageCode::from($booking->package_code_snapshot),
                )
                : $this->virtualAccountService->findByBooking($booking);

            return $virtualAccount?->account_number;
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatCurrency(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    private function referralSourceLabel(?string $value): string
    {
        return match ($value) {
            'TEMAN' => 'Teman',
            'KELUARGA' => 'Keluarga',
            'MEDIA_SOSIAL' => 'Media sosial',
            'WEBSITE' => 'Website',
            'AGENT' => 'Agent',
            default => '-',
        };
    }
}
