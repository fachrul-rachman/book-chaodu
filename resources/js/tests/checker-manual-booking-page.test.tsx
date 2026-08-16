import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import CheckerManualBookingCreatePage from '@/pages/checker/manual-bookings/create';

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
    useForm: (initialData: Record<string, unknown>) => {
        const [data, setData] = useState({
            ...initialData,
            package_code: 'COMBO',
        });

        return {
            data,
            errors: {},
            processing: false,
            setData: (key: string, value: unknown) =>
                setData((current) => ({ ...current, [key]: value })),
            post: vi.fn(),
        };
    },
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
        expect(
            screen.getAllByRole('textbox', { name: /Nama Mandarin/ })[0],
        ).toHaveAttribute('rows');
        expect(
            screen.getAllByRole('button', { name: /Baca foto/ }).length,
        ).toBeGreaterThan(0);
        expect(screen.queryByLabelText('Jumlah hadir')).not.toBeInTheDocument();
        expect(
            screen.queryByLabelText('Bukti pembayaran'),
        ).not.toBeInTheDocument();
    });

    it('offers Site and Agent sources and asks for agent name conditionally', () => {
        render(<CheckerManualBookingCreatePage />);

        const source = screen.getByRole('combobox', { name: 'Sumber' });

        expect(source).toHaveTextContent('Site');
        expect(source).toHaveTextContent('Agent');
        expect(screen.queryByLabelText('Nama agent')).not.toBeInTheDocument();

        fireEvent.change(source, { target: { value: 'AGENT' } });

        expect(screen.getByLabelText('Nama agent')).toBeInTheDocument();

        fireEvent.change(source, { target: { value: 'WEBSITE' } });

        expect(screen.queryByLabelText('Nama agent')).not.toBeInTheDocument();
    });
});
