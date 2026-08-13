import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PublicGalleryPage from '@/pages/public/gallery';

const pageProps = {
    album: {
        bookingNumber: 'CD-ALBUM01',
        eventName: 'Doa Bersama Chao Du',
        eventDate: '20 September 2026',
        title: 'Kenangan dalam Kebersamaan',
        emptyStateText: 'Album khusus ini masih disiapkan.',
        wallpaperUrl: '/chaodu/CD-ALBUM01/wallpaper',
    },
    media: [
        {
            id: 10,
            type: 'IMAGE',
            scope: 'GLOBAL',
            caption: 'Doa pembukaan',
            width: 800,
            height: 1600,
            previewUrl: '/chaodu/CD-ALBUM01/media/10',
            viewerUrl: '/chaodu/CD-ALBUM01/media/10/viewer',
            downloadUrl: '/chaodu/CD-ALBUM01/media/10/download',
        },
        {
            id: 11,
            type: 'VIDEO',
            scope: 'BOOKING',
            caption: 'Dokumentasi keluarga',
            width: 1920,
            height: 1080,
            previewUrl: '/chaodu/CD-ALBUM01/media/11',
            viewerUrl: '/chaodu/CD-ALBUM01/media/11/viewer',
            downloadUrl: '/chaodu/CD-ALBUM01/media/11/download',
        },
    ],
    downloadAll: {
        status: 'IDLE',
        totalSizeBytes: 2048,
        requestUrl: '/chaodu/CD-ALBUM01/archive',
        statusUrl: '/chaodu/CD-ALBUM01/archive',
        downloadUrl: null,
    },
};

const populatedMedia = [...pageProps.media];

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    usePage: () => ({ props: pageProps }),
}));

describe('Album customer', () => {
    beforeEach(() => {
        pageProps.media = [...populatedMedia];
        pageProps.downloadAll = {
            status: 'IDLE',
            totalSizeBytes: 2048,
            requestUrl: '/chaodu/CD-ALBUM01/archive',
            statusUrl: '/chaodu/CD-ALBUM01/archive',
            downloadUrl: null,
        };
        vi.restoreAllMocks();
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
        expect(
            screen.getByRole('img', { name: 'Dokumentasi keluarga' }),
        ).toHaveAttribute('src', expect.stringContaining('/media/11'));
        expect(
            screen.getByRole('button', { name: 'Putar slideshow album' }),
        ).toBeInTheDocument();
        expect(screen.getByTestId('album-masonry')).toHaveAttribute(
            'data-layout',
            'masonry',
        );
        expect(
            screen.getByRole('button', { name: 'Buka Doa pembukaan' })
                .parentElement,
        ).toHaveAttribute('data-crop', 'portrait');
        expect(
            screen.getByRole('img', { name: 'Doa pembukaan' }),
        ).toHaveAttribute('loading', 'lazy');
        expect(screen.getByRole('img', { name: 'Doa pembukaan' })).toHaveClass(
            'object-contain',
        );
        expect(
            screen.getByRole('img', { name: 'Doa pembukaan' }),
        ).not.toHaveClass('object-cover');
        expect(screen.queryByText(/customer/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/gallery\/global/i)).not.toBeInTheDocument();
    });

    it('shows a friendly empty state', () => {
        pageProps.media = [];

        render(<PublicGalleryPage />);

        expect(
            screen.getByText('Album khusus ini masih disiapkan.'),
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
        expect(
            screen.getByLabelText('Pemutar video Dokumentasi keluarga'),
        ).toHaveAttribute('poster', '/chaodu/CD-ALBUM01/media/11');

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

    it('starts the slideshow directly from the sticky album header', () => {
        render(<PublicGalleryPage />);

        fireEvent.click(
            screen.getByRole('button', { name: 'Putar slideshow album' }),
        );

        expect(
            screen.getByRole('dialog', { name: 'Viewer media' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Jeda slideshow' }),
        ).toBeInTheDocument();
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

    it('offers an accessible original download for the selected media', () => {
        render(<PublicGalleryPage />);
        fireEvent.click(
            screen.getByRole('button', { name: 'Buka Doa pembukaan' }),
        );

        expect(
            screen.getByRole('link', { name: 'Download media ini' }),
        ).toHaveAttribute('href', '/chaodu/CD-ALBUM01/media/10/download');
    });

    it('requests an archive, announces progress, and exposes the ready zip', async () => {
        const fetchMock = vi
            .spyOn(window, 'fetch')
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ status: 'PENDING' }), {
                    status: 202,
                    headers: { 'Content-Type': 'application/json' },
                }),
            )
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ status: 'PENDING' }), {
                    headers: { 'Content-Type': 'application/json' },
                }),
            )
            .mockResolvedValueOnce(
                new Response(
                    JSON.stringify({
                        status: 'READY',
                        downloadUrl: '/chaodu/CD-ALBUM01/archive/download',
                    }),
                    { headers: { 'Content-Type': 'application/json' } },
                ),
            );
        vi.useFakeTimers();
        render(<PublicGalleryPage />);

        fireEvent.click(
            screen.getByRole('button', { name: /Siapkan download semua/i }),
        );
        await act(async () => Promise.resolve());

        expect(fetchMock).toHaveBeenNthCalledWith(
            1,
            '/chaodu/CD-ALBUM01/archive',
            expect.objectContaining({ method: 'POST' }),
        );
        expect(
            screen.getByText('Sedang menyiapkan file ZIP…'),
        ).toBeInTheDocument();

        await act(async () => {
            vi.advanceTimersByTime(2000);
            await Promise.resolve();
        });

        expect(
            screen.getByText('Sedang menyiapkan file ZIP…'),
        ).toBeInTheDocument();

        await act(async () => {
            vi.advanceTimersByTime(2000);
            await Promise.resolve();
        });

        expect(
            screen.getByRole('link', { name: 'Download ZIP' }),
        ).toHaveAttribute('href', '/chaodu/CD-ALBUM01/archive/download');
    });

    it('shows a retry action when archive creation fails', () => {
        pageProps.downloadAll.status = 'FAILED';
        render(<PublicGalleryPage />);

        expect(
            screen.getByText('ZIP belum berhasil dibuat.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Coba lagi' }),
        ).toBeInTheDocument();
    });
});
