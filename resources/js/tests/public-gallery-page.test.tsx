import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PublicGalleryPage from '@/pages/public/gallery';

const pageProps = {
    album: {
        bookingNumber: 'CD-ALBUM01',
        eventName: 'Doa Bersama Chao Du',
        eventDate: '20 September 2026',
        title: 'Kenangan dalam Kebersamaan',
    },
    media: [
        {
            id: 10,
            type: 'IMAGE',
            scope: 'GLOBAL',
            caption: 'Doa pembukaan',
            width: 1200,
            height: 800,
            previewUrl: '/chaodu/CD-ALBUM01/media/10',
        },
        {
            id: 11,
            type: 'VIDEO',
            scope: 'BOOKING',
            caption: 'Dokumentasi keluarga',
            width: null,
            height: null,
            previewUrl: null,
        },
    ],
};

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    usePage: () => ({ props: pageProps }),
}));

describe('Album customer', () => {
    it('shows event identity and lazy combined media without private customer data', () => {
        render(<PublicGalleryPage />);

        expect(
            screen.getByRole('heading', { name: 'Kenangan dalam Kebersamaan' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Doa Bersama Chao Du')).toBeInTheDocument();
        expect(screen.getByText('20 September 2026')).toBeInTheDocument();
        expect(screen.getByText('CD-ALBUM01')).toBeInTheDocument();
        expect(screen.getByText('Doa pembukaan')).toBeInTheDocument();
        expect(screen.getByText('Dokumentasi keluarga')).toBeInTheDocument();
        expect(screen.getByText('Video')).toBeInTheDocument();
        expect(
            screen.getByRole('img', { name: 'Doa pembukaan' }),
        ).toHaveAttribute('loading', 'lazy');
        expect(screen.queryByText(/customer/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/gallery\/global/i)).not.toBeInTheDocument();
    });

    it('shows a friendly empty state', () => {
        pageProps.media = [];

        render(<PublicGalleryPage />);

        expect(
            screen.getByText('Dokumentasi acara belum tersedia.'),
        ).toBeInTheDocument();
    });
});
