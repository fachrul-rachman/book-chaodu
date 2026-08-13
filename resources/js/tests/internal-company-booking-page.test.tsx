import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import AdminInternalCompanyBookingCreatePage from '@/pages/admin/internal-company-bookings/create';

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    Link: ({ children, ...props }: React.AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a {...props}>{children}</a>
    ),
    usePage: () => ({
        props: {
            internal_company: {
                label: 'Internal Perusahaan',
                table_codes: ['A18', 'A28'],
                incense_numbers: [1, 2],
            },
            errors: {},
        },
    }),
    useForm: (data: Record<string, unknown>) => ({
        data,
        processing: false,
        setData: vi.fn(),
        transform: vi.fn(),
        post: vi.fn(),
    }),
}));

describe('Booking Internal Perusahaan', () => {
    it('asks only for table, customer name, prayer names, and incense name', () => {
        render(<AdminInternalCompanyBookingCreatePage />);

        expect(screen.getByRole('combobox', { name: 'Nomor meja' })).toBeInTheDocument();
        expect(screen.getByRole('textbox', { name: 'Nama customer' })).toBeInTheDocument();
        expect(screen.getByText('Nama doa 1')).toBeInTheDocument();
        expect(screen.getByText('Nama doa 2')).toBeInTheDocument();
        expect(screen.getByText('Nama hio')).toBeInTheDocument();
        expect(screen.queryByLabelText('Email')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Nomor telepon')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Jumlah hadir')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Makanan vegetarian')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Makanan non vegetarian')).not.toBeInTheDocument();
    });
});
