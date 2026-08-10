import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import GlobalMediaPage from '@/pages/content/global-media/index';

type MediaMock = {
    id: number;
    uuid: string;
    type: 'IMAGE' | 'VIDEO';
    status: 'PROCESSING' | 'READY' | 'FAILED' | 'HIDDEN';
    filename: string;
    mimeType: string;
    sizeBytes: number;
    caption: string | null;
    sortOrder: number | null;
    previewUrl: string | null;
    createdAt: string;
    errorMessage: string | null;
};

const pageProps = {
    auth: {
        user: {
            id: 1,
            name: 'Tim Dokumentasi',
            email: 'content@example.com',
            role: 'CONTENT_TEAM',
        },
    },
    media: [] as MediaMock[],
    limits: { photoMb: 30, videoMb: 1024, captionCharacters: 200 },
    upload: { singleMaxBytes: 104857600, partSizeBytes: 10485760 },
};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => (
        <button>{children}</button>
    ),
    router: { reload: vi.fn() },
    usePage: () => ({ props: pageProps }),
}));

describe('Media Global', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        pageProps.media = [];
    });

    it('accepts several supported files and explains their limits', () => {
        render(<GlobalMediaPage />);

        expect(
            screen.getByRole('heading', { name: 'Media Global' }),
        ).toBeInTheDocument();
        const input = screen.getByLabelText(
            'Pilih foto atau video',
        ) as HTMLInputElement;
        expect(input.multiple).toBe(true);
        expect(input.accept).toContain('image/webp');
        expect(screen.getByText(/Foto maksimal 30 MB/i)).toBeInTheDocument();
        expect(screen.getByText(/Video maksimal 1 GB/i)).toBeInTheDocument();
    });

    it('provides caption, visibility, ordering, and permanent delete controls', () => {
        pageProps.media = [
            {
                id: 12,
                uuid: 'media-12',
                type: 'IMAGE',
                status: 'READY',
                filename: 'pembukaan.jpg',
                mimeType: 'image/jpeg',
                sizeBytes: 2048,
                caption: 'Doa pembukaan',
                sortOrder: 1,
                previewUrl: null,
                createdAt: '2026-08-10T10:00:00+07:00',
                errorMessage: null,
            },
        ];

        render(<GlobalMediaPage />);

        expect(screen.getByDisplayValue('Doa pembukaan')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Sembunyikan' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Hapus pembukaan.jpg' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Naikkan pembukaan.jpg' }),
        ).toBeDisabled();
    });
});
