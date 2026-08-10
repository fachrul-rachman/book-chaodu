import { render, screen } from '@testing-library/react';
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
    filters: { q: 'Budi' },
    limits: { photoMb: 30, videoMb: 1024, captionCharacters: 200 },
};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        'aria-label': ariaLabel,
    }: {
        children: React.ReactNode;
        'aria-label'?: string;
    }) => <button aria-label={ariaLabel}>{children}</button>,
    router: { get: vi.fn(), reload: vi.fn() },
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
            screen.getByLabelText('Cari nomor booking atau nama customer'),
        ).toHaveValue('Budi');
        expect(screen.getByText('CD-CUSTOMER01')).toBeInTheDocument();
        expect(screen.getByText(/Meja A18/)).toBeInTheDocument();
        expect(screen.getByText(/Hio 1/)).toBeInTheDocument();
        expect(screen.queryByText(/628123/)).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Pilih booking CD-CUSTOMER01/ }),
        ).toBeInTheDocument();
    });
});
