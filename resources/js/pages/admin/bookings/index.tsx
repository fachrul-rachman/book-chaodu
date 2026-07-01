import { Head, Link, usePage } from '@inertiajs/react';

type BookingItem = {
    id: number;
    booking_number: string;
    customer_name: string;
    customer_phone: string;
    package_name: string;
    status: string;
    table_slot: string | null;
    incense_slot: number | null;
    created_at: string | null;
};

type Props = {
    selected_status: 'ALL' | 'PENDING' | 'APPROVED' | 'REJECTED';
    status_options: Array<{
        value: 'ALL' | 'PENDING' | 'APPROVED' | 'REJECTED';
        label: string;
    }>;
    status_counts: Record<'ALL' | 'PENDING' | 'APPROVED' | 'REJECTED', number>;
    bookings: BookingItem[];
};

export default function AdminBookingIndexPage() {
    const { bookings, selected_status, status_options, status_counts } =
        usePage<Props>().props;

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
                                href={
                                    item.value === 'ALL'
                                        ? '/admin/booking'
                                        : `/admin/booking?status=${item.value}`
                                }
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
