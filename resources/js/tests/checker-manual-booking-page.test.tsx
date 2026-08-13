import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import CheckerManualBookingCreatePage from '@/pages/checker/manual-bookings/create';

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    Link: ({ children, ...props }: React.AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a {...props}>{children}</a>
    ),
    usePage: () => ({
        props: {
            packages: [
                { code: 'PRAYER', name: 'Sembahyang' },
                { code: 'INCENSE', name: 'Hio Jumbo' },
                { code: 'COMBO', name: 'Combo' },
            ],
            table_slots: [{ id: 10, code: 'A38' }],
            incense_slots: [{ id: 20, number: 3 }],
            ocr: { url: '/api/public/ocr', max_mb: 5 },
            errors: {},
        },
    }),
    useForm: (data: Record<string, unknown>) => ({
        data,
        errors: {},
        processing: false,
        setData: vi.fn(),
        post: vi.fn(),
    }),
}));

describe('Daftar Manual Checker', () => {
    it('uses dropdowns for table and incense and provides multiline OCR name fields', () => {
        render(<CheckerManualBookingCreatePage />);

        expect(screen.getByLabelText('Nama customer')).toBeInTheDocument();
        expect(screen.getByLabelText('Email')).toBeInTheDocument();
        expect(screen.getByLabelText('Nomor telepon')).toBeInTheDocument();
        expect(screen.getByLabelText('Paket')).toBeInTheDocument();
        expect(screen.getByLabelText('Nomor meja')).toBeInTheDocument();
        expect(screen.getByLabelText('Nomor hio')).toBeInTheDocument();
        expect(screen.getAllByRole('textbox', { name: /Nama Mandarin/ })[0]).toHaveAttribute('rows');
        expect(screen.getAllByRole('button', { name: /Baca foto/ }).length).toBeGreaterThan(0);
        expect(screen.queryByLabelText('Jumlah hadir')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Bukti pembayaran')).not.toBeInTheDocument();
    });
});
