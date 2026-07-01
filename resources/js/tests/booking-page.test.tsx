import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { AnchorHTMLAttributes } from 'react';
import { vi } from 'vitest';
import PublicBookingPage from '@/pages/public/booking';

const fetchMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        href,
        ...props
    }: AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    usePage: () => ({
        props: {
            packages: [
                {
                    code: 'PRAYER',
                    name: 'Sembahyang',
                    description: 'Paket sembahyang',
                    price: '2000000',
                    meal_quota: 2,
                    requires_table: true,
                    requires_incense: false,
                    available: true,
                    unavailable_reason: null,
                    image_url: null,
                },
                {
                    code: 'COMBO',
                    name: 'Combo',
                    description: 'Paket combo',
                    price: '3500000',
                    meal_quota: 4,
                    requires_table: true,
                    requires_incense: true,
                    available: true,
                    unavailable_reason: null,
                    image_url: null,
                },
            ],
            payment: {
                bank_name: 'BCA',
                bank_account_holder: 'PT Chao Du',
                hold_minutes: 60,
            },
            limits: {
                upload_max_mb: 5,
                ocr_upload_max_mb: 5,
            },
            captcha: {
                enabled: false,
                site_key: null,
            },
            preview: {
                prayer: {
                    title: 'Kertas Doa',
                    top_label: 'Contoh doa',
                    bottom_label: 'Periksa kembali tulisan nama.',
                    image_url: null,
                    canvas_width: 1121,
                    canvas_height: 3437,
                    markers: {
                        single: { x: 0, y: 0, width: 100, height: 100 },
                        left: { x: 0, y: 0, width: 100, height: 100 },
                        right: { x: 0, y: 0, width: 100, height: 100 },
                    },
                },
                incense: {
                    title: 'Kertas Hio',
                    top_label: 'Contoh hio',
                    bottom_label: 'Periksa kembali tulisan nama.',
                    image_url: null,
                    canvas_width: 1121,
                    canvas_height: 3437,
                    markers: {
                        single: { x: 0, y: 0, width: 100, height: 100 },
                        left: { x: 0, y: 0, width: 100, height: 100 },
                        right: { x: 0, y: 0, width: 100, height: 100 },
                    },
                },
            },
        },
    }),
}));

beforeEach(() => {
    fetchMock.mockReset();
    fetchMock.mockImplementation(async (input: RequestInfo | URL) => {
        const url = String(input);

        if (url.includes('/api/public/virtual-accounts/reserve')) {
            return {
                ok: true,
                json: async () => ({
                    package_code: 'PRAYER',
                    account_number: '900001',
                    bank_name: 'BCA',
                    account_holder: 'PT Chao Du',
                    expires_at: new Date(Date.now() + 60 * 60 * 1000).toISOString(),
                }),
            } satisfies Partial<Response> as Response;
        }

        return {
            ok: true,
            json: async () => ({}),
        } satisfies Partial<Response> as Response;
    });
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal(
        'URL',
        Object.assign(globalThis.URL ?? {}, {
            createObjectURL: vi.fn(() => 'blob:preview'),
            revokeObjectURL: vi.fn(),
        }),
    );
});

function fillStepOne() {
    fireEvent.change(screen.getByRole('textbox', { name: 'Nama pemesan' }), {
        target: { value: 'Budi' },
    });
    fireEvent.change(screen.getByRole('textbox', { name: 'Email' }), {
        target: { value: 'budi@gmail.com' },
    });
    fireEvent.change(
        screen.getByRole('spinbutton', { name: 'Jumlah yang hadir' }),
        {
            target: { value: '2' },
        },
    );

    const phoneInput = screen.getByRole('textbox', { name: 'Nomor telepon' });
    fireEvent.change(phoneInput, {
        target: { value: '81234567890' },
    });
}

it('shows both name sections when combo is selected', async () => {
    render(<PublicBookingPage />);

    fillStepOne();
    await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));
    });

    fireEvent.click(screen.getByRole('button', { name: /Combo/i }));

    expect(screen.getByText('Nama untuk sembahyang')).toBeInTheDocument();
    expect(
        screen.getByText('Nama Orang atau Keluarga yang Ingin Didoakan'),
    ).toBeInTheDocument();
});

it('shows two prayer paper previews when two deceased names are filled', () => {
    render(<PublicBookingPage />);

    fillStepOne();
    fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));
    fireEvent.click(screen.getByRole('button', { name: /Sembahyang/i }));

    fireEvent.change(
        screen.getByRole('textbox', { name: 'Nama Indonesia 1' }),
        {
            target: { value: 'Nama Satu' },
        },
    );
    fireEvent.change(
        screen.getByRole('textbox', { name: 'Nama Indonesia 2' }),
        {
            target: { value: 'Nama Dua' },
        },
    );

    expect(screen.getByText('Kertas Doa 1')).toBeInTheDocument();
    expect(screen.getByText('Kertas Doa 2')).toBeInTheDocument();
    expect(screen.queryByText('Contoh kertas doa')).not.toBeInTheDocument();
});

it('shows the reserved virtual account on the payment step', async () => {
    render(<PublicBookingPage />);

    fillStepOne();
    fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));
    fireEvent.click(screen.getByRole('button', { name: /Sembahyang/i }));
    fireEvent.change(
        screen.getByRole('textbox', { name: 'Nama Indonesia 1' }),
        {
            target: { value: 'Nama Satu' },
        },
    );
    await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));
    });

    await waitFor(() => {
        expect(
            screen.getByRole('heading', { name: 'Pilihan makanan' }),
        ).toBeInTheDocument();
    });
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Vegetarian' }), {
        target: { value: '1' },
    });
    fireEvent.change(
        screen.getByRole('spinbutton', { name: 'Non-vegetarian' }),
        {
            target: { value: '1' },
        },
    );
    fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));

    await waitFor(() => {
        expect(screen.getByText(/Nomor VA:/)).toBeInTheDocument();
    });
    expect(screen.getByText('900001')).toBeInTheDocument();
});

it('fills mandarin name from photo and keeps it editable', async () => {
    fetchMock.mockResolvedValue({
        ok: true,
        json: async () => ({ text: '王小明' }),
    } satisfies Partial<Response>);

    render(<PublicBookingPage />);

    fillStepOne();
    fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));
    fireEvent.click(screen.getByRole('button', { name: /Sembahyang/i }));

    const file = new File(['photo'], 'nama-1.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText('Foto nama 1'), {
        target: { files: [file] },
    });
    await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: 'Baca foto nama 1' }));
    });

    await waitFor(() => {
        expect(screen.getByDisplayValue('王小明')).toBeInTheDocument();
    });

    const mandarinInput = screen.getByDisplayValue('王小明');
    fireEvent.change(mandarinInput, {
        target: { value: '王小明修正' },
    });

    expect(screen.getByDisplayValue('王小明修正')).toBeInTheDocument();
    expect(screen.getByText('王小明修正')).toBeInTheDocument();
});

it('lets customer continue with manual input when photo reading fails', async () => {
    fetchMock.mockImplementation(async (input: RequestInfo | URL) => {
        const url = String(input);

        if (url.includes('/api/public/virtual-accounts/reserve')) {
            return {
                ok: true,
                json: async () => ({
                    package_code: 'PRAYER',
                    account_number: '900001',
                    bank_name: 'BCA',
                    account_holder: 'PT Chao Du',
                    expires_at: new Date(
                        Date.now() + 60 * 60 * 1000,
                    ).toISOString(),
                }),
            } satisfies Partial<Response> as Response;
        }

        return {
            ok: false,
            json: async () => ({
                message:
                    'Tulisan pada foto belum bisa dibaca. Anda tetap bisa isi manual.',
            }),
            status: 422,
        } satisfies Partial<Response> as Response;
    });

    render(<PublicBookingPage />);

    fillStepOne();
    fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));
    fireEvent.click(screen.getByRole('button', { name: /Sembahyang/i }));
    fireEvent.change(
        screen.getByRole('textbox', { name: 'Nama Indonesia 1' }),
        {
            target: { value: 'Nama Manual' },
        },
    );

    const file = new File(['photo'], 'nama-1.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText('Foto nama 1'), {
        target: { files: [file] },
    });
    await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: 'Baca foto nama 1' }));
    });

    await screen.findByText(
        'Tulisan pada foto belum bisa dibaca. Anda tetap bisa isi manual.',
    );

    await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));
    });

    await waitFor(() => {
        expect(
            screen.getByRole('heading', { name: 'Pilihan makanan' }),
        ).toBeInTheDocument();
    });
});

it('lets customer continue with 0 meal quantities', async () => {
    render(<PublicBookingPage />);

    fillStepOne();
    fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));
    fireEvent.click(screen.getByRole('button', { name: /Sembahyang/i }));
    fireEvent.change(
        screen.getByRole('textbox', { name: 'Nama Indonesia 1' }),
        {
            target: { value: 'Nama Satu' },
        },
    );

    await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));
    });

    await waitFor(() => {
        expect(
            screen.getByRole('heading', { name: 'Pilihan makanan' }),
        ).toBeInTheDocument();
    });

    fireEvent.change(screen.getByRole('spinbutton', { name: 'Vegetarian' }), {
        target: { value: '0' },
    });
    fireEvent.change(
        screen.getByRole('spinbutton', { name: 'Non-vegetarian' }),
        {
            target: { value: '0' },
        },
    );

    fireEvent.click(screen.getByRole('button', { name: /Lanjut/ }));

    expect(
        screen.getByRole('heading', { name: 'Pembayaran' }),
    ).toBeInTheDocument();
});
