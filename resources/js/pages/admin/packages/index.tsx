import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { useState } from 'react';

type PackageItem = {
    id: number;
    code: 'PRAYER' | 'INCENSE' | 'COMBO';
    name: string;
    description: string | null;
    price: string | null;
    image_url: string | null;
    has_image: boolean;
    is_active: boolean;
    meal_quota: number;
    requires_table: boolean;
    requires_incense: boolean;
};

type Props = {
    packages: PackageItem[];
    flash?: {
        status?: string | null;
    };
    errors: {
        package?: string;
        [key: string]: string | undefined;
    };
};

function formatRupiah(value: string | null): string {
    if (!value) {
        return 'Belum diisi';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function formatStoredPrice(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function formatNominalInput(value: string): string {
    const digitsOnly = value.replace(/\D/g, '');

    if (!digitsOnly) {
        return '';
    }

    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(Number(digitsOnly));
}

function PackageCard({ item }: { item: PackageItem }) {
    const form = useForm({
        price: formatStoredPrice(item.price),
        is_active: item.is_active,
        image: null as File | null,
    });
    const [previewUrl, setPreviewUrl] = useState<string | null>(item.image_url);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            price: data.price.replace(/\./g, ''),
            is_active: data.is_active ? '1' : '0',
        }));
        form.post(`/admin/paket/${item.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.setData('image', null);
            },
        });
    };

    const onImageChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('image', file);

        if (file) {
            setPreviewUrl(URL.createObjectURL(file));
        }
    };

    return (
        <form
            onSubmit={submit}
            className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm"
        >
            <div className="flex flex-col gap-5 lg:flex-row">
                <div className="w-full max-w-[220px]">
                    <div className="aspect-[4/3] overflow-hidden rounded-[20px] border border-[var(--color-border)] bg-[var(--color-panel)]">
                        {previewUrl ? (
                            <img
                                src={previewUrl}
                                alt={item.name}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <div className="flex h-full items-center justify-center px-4 text-center text-sm text-slate-500">
                                Foto belum diisi
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex-1 space-y-4">
                    <div>
                        <h2 className="text-xl font-semibold">{item.name}</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-700">
                            {item.description}
                        </p>
                        <p className="mt-3 text-sm font-medium text-slate-800">
                            {formatRupiah(item.price)}
                        </p>
                    </div>

                    <label className="block">
                        <span className="mb-2 block text-sm font-medium">
                            Harga
                        </span>
                        <input
                            type="text"
                            inputMode="numeric"
                            value={form.data.price}
                            onChange={(event) =>
                                form.setData(
                                    'price',
                                    formatNominalInput(event.target.value),
                                )
                            }
                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                        />
                    </label>

                    <label className="block">
                        <span className="mb-2 block text-sm font-medium">
                            Foto paket
                        </span>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            onChange={onImageChange}
                            className="block w-full text-sm"
                        />
                    </label>

                    <label className="flex items-center gap-3 rounded-2xl bg-[var(--color-panel)] px-4 py-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                        />
                        Tampilkan paket ini
                    </label>
                    {form.errors.price ? (
                        <p className="text-sm text-red-700">
                            {form.errors.price}
                        </p>
                    ) : null}
                    {form.errors.image ? (
                        <p className="text-sm text-red-700">
                            {form.errors.image}
                        </p>
                    ) : null}

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60"
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </form>
    );
}

export default function AdminPackagesPage() {
    const { packages, flash, errors } = usePage<Props>().props;

    return (
        <>
            <Head title="Paket" />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-6xl space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold">Paket</h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                Isi harga dan foto terlebih dahulu sebelum paket
                                ditampilkan.
                            </p>
                        </div>

                        <Link
                            href="/admin"
                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </div>

                    {flash?.status ? (
                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.status}
                        </div>
                    ) : null}

                    {errors.package ? (
                        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {errors.package}
                        </div>
                    ) : null}

                    <div className="space-y-5">
                        {packages.map((item) => (
                            <PackageCard key={item.id} item={item} />
                        ))}
                    </div>
                </div>
            </main>
        </>
    );
}
