<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingDiscordNotificationService
{
    public function notifySubmitted(Booking $booking): void
    {
        $booking->loadMissing(['payment']);

        $this->sendToWebhook(
            (string) config('services.discord.general_booking_webhook_url'),
            $this->embedPayload(
                $booking,
                '🆕 Booking Baru Masuk',
                3066993,
                optional($booking->created_at)->timezone(config('app.timezone'))->format('d-m-Y H:i'),
                '🕒 Waktu Kirim',
            ),
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
            $this->embedPayload(
                $booking,
                '✅ Booking Agent Disetujui',
                3447003,
                optional($booking->approved_at)->timezone(config('app.timezone'))->format('d-m-Y H:i'),
                '🕒 Waktu Disetujui',
            ),
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
    private function embedPayload(
        Booking $booking,
        string $title,
        int $color,
        ?string $timeValue,
        string $timeLabel,
    ): array {
        $payment = $booking->payment;

        // Field disusun 2 kolom per baris. Tiap baris berisi 2 field akan
        // diselipin "spacer" (field kosong tak terlihat) supaya Discord
        // tidak memaksa 3 kolom sejajar seperti tampilan default-nya.
        $rows = [
            [
                $this->field('🎫 Nomor Booking', $booking->booking_number),
            ],
            [
               $this->field('👤 Nama Customer', $booking->customer_name),
            ],
            [
                $this->field('📦 Paket', $booking->package_name_snapshot),
            ],
            [
                $this->field('👥 Jumlah Hadir', (string) $booking->attendee_count),
            ],
        ];

        $sourceRow = [$this->field('📣 Sumber', $this->referralSourceLabel($booking->referral_source))];
        if ($booking->agent_name) {
            $sourceRow[] = $this->field('🧑‍💼 Nama Agent', $booking->agent_name);
        }
        $rows[] = $sourceRow;

        $fields = [];
        foreach ($rows as $row) {
            foreach ($row as $field) {
                $fields[] = $field;
            }

            // Kalau baris ini isinya 2 field, tambahkan spacer supaya
            // baris berikutnya turun ke baris baru (bukan nempel jadi kolom ke-3).
            if (count($row) === 2) {
                $fields[] = $this->spacer();
            }
        }

        if ($payment && $payment->virtual_account_number) {
            $fields[] = $this->field('💳 Nomor VA', $payment->virtual_account_number, false);
        }

        if ($timeValue) {
            $fields[] = $this->field($timeLabel, $timeValue, false);
        }

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
    private function field(string $name, ?string $value, bool $inline = true): array
    {
        return [
            'name' => $name,
            'value' => $value ?: '-',
            'inline' => $inline,
        ];
    }

    /**
     * Field kosong tak terlihat (zero-width space), dipakai sebagai "pengisi"
     * kolom ketiga supaya layout embed Discord konsisten 2 kolom per baris.
     *
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