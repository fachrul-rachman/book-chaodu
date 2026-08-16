<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class AdminReportPrintPrivacyTest extends TestCase
{
    public function test_report_pdf_templates_omit_booking_number_phone_and_email(): void
    {
        $common = [
            'title' => 'Laporan',
            'filters' => [],
            'generated_at' => '16-08-2026 10:00',
            'app_name' => 'Chao Du',
        ];
        $outputs = [
            view('reports.checkin', $common + ['payload' => ['rows' => [$this->checkInRow()]]])->render(),
            view('reports.finance', $common + ['payload' => $this->financePayload()])->render(),
            view('reports.agent', $common + ['payload' => $this->agentPayload()])->render(),
            view('reports.customer', $common + ['payload' => ['rows' => [$this->customerRow()]]])->render(),
        ];

        foreach ($outputs as $html) {
            self::assertStringNotContainsString('CD-PRIVATE', $html);
            self::assertStringNotContainsString('+628111111111', $html);
            self::assertStringNotContainsString('private@example.com', $html);
            self::assertStringNotContainsString('<th>Nomor booking</th>', $html);
            self::assertStringNotContainsString('<th>Nomor Booking</th>', $html);
            self::assertStringNotContainsString('<th>Nomor telepon</th>', $html);
            self::assertStringNotContainsString('<th>Nomor Telepon</th>', $html);
            self::assertStringNotContainsString('<th>Email</th>', $html);
        }
    }

    /** @return array<string, mixed> */
    private function checkInRow(): array
    {
        return [
            'booking_number' => 'CD-PRIVATE-CHECKIN',
            'customer_name' => 'Budi',
            'customer_phone' => '+628111111111',
            'package_name' => 'Paket Doa',
            'attendee_count' => 2,
            'vegetarian_quantity' => 1,
            'non_vegetarian_quantity' => 1,
            'table_number' => 'A38',
            'incense_number' => '',
            'agent_name' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function financePayload(): array
    {
        return [
            'summary' => [
                'total_bookings' => 1,
                'total_revenue' => 100000,
                'by_package' => [],
            ],
            'rows' => [[
                'booking_number' => 'CD-PRIVATE-FINANCE',
                'booking_date' => '2026-08-16',
                'approval_date' => '2026-08-16',
                'customer_name' => 'Budi',
                'package_name' => 'Paket Doa',
                'amount' => 100000,
                'virtual_account_number' => '123',
                'referral_source' => 'TEMAN',
                'agent_name' => null,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function agentPayload(): array
    {
        return ['groups' => [[
            'display_name' => 'Agent Satu',
            'booking_count' => 1,
            'attendee_count' => 2,
            'total_value' => 100000,
            'bookings' => [[
                'booking_number' => 'CD-PRIVATE-AGENT',
                'booking_date' => '2026-08-16',
                'approval_date' => '2026-08-16',
                'customer_name' => 'Budi',
                'package_name' => 'Paket Doa',
                'attendee_count' => 2,
                'amount' => 100000,
            ]],
        ]]];
    }

    /** @return array<string, mixed> */
    private function customerRow(): array
    {
        return [
            'booking_number' => 'CD-PRIVATE-CUSTOMER',
            'slot_number' => 'Meja: A38',
            'booking_date' => '2026-08-16',
            'customer_name' => 'Budi',
            'customer_phone' => '+628111111111',
            'customer_email' => 'private@example.com',
            'package_name' => 'Paket Doa',
            'prayer_paper_1' => ['name' => 'Nama Doa'],
            'prayer_paper_2' => ['name' => null],
            'incense_paper' => ['name' => null],
        ];
    }
}
