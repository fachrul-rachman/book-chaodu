import { render, screen } from '@testing-library/react';
import type { AnchorHTMLAttributes, ButtonHTMLAttributes } from 'react';
import { vi } from 'vitest';
import ContentTeamDashboard from '@/pages/content/dashboard';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        href,
        as,
        ...props
    }: AnchorHTMLAttributes<HTMLAnchorElement> & {
        as?: string;
    }) =>
        as === 'button' ? (
            <button {...(props as ButtonHTMLAttributes<HTMLButtonElement>)}>
                {children}
            </button>
        ) : (
            <a href={href} {...props}>
                {children}
            </a>
        ),
    usePage: () => ({
        props: {
            auth: {
                user: {
                    id: 4,
                    name: 'Tim Dokumentasi',
                    email: 'content@chaodu.test',
                    role: 'CONTENT_TEAM',
                    is_active: true,
                    email_verified_at: null,
                    created_at: '2026-08-10T00:00:00.000Z',
                    updated_at: '2026-08-10T00:00:00.000Z',
                },
            },
        },
    }),
}));

it('shows the content team identity and the two gallery work areas', () => {
    render(<ContentTeamDashboard />);

    expect(
        screen.getByRole('heading', {
            name: 'Selamat datang, Tim Dokumentasi',
        }),
    ).toBeInTheDocument();
    expect(
        screen.getByRole('heading', { name: 'Media Global' }),
    ).toBeInTheDocument();
    expect(
        screen.getByRole('heading', { name: 'Media Customer' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Keluar' })).toBeInTheDocument();
});
