import { Head, Link, usePage } from '@inertiajs/react';

export default function PublicBookingSuccessPage() {
    const { booking_number } = usePage<{ booking_number: string }>().props;

    return (
        <>
            <Head title="Booking Berhasil" />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-3xl items-center">
                    <section className="w-full rounded-[28px] border border-[var(--color-border)] bg-white/95 p-6 shadow-sm sm:p-8">
                        <p className="text-sm font-semibold text-[var(--color-brand)]">
                            Booking berhasil
                        </p>
                        <h1 className="mt-3 text-3xl font-semibold text-[var(--color-ink)]">
                            Data Anda sudah kami terima.
                        </h1>
                        <p className="mt-4 text-base leading-7 text-slate-700">
                            Pembayaran sedang divalidasi. Mohon periksa email
                            secara berkala.
                        </p>

                        <div className="mt-6 rounded-[24px] bg-[var(--color-panel)] p-5">
                            <p className="text-sm text-slate-600">
                                Nomor booking
                            </p>
                            <p className="mt-2 text-2xl font-semibold tracking-wide text-[var(--color-ink)]">
                                {booking_number}
                            </p>
                        </div>

                        <div className="mt-6">
                            <Link
                                href="/"
                                className="inline-flex rounded-full border border-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-[var(--color-brand)]"
                            >
                                Buat booking lain
                            </Link>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
