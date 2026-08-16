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
                tab: 'checkin',
                date_field: 'booking',
                date_from: null,
                date_to: null,
                package_code: null,
                sort: 'table_number',
                agent_search: null,
                page: 1,
            },
            tabs: [{ value: 'checkin', label: 'Check-in' }],
            sort_options: [{ value: 'table_number', label: 'Nomor meja' }],
            package_options: [],
            checkin: {
                rows: [
                    {
                        booking_number: 'CD-PRIVATE-CHECKIN',
                        customer_name: 'Budi',
                        customer_phone: '+628111111111',
                        package_name: 'Paket Doa',
                        attendee_count: 2,
                        vegetarian_quantity: 1,
                        non_vegetarian_quantity: 1,
                        table_number: 'A38',
                        incense_number: '',
                        agent_name: null,
                    },
                ],
                filter_lines: [],
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 25,
                    total: 1,
                    from: 1,
                    to: 1,
                },
            },
            finance: {
                summary: {
                    total_bookings: 0,
                    total_revenue: 0,
                    by_package: [],
                },
                rows: [],
                filter_lines: [],
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 25,
                    total: 0,
                    from: null,
                    to: null,
                },
            },
            agent: {
                groups: [],
                filter_lines: [],
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 25,
                    total: 0,
                    from: null,
                    to: null,
                },
            },
            customer: {
                summary: { total_bookings: 0, by_package: [] },
                rows: [],
                filter_lines: [],
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 25,
                    total: 0,
                    from: null,
                    to: null,
                },
            },
            export_urls: {
                checkin: { xlsx: '/xlsx', pdf: '/pdf' },
            },
        },
    }),
}));

describe('Privasi cetak laporan admin', () => {
    it('hides booking number and phone columns only when printing check-in', () => {
        render(<AdminReportsPage />);

        expect(
            screen.getByRole('columnheader', { name: 'Nomor booking' }),
        ).toHaveClass('print:hidden');
        expect(
            screen.getByRole('columnheader', { name: 'Nomor telepon' }),
        ).toHaveClass('print:hidden');
        expect(screen.getByText('CD-PRIVATE-CHECKIN')).toHaveClass(
            'print:hidden',
        );
        expect(screen.getByText('+628111111111')).toHaveClass('print:hidden');
    });
});
