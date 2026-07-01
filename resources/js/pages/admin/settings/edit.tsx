import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

type Props = {
    payment_settings: {
        bank_name: string | null;
        bank_account_holder: string | null;
    };
    virtual_account_summary: Record<
        string,
        {
            total: number;
            available: number;
            held: number;
            assigned: number;
        }
    >;
    flash?: {
        status?: string | null;
    };
};

export default function PaymentSettingsPage() {
    const { payment_settings, virtual_account_summary, flash } =
        usePage<Props>().props;
    const form = useForm({
        bank_name: payment_settings.bank_name ?? '',
        bank_account_holder: payment_settings.bank_account_holder ?? '',
        prayer_virtual_accounts: '',
        incense_virtual_accounts: '',
        combo_virtual_accounts: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/admin/pembayaran', {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Informasi pembayaran" />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-3xl space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold">
                                Informasi pembayaran
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                Data ini akan ditampilkan pada halaman
                                pembayaran.
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

                    <form
                        onSubmit={submit}
                        className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm sm:p-8"
                    >
                        <div className="space-y-4">
                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nama bank
                                </span>
                                <input
                                    type="text"
                                    value={form.data.bank_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'bank_name',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nama penerima
                                </span>
                                <input
                                    type="text"
                                    value={form.data.bank_account_holder}
                                    onChange={(event) =>
                                        form.setData(
                                            'bank_account_holder',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <div className="grid gap-4 rounded-2xl border border-[var(--color-border)] bg-[#F8F4EE] p-4 sm:grid-cols-3">
                                {[
                                    ['PRAYER', 'Sembahyang'],
                                    ['INCENSE', 'Hio'],
                                    ['COMBO', 'Combo'],
                                ].map(([code, label]) => (
                                    <div key={code} className="space-y-1 text-sm">
                                        <p className="font-semibold text-[#2C1810]">
                                            {label}
                                        </p>
                                        <p>
                                            Total:{' '}
                                            {virtual_account_summary[code]
                                                ?.total ?? 0}
                                        </p>
                                        <p>
                                            Tersedia:{' '}
                                            {virtual_account_summary[code]
                                                ?.available ?? 0}
                                        </p>
                                        <p>
                                            Dipakai sementara:{' '}
                                            {virtual_account_summary[code]
                                                ?.held ?? 0}
                                        </p>
                                        <p>
                                            Sudah masuk booking:{' '}
                                            {virtual_account_summary[code]
                                                ?.assigned ?? 0}
                                        </p>
                                    </div>
                                ))}
                            </div>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Tambah VA sembahyang
                                </span>
                                <textarea
                                    rows={6}
                                    value={form.data.prayer_virtual_accounts}
                                    onChange={(event) =>
                                        form.setData(
                                            'prayer_virtual_accounts',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Satu nomor per baris"
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Tambah VA hio
                                </span>
                                <textarea
                                    rows={6}
                                    value={form.data.incense_virtual_accounts}
                                    onChange={(event) =>
                                        form.setData(
                                            'incense_virtual_accounts',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Satu nomor per baris"
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Tambah VA combo
                                </span>
                                <textarea
                                    rows={6}
                                    value={form.data.combo_virtual_accounts}
                                    onChange={(event) =>
                                        form.setData(
                                            'combo_virtual_accounts',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Satu nomor per baris"
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            {Object.values(form.errors).length > 0 ? (
                                <div className="space-y-1 text-sm text-red-700">
                                    {Object.values(form.errors).map((error) => (
                                        <p key={error}>{error}</p>
                                    ))}
                                </div>
                            ) : null}

                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60"
                            >
                                {form.processing ? 'Menyimpan...' : 'Simpan'}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </>
    );
}
