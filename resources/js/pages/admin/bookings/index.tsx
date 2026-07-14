import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type BookingItem = {
    id: number;
    booking_number: string;
    customer_name: string;
    customer_phone: string;
    package_name: string;
    status: string;
    source_label: string;
    table_slot: string | null;
    incense_slot: number | null;
    created_at: string | null;
};

type Props = {
    selected_status: 'ALL' | 'PENDING' | 'APPROVED' | 'REJECTED';
    selected_package: 'ALL' | 'PRAYER' | 'INCENSE' | 'COMBO';
    search: string;
    status_options: Array<{
        value: 'ALL' | 'PENDING' | 'APPROVED' | 'REJECTED';
        label: string;
    }>;
    package_options: Array<{
        value: 'ALL' | 'PRAYER' | 'INCENSE' | 'COMBO';
        label: string;
    }>;
    status_counts: Record<'ALL' | 'PENDING' | 'APPROVED' | 'REJECTED', number>;
    bookings: BookingItem[];
};

export default function AdminBookingIndexPage() {
    const {
        bookings,
        selected_status,
        selected_package,
        search,
        status_options,
        package_options,
        status_counts,
    } = usePage<Props>().props;
    const [searchValue, setSearchValue] = useState(search);
    const [packageValue, setPackageValue] = useState(selected_package);

    const baseQuery = useMemo(() => {
        const query: Record<string, string> = {};

        if (search.trim() !== '') {
            query.search = search.trim();
        }

        if (selected_package !== 'ALL') {
            query.package = selected_package;
        }

        return query;
    }, [search, selected_package]);

    function submitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const query: Record<string, string> = {};

        if (selected_status !== 'ALL') {
            query.status = selected_status;
        }

        if (searchValue.trim() !== '') {
            query.search = searchValue.trim();
        }

        if (packageValue !== 'ALL') {
            query.package = packageValue;
        }

        router.get('/admin/booking', query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    function resetFilters() {
        setSearchValue('');
        setPackageValue('ALL');

        const query =
            selected_status === 'ALL' ? {} : { status: selected_status };

        router.get('/admin/booking', query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Booking" />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-6xl space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold">
                                Booking masuk
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                Cek data customer sebelum disetujui atau
                                ditolak.
                            </p>
                        </div>

                        <Link
                            href="/admin"
                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        {status_options.map((item) => (
                            <Link
                                key={item.value}
                                href="/admin/booking"
                                data={{
                                    ...baseQuery,
                                    ...(item.value === 'ALL'
                                        ? {}
                                        : { status: item.value }),
                                }}
                                className={`rounded-full px-4 py-2 text-sm font-semibold ${
                                    selected_status === item.value
                                        ? 'bg-[var(--color-brand)] text-white'
                                        : 'border border-[var(--color-border)] text-slate-700'
                                }`}
                            >
                                {item.label} ({status_counts[item.value]})
                            </Link>
                        ))}
                    </div>

                    <section className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-4 shadow-sm">
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_auto_auto]"
                        >
                            <label className="space-y-2">
                                <span className="text-sm font-medium text-slate-700">
                                    Cari booking
                                </span>
                                <input
                                    type="text"
                                    value={searchValue}
                                    onChange={(event) =>
                                        setSearchValue(event.target.value)
                                    }
                                    placeholder="Nomor booking atau nama customer"
                                    className="w-full rounded-2xl border border-[var(--color-border)] px-4 py-3 text-base outline-none ring-0 transition focus:border-[var(--color-brand)]"
                                />
                            </label>

                            <label className="space-y-2">
                                <span className="text-sm font-medium text-slate-700">
                                    Paket
                                </span>
                                <select
                                    value={packageValue}
                                    onChange={(event) =>
                                        setPackageValue(
                                            event.target.value as
                                                | 'ALL'
                                                | 'PRAYER'
                                                | 'INCENSE'
                                                | 'COMBO',
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] px-4 py-3 text-base outline-none ring-0 transition focus:border-[var(--color-brand)]"
                                >
                                    {package_options.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <button
                                type="submit"
                                className="rounded-2xl bg-[var(--color-brand)] px-5 py-3 text-base font-semibold text-white"
                            >
                                Cari
                            </button>

                            <button
                                type="button"
                                onClick={resetFilters}
                                className="rounded-2xl border border-[var(--color-border)] px-5 py-3 text-base font-semibold text-slate-700"
                            >
                                Reset
                            </button>
                        </form>
                    </section>

                    <section className="overflow-hidden rounded-[24px] border border-[var(--color-border)] bg-white/90 shadow-sm">
                        {bookings.length === 0 ? (
                            <div className="px-6 py-8 text-sm text-slate-700">
                                Belum ada booking pada daftar ini.
                            </div>
                        ) : (
                            <div className="divide-y divide-[var(--color-border)]">
                                {bookings.map((booking) => (
                                    <Link
                                        key={booking.id}
                                        href={`/admin/booking/${booking.id}`}
                                        className="block px-6 py-5 hover:bg-[var(--color-panel)]"
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="space-y-1">
                                                <p className="text-base font-semibold text-[var(--color-ink)]">
                                                    {booking.booking_number}
                                                </p>
                                                <p className="text-sm text-slate-700">
                                                    {booking.customer_name}
                                                </p>
                                                <p className="text-sm text-slate-700">
                                                    {booking.package_name}
                                                </p>
                                                <p className="text-sm text-slate-500">
                                                    {booking.source_label}
                                                </p>
                                            </div>

                                            <div className="space-y-1 text-sm text-slate-700 sm:text-right">
                                                <p>{booking.status}</p>
                                                <p>
                                                    Meja:{' '}
                                                    {booking.table_slot ?? '-'}{' '}
                                                    | Hio:{' '}
                                                    {booking.incense_slot ??
                                                        '-'}
                                                </p>
                                                <p>
                                                    {booking.created_at ?? '-'}
                                                </p>
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}
