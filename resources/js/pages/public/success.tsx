import { Head, Link, usePage } from '@inertiajs/react';
import { formatCurrency } from '@/lib/booking';

export default function PublicBookingSuccessPage() {
    const { booking_number, customer_email, package_name, package_price } =
        usePage<{
            booking_number: string;
            customer_email: string;
            package_name: string;
            package_price: string;
        }>().props;

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
                            Langkah berikutnya adalah <strong>membuka Gmail
                            Anda</strong>, lalu <strong>klik link pembayaran</strong>{' '}
                            yang kami kirimkan ke email:
                        </p>
                        <p className="mt-3 rounded-[20px] border border-[var(--color-brand)] bg-[var(--color-panel)] px-4 py-3 text-base font-semibold text-[var(--color-ink)]">
                            {customer_email}
                        </p>
                        <p className="mt-4 text-base leading-7 text-slate-700">
                            Pembayaran hanya bisa dilakukan dari link di email
                            tersebut. Jika belum terlihat, cek folder spam,
                            promosi, atau semua email.
                        </p>

                        <div className="mt-6 grid gap-4 rounded-[24px] bg-[var(--color-panel)] p-5 sm:grid-cols-2">
                            <div>
                                <p className="text-sm text-slate-600">
                                    Nomor booking
                                </p>
                                <p className="mt-2 text-2xl font-semibold tracking-wide text-[var(--color-ink)]">
                                    {booking_number}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-slate-600">
                                    Paket
                                </p>
                                <p className="mt-2 text-lg font-semibold text-[var(--color-ink)]">
                                    {package_name}
                                </p>
                                <p className="mt-1 text-base font-semibold text-[var(--color-brand)]">
                                    {formatCurrency(package_price)}
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 rounded-[24px] border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-7 text-amber-900">
                            <p>
                                <strong>Jangan transfer jika waktu booking pada link pembayaran sudah habis.</strong>{' '}
                                Jika waktu sudah habis, booking harus dibuat ulang.
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
