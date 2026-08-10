import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import GlobalMediaPage from '@/pages/content/global-media/index';

const pageProps = {
    auth: { user: { id: 1, name: 'Tim Dokumentasi', email: 'content@example.com', role: 'CONTENT_TEAM' } },
    media: [],
    limits: { photoMb: 30, videoMb: 1024, captionCharacters: 200 },
    upload: { singleMaxBytes: 104857600, partSizeBytes: 10485760 },
};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => <button>{children}</button>,
    router: { reload: vi.fn() },
    usePage: () => ({ props: pageProps }),
}));

describe('Media Global', () => {
    beforeEach(() => vi.clearAllMocks());

    it('accepts several supported files and explains their limits', () => {
        render(<GlobalMediaPage />);

        expect(screen.getByRole('heading', { name: 'Media Global' })).toBeInTheDocument();
        const input = screen.getByLabelText('Pilih foto atau video') as HTMLInputElement;
        expect(input.multiple).toBe(true);
        expect(input.accept).toContain('image/webp');
        expect(screen.getByText(/Foto maksimal 30 MB/i)).toBeInTheDocument();
        expect(screen.getByText(/Video maksimal 1 GB/i)).toBeInTheDocument();
    });
});
