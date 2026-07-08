import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { Auth } from '@/types';

type PaperItem = {
    id: number;
    label: string;
    download_url: string | null;
};

type BookingItem = {
    id: number;
    booking_number: string;
    package_name: string;
    source_label: string;
    approved_at: string | null;
    is_printed: boolean;
    prayer_papers: PaperItem[];
};

type Props = {
    auth: Auth;
    selected_filter: 'ALL' | 'UNPRINTED' | 'PRINTED';
    filter_options: Array<{
        value: 'ALL' | 'UNPRINTED' | 'PRINTED';
        label: string;
    }>;
    filter_counts: Record<'ALL' | 'UNPRINTED' | 'PRINTED', number>;
    bookings: BookingItem[];
    flash?: {
        status?: string | null;
    };
};

export default function PrinterDashboard() {
    const { auth, selected_filter, filter_options, filter_counts, bookings, flash } =
        usePage<Props>().props;
    const user = auth.user!;
    const form = useForm({
        is_printed: false,
    });

    const updatePrinted = (bookingId: number, value: boolean) => {
        form.setData('is_printed', value);
        form.put(`/printer/booking/${bookingId}/print`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Daftar Print" />

            <main className="min-h-screen bg-[var(--color-bg,#f8fafc)] px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-6xl space-y-6">
                    <section className="rounded-[24px] border border-[var(--color-border)] bg-[var(--color-panel)] p-6 shadow-sm sm:p-8">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Petugas Print
                                </p>
                                <h1 className="mt-1 text-2xl font-semibold text-slate-900 sm:text-3xl">
                                    Daftar kertas siap print
                                </h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                                    Lihat booking yang sudah disetujui, download kertasnya, lalu tandai jika sudah di-print.
                                </p>
                                <p className="mt-2 text-sm text-slate-500">
                                    Masuk sebagai {user.name}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Link
                                    href="/printer/kertas-doa/cek-cepat"
                                    className="rounded-full bg-[var(--color-brand)] px-5 py-2 text-sm font-semibold text-white"
                                >
                                    Cek cepat kertas
                                </Link>
                                <Link
                                    href="/keluar"
                                    method="post"
                                    as="button"
                                    className="rounded-full border border-[var(--color-brand)] px-5 py-2 text-sm font-semibold text-[var(--color-brand)]"
                                >
                                    Keluar
                                </Link>
                            </div>
                        </div>
                    </section>

                    {flash?.status ? (
                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.status}
                        </div>
                    ) : null}

                    <div className="flex flex-wrap gap-3">
                        {filter_options.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                onClick={() =>
                                    router.get(
                                        '/printer',
                                        item.value === 'ALL'
                                            ? {}
                                            : { filter: item.value },
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    )
                                }
                                className={`rounded-full px-4 py-2 text-sm font-semibold ${
                                    selected_filter === item.value
                                        ? 'bg-[var(--color-brand)] text-white'
                                        : 'border border-[var(--color-border)] text-slate-700'
                                }`}
                            >
                                {item.label} ({filter_counts[item.value]})
                            </button>
                        ))}
                    </div>

                    <section className="overflow-hidden rounded-[24px] border border-[var(--color-border)] bg-white/90 shadow-sm">
                        {bookings.length === 0 ? (
                            <div className="px-6 py-8 text-sm text-slate-700">
                                Belum ada data pada daftar ini.
                            </div>
                        ) : (
                            <div className="divide-y divide-[var(--color-border)]">
                                {bookings.map((booking) => (
                                    <article
                                        key={booking.id}
                                        className="space-y-4 px-6 py-5"
                                    >
                                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div className="space-y-1">
                                                <p className="text-base font-semibold text-[var(--color-ink)]">
                                                    {booking.booking_number}
                                                </p>
                                                <p className="text-sm text-slate-700">
                                                    {booking.package_name}
                                                </p>
                                                <p className="text-sm text-slate-500">
                                                    {booking.source_label}
                                                </p>
                                                <p className="text-sm text-slate-500">
                                                    Disetujui:{' '}
                                                    {booking.approved_at ?? '-'}
                                                </p>
                                            </div>

                                            <label className="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] px-4 py-3 text-sm text-slate-700">
                                                <input
                                                    type="checkbox"
                                                    checked={booking.is_printed}
                                                    onChange={(event) =>
                                                        updatePrinted(
                                                            booking.id,
                                                            event.target.checked,
                                                        )
                                                    }
                                                    className="h-5 w-5 rounded border-[var(--color-border)] text-[var(--color-brand)]"
                                                />
                                                <span>Sudah di-print?</span>
                                            </label>
                                        </div>

                                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                            {booking.prayer_papers.length > 0 ? (
                                                booking.prayer_papers.map((paper) => (
                                                    <div
                                                        key={paper.id}
                                                        className="rounded-2xl border border-[var(--color-border)] bg-slate-50 p-4"
                                                    >
                                                        <p className="text-sm font-semibold text-slate-900">
                                                            {paper.label}
                                                        </p>
                                                        <div className="mt-3">
                                                            {paper.download_url ? (
                                                                <a
                                                                    href={
                                                                        paper.download_url
                                                                    }
                                                                    className="inline-flex rounded-full bg-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-white"
                                                                >
                                                                    Download
                                                                </a>
                                                            ) : (
                                                                <p className="text-sm text-slate-500">
                                                                    File belum ada.
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))
                                            ) : (
                                                <div className="rounded-2xl border border-dashed border-[var(--color-border)] bg-slate-50 p-4 text-sm text-slate-500">
                                                    Kertas belum tersedia.
                                                </div>
                                            )}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}
