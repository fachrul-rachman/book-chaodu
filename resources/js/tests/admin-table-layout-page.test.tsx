import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminTableLayoutPage from '@/pages/admin/table-layout';

let showClosedSlots = false;

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    Link: ({
        children,
        ...props
    }: React.AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a {...props}>{children}</a>
    ),
    usePage: () => ({
        props: {
            show_closed_slots: showClosedSlots,
            background_label: 'BACKGROUND',
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
                {
                    row_code: 'A',
                    slots: [
                        {
                            id: 2,
                            code: 'A38',
                            number: 38,
                            status: 'AVAILABLE',
                            booking_id: null,
                            booking_number: null,
                            customer_name: null,
                            is_internal_company: false,
                            is_temporarily_closed: false,
                        },
                        {
                            id: 3,
                            code: 'A18',
                            number: 18,
                            status: 'ASSIGNED',
                            booking_id: 99,
                            booking_number: 'CD-INTERNAL',
                            customer_name: 'Internal',
                            is_internal_company: true,
                            is_temporarily_closed: false,
                        },
                    ],
                },
                {
                    row_code: 'E',
                    slots: [],
                },
                {
                    row_code: 'B',
                    slots: [
                        {
                            id: 4,
                            code: 'B38',
                            number: 38,
                            status: 'ASSIGNED',
                            booking_id: 100,
                            booking_number: 'CD-APPROVED',
                            customer_name: 'Approved',
                            is_internal_company: false,
                            is_temporarily_closed: false,
                        },
                        {
                            id: 5,
                            code: 'B48',
                            number: 48,
                            status: 'RESERVED',
                            booking_id: 101,
                            booking_number: 'CD-PENDING',
                            customer_name: 'Pending',
                            is_internal_company: false,
                            is_temporarily_closed: false,
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
        vi.restoreAllMocks();
    });

    it('keeps a hidden placeholder when closed tables are disabled', () => {
        render(<AdminTableLayoutPage />);

        expect(screen.getByTitle('J88: ditutup sementara')).toHaveClass(
            'invisible',
        );
        expect(screen.queryByText('Ditutup sementara')).not.toBeInTheDocument();
    });

    it('shows closed tables when enabled', () => {
        showClosedSlots = true;
        render(<AdminTableLayoutPage />);

        expect(screen.getByTitle('J88: ditutup sementara')).toHaveClass(
            'bg-slate-500',
        );
        expect(screen.getByText('Ditutup sementara')).toBeInTheDocument();
    });

    it('uses the corrected admin status colors and hides E J labels', () => {
        render(<AdminTableLayoutPage />);

        expect(screen.getByText('Row A')).toHaveClass(
            'bg-[#FD9FC9]',
            'text-slate-900',
        );
        expect(screen.getByTitle('A38: masih kosong')).toHaveClass('bg-white');
        expect(screen.getByTitle('B38 | CD-APPROVED | Approved')).toHaveClass(
            'bg-[#1796C7]',
            'text-white',
        );
        expect(screen.getByTitle('B48 | CD-PENDING | Pending')).toHaveClass(
            'bg-yellow-300',
        );
        expect(screen.getByTitle('A18: Internal Perusahaan')).toHaveClass(
            'bg-orange-400',
        );
        expect(
            screen.getByText('Kosong').parentElement?.querySelector('span'),
        ).toHaveClass('bg-white');
        expect(
            screen
                .getByText('Sudah masuk booking')
                .parentElement?.querySelector('span'),
        ).toHaveClass('bg-yellow-300');
        expect(
            screen
                .getByText('Sudah disetujui')
                .parentElement?.querySelector('span'),
        ).toHaveClass('bg-[#1796C7]');
        expect(
            screen
                .getByText('Internal Perusahaan')
                .parentElement?.querySelector('span'),
        ).toHaveClass('bg-orange-400');
        expect(screen.queryByText('Row J')).not.toBeInTheDocument();
        expect(screen.queryByText('Row E')).not.toBeInTheDocument();
    });

    it('opens the browser print dialog for the dedicated table layout sheet', () => {
        const printMock = vi
            .spyOn(window, 'print')
            .mockImplementation(() => undefined);

        render(<AdminTableLayoutPage />);

        fireEvent.click(screen.getByRole('button', { name: 'Cetak denah' }));

        expect(printMock).toHaveBeenCalledOnce();
        expect(screen.getByTestId('table-layout-sheet')).toHaveClass(
            'table-layout-sheet',
        );
    });
});
