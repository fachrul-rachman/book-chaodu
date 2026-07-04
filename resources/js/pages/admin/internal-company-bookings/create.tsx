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

export default function AdminInternalCompanyBookingCreatePage() {
    const { internal_company, errors } = usePage<Props>().props;
    const form = useForm({
        customer_name: '',
        customer_phone: '+62',
        customer_email: '',
        attendee_count: '1',
        vegetarian_quantity: '0',
        non_vegetarian_quantity: '0',
        deceased_names: [
            { position: 1, indonesian_name: '', mandarin_name: '' },
            { position: 2, indonesian_name: '', mandarin_name: '' },
        ],
        incense_name: {
            position: 1,
            indonesian_name: '',
            mandarin_name: '',
        },
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            attendee_count: Number(data.attendee_count),
            vegetarian_quantity: Number(data.vegetarian_quantity),
            non_vegetarian_quantity: Number(data.non_vegetarian_quantity),
        }));

        form.post('/admin/booking/internal-perusahaan');
    };

    return (
        <>
            <Head title="Booking Internal Perusahaan" />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-5xl space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold">
                                Booking {internal_company.label}
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                Booking ini langsung jadi dan hanya memakai slot
                                khusus kantor.
                            </p>
                        </div>

                        <Link
                            href="/admin"
                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </div>

                    <section className="rounded-[24px] border border-sky-200 bg-sky-50 p-5 text-sm text-sky-900">
                        <p className="font-semibold">{internal_company.label}</p>
                        <p className="mt-2">
                            Meja khusus: {internal_company.table_codes.join(', ')}
                        </p>
                        <p className="mt-1">
                            Hio khusus: {internal_company.incense_numbers.join(', ')}
                        </p>
                    </section>

                    {Object.keys(errors).length > 0 ? (
                        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {Object.values(errors).filter(Boolean).join(' ')}
                        </div>
                    ) : null}

                    <form
                        onSubmit={submit}
                        className="space-y-6 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nama pemesan
                                </span>
                                <input
                                    type="text"
                                    value={form.data.customer_name}
                                    onChange={(event) =>
                                        form.setData('customer_name', event.target.value)
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Email
                                </span>
                                <input
                                    type="email"
                                    value={form.data.customer_email}
                                    onChange={(event) =>
                                        form.setData('customer_email', event.target.value)
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nomor telepon
                                </span>
                                <input
                                    type="text"
                                    value={form.data.customer_phone}
                                    onChange={(event) =>
                                        form.setData('customer_phone', event.target.value)
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Jumlah hadir
                                </span>
                                <input
                                    type="number"
                                    min={1}
                                    value={form.data.attendee_count}
                                    onChange={(event) =>
                                        form.setData('attendee_count', event.target.value)
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {form.data.deceased_names.map((name, index) => (
                                <div
                                    key={name.position}
                                    className="space-y-3 rounded-2xl border border-[var(--color-border)] p-4"
                                >
                                    <p className="text-sm font-semibold">
                                        Nama sembahyang {index + 1}
                                    </p>
                                    <input
                                        type="text"
                                        value={name.indonesian_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'deceased_names',
                                                form.data.deceased_names.map((item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              indonesian_name: event.target.value,
                                                          }
                                                        : item,
                                                ),
                                            )
                                        }
                                        placeholder="Nama Indonesia"
                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                    />
                                    <input
                                        type="text"
                                        value={name.mandarin_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'deceased_names',
                                                form.data.deceased_names.map((item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              mandarin_name: event.target.value,
                                                          }
                                                        : item,
                                                ),
                                            )
                                        }
                                        placeholder="Nama Mandarin"
                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                    />
                                </div>
                            ))}
                        </div>

                        <div className="space-y-3 rounded-2xl border border-[var(--color-border)] p-4">
                            <p className="text-sm font-semibold">Nama hio</p>
                            <input
                                type="text"
                                value={form.data.incense_name.indonesian_name}
                                onChange={(event) =>
                                    form.setData('incense_name', {
                                        ...form.data.incense_name,
                                        indonesian_name: event.target.value,
                                    })
                                }
                                placeholder="Nama Indonesia"
                                className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                            />
                            <input
                                type="text"
                                value={form.data.incense_name.mandarin_name}
                                onChange={(event) =>
                                    form.setData('incense_name', {
                                        ...form.data.incense_name,
                                        mandarin_name: event.target.value,
                                    })
                                }
                                placeholder="Nama Mandarin"
                                className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Makanan vegetarian
                                </span>
                                <input
                                    type="number"
                                    min={0}
                                    value={form.data.vegetarian_quantity}
                                    onChange={(event) =>
                                        form.setData('vegetarian_quantity', event.target.value)
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Makanan non vegetarian
                                </span>
                                <input
                                    type="number"
                                    min={0}
                                    value={form.data.non_vegetarian_quantity}
                                    onChange={(event) =>
                                        form.setData('non_vegetarian_quantity', event.target.value)
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>
                        </div>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {form.processing
                                ? 'Menyimpan...'
                                : 'Buat booking Internal Perusahaan'}
                        </button>
                    </form>
                </div>
            </main>
        </>
    );
}
