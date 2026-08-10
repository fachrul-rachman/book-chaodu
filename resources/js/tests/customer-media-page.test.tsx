import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CustomerMediaPage from '@/pages/content/customer-media/index';

const pageProps = {
    results: [
        {
            id: 9,
            bookingNumber: 'CD-CUSTOMER01',
            customerName: 'Budi Santoso',
            packageName: 'Combo',
            tableNumber: 'A18',
            incenseNumber: '1',
            mediaCount: 2,
        },
    ],
    selectedBooking: null,
    media: [],
    filters: { q: 'Budi', table: 'A18', incense: '1' },
    limits: { photoMb: 30, videoMb: 1024, captionCharacters: 200 },
};

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        'aria-label': ariaLabel,
    }: {
        children: React.ReactNode;
        'aria-label'?: string;
    }) => <button aria-label={ariaLabel}>{children}</button>,
    router: { get: routerGet, reload: vi.fn() },
    usePage: () => ({ props: pageProps }),
}));

describe('Media Customer', () => {
    beforeEach(() => vi.clearAllMocks());

    it('searches bookings and shows only operational information', () => {
        render(<CustomerMediaPage />);

        expect(
            screen.getByRole('heading', { name: 'Media Customer' }),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('Nama customer atau kode booking'),
        ).toHaveValue('Budi');
        expect(screen.getByLabelText('Nomor meja')).toHaveValue('A18');
        expect(screen.getByLabelText('Nomor hio')).toHaveValue('1');
        expect(screen.getByText('CD-CUSTOMER01')).toBeInTheDocument();
        expect(screen.getByText(/Meja A18/)).toBeInTheDocument();
        expect(screen.getByText(/Hio 1/)).toBeInTheDocument();
        expect(screen.queryByText(/628123/)).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Pilih booking CD-CUSTOMER01/ }),
        ).toBeInTheDocument();
    });

    it('submits the three booking search fields together', () => {
        render(<CustomerMediaPage />);

        fireEvent.change(
            screen.getByLabelText('Nama customer atau kode booking'),
            { target: { value: 'Sari' } },
        );
        fireEvent.change(screen.getByLabelText('Nomor meja'), {
            target: { value: 'F18' },
        });
        fireEvent.change(screen.getByLabelText('Nomor hio'), {
            target: { value: '2' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Cari booking' }));

        expect(routerGet).toHaveBeenCalledWith('/content/media/customer', {
            q: 'Sari',
            table: 'F18',
            incense: '2',
        });
    });
});
