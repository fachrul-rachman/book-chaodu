import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

type Props = {
    payment_settings: {
        bank_name: string | null;
        bank_account_holder: string | null;
        virtual_account_mode: 'FIXED' | 'POOL' | null;
        prayer_virtual_account: string | null;
        incense_virtual_account: string | null;
        combo_virtual_account: string | null;
        prayer_virtual_accounts: string | null;
        incense_virtual_accounts: string | null;
        combo_virtual_accounts: string | null;
    };
    virtual_account_summary: Record<
        string,
        {
            configured: boolean;
            account_number: string | null;
            total: number;
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
        virtual_account_mode: payment_settings.virtual_account_mode ?? 'FIXED',
        prayer_virtual_account: payment_settings.prayer_virtual_account ?? '',
        incense_virtual_account: payment_settings.incense_virtual_account ?? '',
        combo_virtual_account: payment_settings.combo_virtual_account ?? '',
        prayer_virtual_accounts: payment_settings.prayer_virtual_accounts ?? '',
        incense_virtual_accounts: payment_settings.incense_virtual_accounts ?? '',
        combo_virtual_accounts: payment_settings.combo_virtual_accounts ?? '',
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

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Cara pakai nomor VA
                                </span>
                                <select
                                    value={form.data.virtual_account_mode}
                                    onChange={(event) =>
                                        form.setData(
                                            'virtual_account_mode',
                                            event.target.value as
                                                | 'FIXED'
                                                | 'POOL',
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                >
                                    <option value="FIXED">
                                        Satu paket satu nomor tetap
                                    </option>
                                    <option value="POOL">
                                        Satu paket banyak nomor
                                    </option>
                                </select>
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
                                            Status:{' '}
                                            {virtual_account_summary[code]
                                                ?.configured
                                                ? 'Sudah diisi'
                                                : 'Belum diisi'}
                                        </p>
                                        <p>
                                            Nomor:{' '}
                                            {virtual_account_summary[code]
                                                ?.account_number ?? '-'}
                                        </p>
                                        <p>
                                            Jumlah:{' '}
                                            {virtual_account_summary[code]
                                                ?.total ?? 0}
                                        </p>
                                    </div>
                                ))}
                            </div>

                            {form.data.virtual_account_mode === 'FIXED' ? (
                                <>
                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium">
                                            Nomor VA sembahyang
                                        </span>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            value={
                                                form.data
                                                    .prayer_virtual_account
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'prayer_virtual_account',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                    </label>

                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium">
                                            Nomor VA hio
                                        </span>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            value={
                                                form.data
                                                    .incense_virtual_account
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'incense_virtual_account',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                    </label>

                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium">
                                            Nomor VA combo
                                        </span>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            value={
                                                form.data
                                                    .combo_virtual_account
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'combo_virtual_account',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                    </label>
                                </>
                            ) : (
                                <>
                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium">
                                            Daftar nomor VA sembahyang
                                        </span>
                                        <textarea
                                            rows={5}
                                            value={
                                                form.data
                                                    .prayer_virtual_accounts
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'prayer_virtual_accounts',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                    </label>

                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium">
                                            Daftar nomor VA hio
                                        </span>
                                        <textarea
                                            rows={5}
                                            value={
                                                form.data
                                                    .incense_virtual_accounts
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'incense_virtual_accounts',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                    </label>

                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium">
                                            Daftar nomor VA combo
                                        </span>
                                        <textarea
                                            rows={5}
                                            value={
                                                form.data
                                                    .combo_virtual_accounts
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'combo_virtual_accounts',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                    </label>
                                </>
                            )}

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
