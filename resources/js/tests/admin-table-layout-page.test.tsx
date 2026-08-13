import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminTableLayoutPage from '@/pages/admin/table-layout';

let showClosedSlots = false;

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    Link: ({ children, ...props }: React.AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a {...props}>{children}</a>
    ),
    usePage: () => ({
        props: {
            show_closed_slots: showClosedSlots,
            rows: [
                {
                    row_code: 'J',
                    slots: [
                        {
                            id: 1,
                            code: 'J88',
                            number: 88,
                            status: 'AVAILABLE',
                            booking_id: null,
                            booking_number: null,
                            customer_name: null,
                            is_internal_company: false,
                            is_temporarily_closed: true,
                        },
                    ],
                },
            ],
        },
    }),
}));

describe('Layout meja admin', () => {
    beforeEach(() => {
        showClosedSlots = false;
    });

    it('keeps a hidden placeholder when closed tables are disabled', () => {
        render(<AdminTableLayoutPage />);

        expect(screen.getByTitle('J88: ditutup sementara')).toHaveClass('invisible');
        expect(screen.queryByText('Ditutup sementara')).not.toBeInTheDocument();
    });

    it('shows closed tables when enabled', () => {
        showClosedSlots = true;
        render(<AdminTableLayoutPage />);

        expect(screen.getByTitle('J88: ditutup sementara')).toHaveClass('bg-slate-500');
        expect(screen.getByText('Ditutup sementara')).toBeInTheDocument();
    });
});
