import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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
            viewerUrl: '/chaodu/CD-ALBUM01/media/10/viewer',
        },
        {
            id: 11,
            type: 'VIDEO',
            scope: 'BOOKING',
            caption: 'Dokumentasi keluarga',
            width: null,
            height: null,
            previewUrl: null,
            viewerUrl: '/chaodu/CD-ALBUM01/media/11/viewer',
        },
    ],
};

const populatedMedia = [...pageProps.media];

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    usePage: () => ({ props: pageProps }),
}));

describe('Album customer', () => {
    beforeEach(() => {
        pageProps.media = [...populatedMedia];
    });

    afterEach(() => {
        vi.useRealTimers();
    });

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

    it('opens an accessible viewer and navigates photo and video by controls and keyboard', () => {
        render(<PublicGalleryPage />);
        const trigger = screen.getByRole('button', {
            name: 'Buka Doa pembukaan',
        });

        fireEvent.click(trigger);

        expect(
            screen.getByRole('dialog', { name: 'Viewer media' }),
        ).toBeInTheDocument();
        expect(screen.getByText('1 dari 2')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Tutup viewer' }),
        ).toHaveFocus();

        fireEvent.click(
            screen.getByRole('button', { name: 'Media berikutnya' }),
        );

        expect(screen.getByText('2 dari 2')).toBeInTheDocument();
        expect(
            screen.getByLabelText('Pemutar video Dokumentasi keluarga'),
        ).toHaveAttribute('controls');
        expect(
            screen.getByLabelText('Pemutar video Dokumentasi keluarga'),
        ).not.toHaveAttribute('autoplay');

        fireEvent.keyDown(window, { key: 'ArrowLeft' });
        expect(screen.getByText('1 dari 2')).toBeInTheDocument();

        fireEvent.keyDown(window, { key: 'Escape' });
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(trigger).toHaveFocus();
    });

    it('supports play and pause slideshow without autoplaying video', () => {
        vi.useFakeTimers();
        render(<PublicGalleryPage />);
        fireEvent.click(
            screen.getByRole('button', { name: 'Buka Doa pembukaan' }),
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Mulai slideshow' }),
        );
        expect(
            screen.getByRole('button', { name: 'Jeda slideshow' }),
        ).toBeInTheDocument();

        act(() => vi.advanceTimersByTime(4000));

        expect(screen.getByText('2 dari 2')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Mulai slideshow' }),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('Pemutar video Dokumentasi keluarga'),
        ).not.toHaveAttribute('autoplay');
    });

    it('supports horizontal swipe with visible button alternatives', () => {
        render(<PublicGalleryPage />);
        fireEvent.click(
            screen.getByRole('button', { name: 'Buka Doa pembukaan' }),
        );
        const dialog = screen.getByRole('dialog', { name: 'Viewer media' });

        fireEvent.touchStart(dialog, { touches: [{ clientX: 280 }] });
        fireEvent.touchEnd(dialog, { changedTouches: [{ clientX: 100 }] });

        expect(screen.getByText('2 dari 2')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Media sebelumnya' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Media berikutnya' }),
        ).toBeInTheDocument();
    });
});
