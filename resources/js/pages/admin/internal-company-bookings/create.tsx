import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

type Props = {
    internal_company: {
        label: string;
        table_codes: string[];
        incense_numbers: number[];
    };
    errors: Record<string, string | undefined>;
};

type Name = {
    position: number;
    indonesian_name: string;
    mandarin_name: string;
};

export default function AdminInternalCompanyBookingCreatePage() {
    const { internal_company, errors } = usePage<Props>().props;
    const form = useForm({
        table_code: '',
        customer_name: '',
        deceased_names: [
            { position: 1, indonesian_name: '', mandarin_name: '' },
            { position: 2, indonesian_name: '', mandarin_name: '' },
        ] as Name[],
        incense_name: { position: 1, indonesian_name: '', mandarin_name: '' },
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/admin/booking/internal-perusahaan');
    };

    const updatePrayerName = (
        index: number,
        key: 'indonesian_name' | 'mandarin_name',
        value: string,
    ) => {
        form.setData(
            'deceased_names',
            form.data.deceased_names.map((name, nameIndex) =>
                nameIndex === index ? { ...name, [key]: value } : name,
            ),
        );
    };

    return (
        <>
            <Head title="Booking Internal Perusahaan" />
            <main className="min-h-screen bg-[var(--color-background)] px-4 py-8 text-[var(--color-foreground)] sm:px-6">
                <div className="mx-auto max-w-3xl space-y-6">
                    <div>
                        <Link
                            href="/admin/booking"
                            className="text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali ke daftar booking
                        </Link>
                        <h1 className="mt-3 text-3xl font-bold">
                            Booking Internal Perusahaan
                        </h1>
                        <p className="mt-2 text-sm text-[var(--color-muted-foreground)]">
                            Booking langsung disetujui. Kertas doa dan hio akan
                            dibuat otomatis lalu masuk ke album galeri.
                        </p>
                    </div>

                    {Object.keys(errors).length > 0 ? (
                        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {Object.values(errors).filter(Boolean).join(' ')}
                        </div>
                    ) : null}

                    <form
                        onSubmit={submit}
                        className="space-y-6 rounded-[24px] border border-[var(--color-border)] bg-white p-6 shadow-sm"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nomor meja
                                </span>
                                <select
                                    aria-label="Nomor meja"
                                    required
                                    value={form.data.table_code}
                                    onChange={(event) =>
                                        form.setData(
                                            'table_code',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                >
                                    <option value="">Pilih nomor meja</option>
                                    {internal_company.table_codes.map(
                                        (code, index) => (
                                            <option key={code} value={code}>
                                                {code} — Hio{' '}
                                                {
                                                    internal_company
                                                        .incense_numbers[index]
                                                }
                                            </option>
                                        ),
                                    )}
                                </select>
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nama customer
                                </span>
                                <input
                                    aria-label="Nama customer"
                                    required
                                    type="text"
                                    value={form.data.customer_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'customer_name',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {form.data.deceased_names.map((name, index) => (
                                <fieldset
                                    key={name.position}
                                    className="space-y-3 rounded-2xl border border-[var(--color-border)] p-4"
                                >
                                    <legend className="px-1 text-sm font-semibold">
                                        Nama doa {index + 1}
                                    </legend>
                                    <label className="block text-sm">
                                        Nama Indonesia
                                        <input
                                            type="text"
                                            value={name.indonesian_name}
                                            onChange={(event) =>
                                                updatePrayerName(
                                                    index,
                                                    'indonesian_name',
                                                    event.target.value,
                                                )
                                            }
                                            className="mt-2 w-full rounded-2xl border border-[var(--color-border)] px-4 py-3 text-base"
                                        />
                                    </label>
                                    <label className="block text-sm">
                                        Nama Mandarin
                                        <input
                                            type="text"
                                            value={name.mandarin_name}
                                            onChange={(event) =>
                                                updatePrayerName(
                                                    index,
                                                    'mandarin_name',
                                                    event.target.value,
                                                )
                                            }
                                            className="mt-2 w-full rounded-2xl border border-[var(--color-border)] px-4 py-3 text-base"
                                        />
                                    </label>
                                </fieldset>
                            ))}
                        </div>

                        <fieldset className="space-y-3 rounded-2xl border border-[var(--color-border)] p-4">
                            <legend className="px-1 text-sm font-semibold">
                                Nama hio
                            </legend>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="block text-sm">
                                    Nama Indonesia
                                    <input
                                        type="text"
                                        value={
                                            form.data.incense_name
                                                .indonesian_name
                                        }
                                        onChange={(event) =>
                                            form.setData('incense_name', {
                                                ...form.data.incense_name,
                                                indonesian_name:
                                                    event.target.value,
                                            })
                                        }
                                        className="mt-2 w-full rounded-2xl border border-[var(--color-border)] px-4 py-3 text-base"
                                    />
                                </label>
                                <label className="block text-sm">
                                    Nama Mandarin
                                    <input
                                        type="text"
                                        value={
                                            form.data.incense_name.mandarin_name
                                        }
                                        onChange={(event) =>
                                            form.setData('incense_name', {
                                                ...form.data.incense_name,
                                                mandarin_name:
                                                    event.target.value,
                                            })
                                        }
                                        className="mt-2 w-full rounded-2xl border border-[var(--color-border)] px-4 py-3 text-base"
                                    />
                                </label>
                            </div>
                        </fieldset>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="min-h-12 rounded-full bg-[var(--color-brand)] px-6 py-3 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {form.processing
                                ? 'Menyimpan...'
                                : 'Buat booking internal'}
                        </button>
                    </form>
                </div>
            </main>
        </>
    );
}
