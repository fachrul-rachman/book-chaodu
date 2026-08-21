import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import AdminReportsPage from '@/pages/admin/reports';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    router: { get: vi.fn() },
    usePage: () => ({
        props: {
            filters: {
                tab: 'after_event',
                date_field: 'approval',
                date_from: null,
                date_to: null,
                package_code: null,
                sort: 'table_number',
                agent_search: null,
                page: 1,
            },
            tabs: [{ value: 'after_event', label: 'After Event' }],
            sort_options: [{ value: 'table_number', label: 'Nomor meja' }],
            package_options: [],
            checkin: { rows: [], filter_lines: [], pagination: pagination() },
            finance: {
                summary: {
                    total_bookings: 0,
                    total_revenue: 0,
                    by_package: [],
                },
                rows: [],
                filter_lines: [],
                pagination: pagination(),
            },
            agent: { groups: [], filter_lines: [], pagination: pagination() },
            customer: {
                summary: { total_bookings: 0, by_package: [] },
                rows: [],
                filter_lines: [],
                pagination: pagination(),
            },
            after_event: {
                rows: [
                    {
                        booking_number: 'CD-AFTER-1',
                        customer_name: 'Budi Santoso',
                        customer_phone: '+628123456789',
                        agent_name: 'Agent Lestari',
                        approval_date: '2026-08-16',
                        package_name: 'Paket Combo',
                        table_number: 'F238',
                        incense_number: '7',
                    },
                ],
                filter_lines: [],
                pagination: pagination(1),
            },
            export_urls: {
                after_event: {
                    xlsx: '/after-event.xlsx',
                    pdf: '/after-event.pdf',
                },
            },
        },
    }),
}));

function pagination(total = 0) {
    return {
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total,
        from: total > 0 ? 1 : null,
        to: total > 0 ? total : null,
    };
}

describe('Laporan After Event', () => {
    it('shows the requested columns with table and incense numbers separated', () => {
        render(<AdminReportsPage />);

        for (const heading of [
            'Kode booking',
            'Nama customer',
            'Nomor telepon',
            'Nama agent',
            'Tanggal disetujui',
            'Paket',
            'Nomor meja',
            'Nomor hio',
        ]) {
            expect(
                screen.getByRole('columnheader', { name: heading }),
            ).toBeInTheDocument();
        }

        expect(screen.getByText('CD-AFTER-1')).toBeInTheDocument();
        expect(screen.getByText('F238')).toBeInTheDocument();
        expect(screen.getByText('7')).toBeInTheDocument();
    });
});
